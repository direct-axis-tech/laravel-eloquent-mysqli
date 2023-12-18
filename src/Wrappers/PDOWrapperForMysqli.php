<?php

namespace LaravelEloquentMySQLi\Wrappers;

use PDO;
use RuntimeException;

class PDOWrapperForMysqli {
    use WrapperTrait;

    /**
     * The active MySqli connection.
     *
     * @var \mysqli
     */
    protected $singleton;

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
}