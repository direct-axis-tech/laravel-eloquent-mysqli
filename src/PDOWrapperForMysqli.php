<?php

namespace LaravelEloquentMySQLi;

use mysqli;
use PDO;
use PDOException;
use RuntimeException;

class PDOWrapperForMysqli {
    /**
     * The active MySqli connection.
     *
     * @var \mysqli
     */
    protected mysqli $singleton;

    /**
     * Track whether we're currently in a transaction.
     *
     * @var bool
     */
    protected $inTransaction = false;

    /**
     * Store error mode (mysqli doesn't have native support for this).
     *
     * @var int
     */
    protected $errorMode = PDO::ERRMODE_EXCEPTION;

    /**
     * Track autocommit state (mysqli doesn't provide a direct way to query this).
     *
     * @var bool
     */
    protected $autocommit = true;


    public function __construct(mysqli $singleton)
    {
        $this->singleton = $singleton;
    }

    public function __call($method, $arguments)
    {
        return call_user_func_array([$this->singleton, $method], $arguments);
    }

    public function __get($property)
    {
        return $this->singleton->$property;
    }

    public function __set($property, $value)
    {
        $this->singleton->$property = $value;
    }

    /**
     * Wrapper function for fluent syntax
     *
     * @param mysqli $singleton
     * @return static
     */
    public static function wrap(mysqli $singleton)
    {
        return new static($singleton);
    }

    /**
     * Polyfill for PDO lastInsertId
     *
     * @return int|string
     */
    public function lastInsertId()
    {
        return $this->singleton->insert_id;
    }

    /**
     * Polyfill for PDO quote
     *
     * @return int|string
     */
    public function quote($value, $type = PDO::PARAM_STR)
    {
        switch ($type) {
            case PDO::PARAM_STR:
                return "'{$this->singleton->real_escape_string($value)}'";
            case PDO::PARAM_INT:
                return (int) $value;
            default:
                throw new RuntimeException("Unhandled Type Passed To PDOWrapperForMysqli::quote", 1);
        }
    }

    /**
     * Polyfill for PDO beginTransaction
     *
     * @return bool
     * @throws PDOException
     */
    public function beginTransaction()
    {
        if ($this->inTransaction) {
            throw new PDOException("There is already an active transaction");
        }

        if (!$this->singleton->autocommit(false)) {
            throw new PDOException(
                "Failed to begin transaction: {$this->singleton->error}",
                $this->singleton->errno
            );
        }

        $this->autocommit = false;
        $this->inTransaction = true;
        return true;
    }

    /**
     * Polyfill for PDO commit
     *
     * @return bool
     * @throws PDOException
     */
    public function commit()
    {
        if (!$this->inTransaction) {
            return false;
        }

        if (!$this->singleton->commit()) {
            throw new PDOException(
                "Failed to commit transaction: {$this->singleton->error}",
                $this->singleton->errno
            );
        }

        $this->singleton->autocommit(true);
        $this->autocommit = true;
        $this->inTransaction = false;
        return true;
    }

    /**
     * Polyfill for PDO rollBack
     *
     * @return bool
     * @throws PDOException
     */
    public function rollBack()
    {
        if (!$this->inTransaction) {
            return false;
        }

        if (!$this->singleton->rollback()) {
            throw new PDOException(
                "Failed to rollback transaction: {$this->singleton->error}",
                $this->singleton->errno
            );
        }

        $this->singleton->autocommit(true);
        $this->autocommit = true;
        $this->inTransaction = false;
        return true;
    }

    /**
     * Polyfill for PDO inTransaction
     *
     * @return bool
     */
    public function inTransaction()
    {
        return $this->inTransaction;
    }

    /**
     * Polyfill for PDO getAttribute
     *
     * @param int $attribute
     * @return mixed
     * @throws PDOException
     */
    public function getAttribute($attribute)
    {
        switch ($attribute) {
            case PDO::ATTR_AUTOCOMMIT:
                return $this->autocommit;

            case PDO::ATTR_ERRMODE:
                return $this->errorMode;

            case PDO::ATTR_SERVER_VERSION:
                return $this->singleton->server_info;

            case PDO::ATTR_CLIENT_VERSION:
                return $this->singleton->client_info;

            case PDO::ATTR_CONNECTION_STATUS:
                // Check if connection is still alive
                return $this->singleton->ping() ? 'Connection OK; Waiting to send.' : 'Connection failed.';

            case PDO::ATTR_SERVER_INFO:
                return $this->singleton->server_info;

            default:
                throw new PDOException("Unsupported attribute: {$attribute}");
        }
    }

    /**
     * Polyfill for PDO setAttribute
     *
     * @param int $attribute
     * @param mixed $value
     * @return bool
     * @throws PDOException
     */
    public function setAttribute($attribute, $value)
    {
        switch ($attribute) {
            case PDO::ATTR_AUTOCOMMIT:
                if (!is_bool($value)) {
                    throw new PDOException("ATTR_AUTOCOMMIT value must be boolean");
                }
                if (!$this->singleton->autocommit($value)) {
                    throw new PDOException(
                        "Failed to set autocommit: {$this->singleton->error}",
                        $this->singleton->errno
                    );
                }
                $this->autocommit = $value;
                // Update transaction state if autocommit is enabled
                if ($value) {
                    $this->inTransaction = false;
                }
                return true;

            case PDO::ATTR_ERRMODE:
                $validModes = [PDO::ERRMODE_SILENT, PDO::ERRMODE_WARNING, PDO::ERRMODE_EXCEPTION];
                if (!in_array($value, $validModes, true)) {
                    throw new PDOException("Invalid error mode");
                }
                $this->errorMode = $value;
                return true;

            default:
                throw new PDOException("Unsupported attribute: {$attribute}");
        }
    }

    /**
     * Polyfill for PDO errorCode
     *
     * @return string|null
     */
    public function errorCode()
    {
        if ($this->singleton->errno === 0) {
            return '00000'; // Success SQLSTATE
        }

        // Map MySQL error codes to SQLSTATE codes
        // Common MySQL error codes and their SQLSTATE equivalents
        $errorCode = $this->singleton->errno;
        
        // MySQL error codes to SQLSTATE mapping
        // This is a simplified mapping - full mapping would be more comprehensive
        $sqlstateMap = [
            1045 => '28000', // Access denied
            1049 => '42000', // Unknown database
            1054 => '42S22', // Unknown column
            1062 => '23000', // Duplicate entry
            1064 => '42000', // SQL syntax error
            1146 => '42S02', // Table doesn't exist
            1216 => '23000', // Foreign key constraint
            1217 => '23000', // Foreign key constraint
            1451 => '23000', // Foreign key constraint fails
            1452 => '23000', // Foreign key constraint fails
        ];

        if (isset($sqlstateMap[$errorCode])) {
            return $sqlstateMap[$errorCode];
        }

        // For unmapped errors, return a generic SQLSTATE
        // Format: HY### where ### is a 3-digit code
        if ($errorCode >= 1000 && $errorCode < 2000) {
            return 'HY000'; // General error
        }

        // Return error code as string if no mapping found
        return (string) $errorCode;
    }

    /**
     * Polyfill for PDO errorInfo
     *
     * @return array
     */
    public function errorInfo()
    {
        if ($this->singleton->errno === 0) {
            return ['00000', null, null];
        }

        return [
            $this->errorCode(),           // SQLSTATE error code
            $this->singleton->errno,      // Driver-specific error code
            $this->singleton->error,      // Driver-specific error message
        ];
    }

    /**
     * Polyfill for PDO exec
     *
     * @param string $statement
     * @return int|false
     * @throws PDOException
     */
    public function exec($statement)
    {
        $result = $this->singleton->query($statement);

        if ($result === false) {
            $errorInfo = $this->errorInfo();
            throw new PDOException(
                "SQLSTATE[{$errorInfo[0]}]: {$errorInfo[2]}",
                (int) $errorInfo[1]
            );
        }

        // For SELECT statements, PDO exec returns 0
        // For INSERT/UPDATE/DELETE, return affected rows
        if (is_bool($result)) {
            // Boolean result means it was a non-SELECT query
            return $this->singleton->affected_rows;
        }

        // If it's a result set (SELECT), return 0
        if ($result instanceof \mysqli_result) {
            $result->close();
            return 0;
        }

        return $this->singleton->affected_rows;
    }
}