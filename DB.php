<?php

namespace Core;

use PDO;

class DB
{
    protected $pdo;
    protected $table;
    protected $select = '*';
    protected $where = [];
    protected $bindings = [];
    protected $joins = [];


    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public static function table($table)
    {
        $instance = new self();
        $instance->table = $table;
        return $instance;
    }

    public function select($columns = '*')
    {
        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        }

        if ($this->select === '*') {
            $this->select = $columns;
        } else {
            $this->select .= ', ' . $columns;
        }

        return $this;
    }


    public function where($column, $operator, $value = null)
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = ':where_' . count($this->bindings);
        $this->where[] = "{$column} {$operator} {$placeholder}";
        $this->bindings[$placeholder] = $value;

        return $this;
    }

    public function get()
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first()
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }


        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insert(array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        return $stmt->execute();
    }

    public function update(array $data)
    {
        $set = [];
        foreach ($data as $key => $value) {
            $placeholder = ':set_' . $key;
            $set[] = "{$key} = {$placeholder}";
            $this->bindings[$placeholder] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set);
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        return $stmt->execute();
    }

    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        return $stmt->execute();
    }

    public function leftJoin($table, $first, $operator, $second)
    {
        $this->joins[] = "LEFT JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }



    public static function raw($sql)
    {
        $pdo = Database::getInstance();
        return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function query($sql, array $bindings = [])
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare($sql);

        foreach ($bindings as $key => $value) {
            $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
