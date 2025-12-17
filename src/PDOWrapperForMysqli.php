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
}