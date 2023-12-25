<?php

namespace LaravelEloquentMySQLi\Wrappers;

trait WrapperTrait {
    /**
     * The object being wrapped
     */
    protected $singleton;

    public function __construct($singleton)
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
     * @param mixed $singleton
     * @return static
     */
    public static function wrap($singleton)
    {
        return new static($singleton);
    }
}