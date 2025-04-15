<?php

namespace Forpart\Core\Traits;

use PDO;

trait HasQueryBuilder
{
    /**
     * The where clause.
     *
     * @var string
     */
    protected $whereClause = '';

    /**
     * Parameter bindings for query.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Boot the HasQueryBuilder trait for a model.
     *
     * @return void
     */
    protected function bootHasQueryBuilder()
    {
        $this->registerQueryBuilderMethods();
    }

    /**
     * Register the basic query builder methods.
     *
     * @return void
     */
    protected function registerQueryBuilderMethods()
    {
        // Basic find method
        $this->methods['find'] = function ($id) {
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $instance = new static(); // Create new instance of the model
                foreach ($result as $key => $value) {
                    $instance->{$key} = $value; // Hydrate properties
                }

                // Load any eager relations
                if (!empty($this->with)) {
                    $instance->loadRelations($this->with);
                }

                return $instance;
            }

            return null;
        };

        $this->methods['all'] = function ($orderBy = null, $order = 'ASC') {
            $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
            $sql = "SELECT * FROM {$this->table}";
            if ($orderBy) {
                $sql .= " ORDER BY {$orderBy} {$order}";
            }
            $stmt = $this->db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create model instances for each result
            $instances = [];
            foreach ($results as $result) {
                $instance = new static();
                foreach ($result as $key => $value) {
                    $instance->{$key} = $value;
                }
                $instances[] = $instance;
            }

            // Load any eager relations
            if (!empty($this->with) && !empty($instances)) {
                $this->eagerLoadRelations($instances, $this->with);
            }

            return $instances;
        };

        $this->methods['findBy'] = function ($field, $value) {
            $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':value', $value);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create model instances for each result
            $instances = [];
            foreach ($results as $result) {
                $instance = new static();
                foreach ($result as $key => $value) {
                    $instance->{$key} = $value;
                }
                $instances[] = $instance;
            }

            // Load any eager relations
            if (!empty($this->with) && !empty($instances)) {
                $this->eagerLoadRelations($instances, $this->with);
            }

            return $instances;
        };

        $this->methods['create'] = function (array $data) {
            $invalidKeys = array_diff(array_keys($data), $this->fillable);
            if (!empty($invalidKeys)) {
                $invalidFields = implode(', ', $invalidKeys);
                throw new \InvalidArgumentException("Fields not allowed for mass assignment: {$invalidFields}");
            }

            $data = array_intersect_key($data, array_flip($this->fillable));
            if (empty($data)) return false;

            $fields = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})";

            $stmt = $this->db->prepare($sql);
            foreach ($data as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            if ($stmt->execute()) {
                $lastId = (int) $this->db->lastInsertId();
                return $this->__call('find', [$lastId]);
            }

            return false;
        };

        $this->methods['update'] = function (array $attributes = []) {
            // Filter attributes through fillable fields
            $filtered = array_intersect_key($attributes, array_flip($this->fillable));
            if (empty($filtered)) return false;

            // Ensure we have a where clause for safety
            if (!$this->whereClause) {
                throw new \LogicException("Update operation requires where conditions");
            }

            // Build SET clause
            $fields = [];
            $params = [];

            foreach ($filtered as $key => $value) {
                $fields[] = "{$key} = ?";
                $params[] = $value;
            }

            // Build SQL query
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " {$this->whereClause}";

            // Replace named parameters with question marks for the where clause
            $whereParams = [];
            foreach ($this->bindings as $key => $value) {
                // Extract key name from :key format
                $keyName = substr($key, 1);
                $sql = str_replace($key, '?', $sql);
                $whereParams[] = $value;
            }

            // Combine parameters
            $allParams = array_merge($params, $whereParams);

            // Prepare and execute the query
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($allParams);

            // Reset query builder state
            $this->whereClause = '';
            $this->bindings = [];

            return $result;
        };

        $this->methods['delete'] = function ($id = null) {
            // If ID is provided, delete specific record
            if ($id !== null) {
                $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                return $stmt->execute();
            }

            // Otherwise, use the where clause for bulk delete
            $sql = "DELETE FROM {$this->table}";
            if ($this->whereClause) {
                $sql .= " {$this->whereClause}";
            } else {
                // Prevent accidental deletion of all records
                throw new \RuntimeException("Cannot delete without conditions. Use truncate() to delete all records.");
            }

            $stmt = $this->db->prepare($sql);
            foreach ($this->bindings as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $result = $stmt->execute();
            // Reset query builder state
            $this->whereClause = '';
            $this->bindings = [];

            return $result;
        };

        $this->methods['count'] = function () {
            $sql = "SELECT COUNT(*) FROM {$this->table}";
            if ($this->whereClause) {
                $sql .= " {$this->whereClause}";
            }
            $stmt = $this->db->prepare($sql);
            foreach ($this->bindings as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        };

        // Where clauses
        $this->methods['where'] = function ($field, $value, $operator = '=') {
            $allowedOperators = ['=', '<>', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN'];
            if (!in_array($operator, $allowedOperators)) {
                throw new \InvalidArgumentException("Invalid operator {$operator}");
            }

            if ($this->whereClause) {
                $this->whereClause .= " AND {$field} {$operator} :{$field}";
            } else {
                $this->whereClause = "WHERE {$field} {$operator} :{$field}";
            }

            $this->bindings[":{$field}"] = $value;

            return $this;
        };

        $this->methods['whereIn'] = function ($field, array $values) {
            if (empty($values)) {
                throw new \InvalidArgumentException("Values array cannot be empty for whereIn clause");
            }

            $placeholders = [];
            foreach ($values as $index => $value) {
                $paramName = ":{$field}_{$index}";
                $placeholders[] = $paramName;
                $this->bindings[$paramName] = $value;
            }

            $placeholdersStr = implode(', ', $placeholders);

            if ($this->whereClause) {
                $this->whereClause .= " AND {$field} IN ({$placeholdersStr})";
            } else {
                $this->whereClause = "WHERE {$field} IN ({$placeholdersStr})";
            }

            return $this;
        };

        $this->methods['whereNotIn'] = function ($field, array $values) {
            if (empty($values)) {
                throw new \InvalidArgumentException("Values array cannot be empty for whereNotIn clause");
            }

            $placeholders = [];
            foreach ($values as $index => $value) {
                $paramName = ":{$field}_{$index}";
                $placeholders[] = $paramName;
                $this->bindings[$paramName] = $value;
            }

            $placeholdersStr = implode(', ', $placeholders);

            if ($this->whereClause) {
                $this->whereClause .= " AND {$field} NOT IN ({$placeholdersStr})";
            } else {
                $this->whereClause = "WHERE {$field} NOT IN ({$placeholdersStr})";
            }

            return $this;
        };

        $this->methods['whereNull'] = function ($field) {
            if ($this->whereClause) {
                $this->whereClause .= " AND {$field} IS NULL";
            } else {
                $this->whereClause = "WHERE {$field} IS NULL";
            }

            return $this;
        };

        $this->methods['whereNotNull'] = function ($field) {
            if ($this->whereClause) {
                $this->whereClause .= " AND {$field} IS NOT NULL";
            } else {
                $this->whereClause = "WHERE {$field} IS NOT NULL";
            }

            return $this;
        };

        $this->methods['whereBetween'] = function ($field, $min, $max) {
            $minParam = ":{$field}_min";
            $maxParam = ":{$field}_max";

            if ($this->whereClause) {
                $this->whereClause .= " AND {$field} BETWEEN {$minParam} AND {$maxParam}";
            } else {
                $this->whereClause = "WHERE {$field} BETWEEN {$minParam} AND {$maxParam}";
            }

            $this->bindings[$minParam] = $min;
            $this->bindings[$maxParam] = $max;

            return $this;
        };

        $this->methods['whereNotBetween'] = function ($field, $min, $max) {
            $minParam = ":{$field}_min";
            $maxParam = ":{$field}_max";

            if ($this->whereClause) {
                $this->whereClause .= " AND {$field} NOT BETWEEN {$minParam} AND {$maxParam}";
            } else {
                $this->whereClause = "WHERE {$field} NOT BETWEEN {$minParam} AND {$maxParam}";
            }

            $this->bindings[$minParam] = $min;
            $this->bindings[$maxParam] = $max;

            return $this;
        };

        $this->methods['orWhere'] = function ($field, $value, $operator = '=') {
            $allowedOperators = ['=', '<>', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN'];
            if (!in_array($operator, $allowedOperators)) {
                throw new \InvalidArgumentException("Invalid operator {$operator}");
            }

            $paramName = ":{$field}_or" . count($this->bindings);

            if ($this->whereClause) {
                $this->whereClause .= " OR {$field} {$operator} {$paramName}";
            } else {
                $this->whereClause = "WHERE {$field} {$operator} {$paramName}";
            }

            $this->bindings[$paramName] = $value;

            return $this;
        };

        $this->methods['first'] = function () {
            $sql = "SELECT * FROM {$this->table}";
            if ($this->whereClause) {
                $sql .= " {$this->whereClause}";
            }
            $sql .= " LIMIT 1";

            $stmt = $this->db->prepare($sql);
            foreach ($this->bindings as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $instance = new static();
                foreach ($result as $key => $value) {
                    $instance->{$key} = $value;
                }

                // Load any eager relations
                if (!empty($this->with)) {
                    $instance->loadRelations($this->with);
                }

                return $instance;
            }

            return null;
        };

        $this->methods['get'] = function () {
            $sql = "SELECT * FROM {$this->table}";
            if ($this->whereClause) {
                $sql .= " {$this->whereClause}";
            }

            $stmt = $this->db->prepare($sql);
            foreach ($this->bindings as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create model instances
            $instances = [];
            foreach ($results as $result) {
                $instance = new static();
                foreach ($result as $key => $value) {
                    $instance->{$key} = $value;
                }
                $instances[] = $instance;
            }

            // Load any eager relations
            if (!empty($this->with) && !empty($instances)) {
                $this->eagerLoadRelations($instances, $this->with);
            }

            return $instances;
        };

        $this->methods['reset'] = function () {
            $this->whereClause = '';
            $this->bindings = [];
            return $this;
        };

        $this->methods['map'] = function (callable $callback) {
            // Get all the models first (using 'all' method)
            $instances = $this->methods['all']();

            // Apply the callback to each instance
            return array_map($callback, $instances);
        };

        $this->methods['select'] = function ($columns) {
            // Convert string to array if a single column is passed
            if (is_string($columns)) {
                $columns = [$columns];
            } elseif (!is_array($columns)) {
                throw new \InvalidArgumentException("Columns must be a string or array for select clause");
            }

            if (empty($columns)) {
                throw new \InvalidArgumentException("Columns cannot be empty for select clause");
            }

            $columnsStr = implode(', ', $columns);
            $this->methods['all'] = function ($orderBy = null, $order = 'ASC') use ($columnsStr) {
                $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
                $sql = "SELECT {$columnsStr} FROM {$this->table}";

                if ($orderBy) {
                    $sql .= " ORDER BY {$orderBy} {$order}";
                }

                $stmt = $this->db->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $instances = [];
                foreach ($results as $result) {
                    $instance = new static();
                    foreach ($result as $key => $value) {
                        $instance->{$key} = $value;
                    }
                    $instances[] = $instance;
                }

                // Load any eager relations
                if (!empty($this->with) && !empty($instances)) {
                    $this->eagerLoadRelations($instances, $this->with);
                }

                return $instances;
            };

            return $this;
        };
    }

    /**
     * Apply a callback to the model collection.
     *
     * @param callable $callback
     * @return array
     */
    public function mapMethod(callable $callback)
    {
        // If the current model is an instance, wrap it in an array for consistency
        $models = is_array($this) ? $this : [$this];

        // Map the callback function over the collection of models
        return array_map(function ($model) use ($callback) {
            // Apply the callback to each model instance
            return $callback($model);
        }, $models);
    }
}
