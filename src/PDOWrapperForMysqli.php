<?php

namespace LaravelEloquentMySQLi;

use mysqli;
use PDO;
use RuntimeException;

class PDOWrapperForMysqli {
    /**
     * The active MySqli connection.
     *
     * @var \mysqli
     */
    protected mysqli $singleton;

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
}