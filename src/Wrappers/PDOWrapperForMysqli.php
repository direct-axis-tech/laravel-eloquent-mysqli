<?php

namespace LaravelEloquentMySQLi\Wrappers;

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
}