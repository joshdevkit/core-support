<?php

namespace Forpart\Core\Relations;

use Forpart\Core\Database;
use Forpart\Core\Relations\Relation;
use PDO;

class BelongsToMany extends Relation
{
    protected $table;
    protected $foreignPivotKey;
    protected $relatedPivotKey;

    public function __construct($parent, $related, $table, $foreignPivotKey, $relatedPivotKey)
    {
        parent::__construct($parent, $related);
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
    }

    public function getResults()
    {
        $db = Database::getInstance();

        $parentKey = $this->parent->{$this->parent->primaryKey};
        if (is_null($parentKey)) {
            return [];
        }

        $sql = "SELECT r.* FROM {$this->related->table} r
                INNER JOIN {$this->table} p ON p.{$this->relatedPivotKey} = r.{$this->related->primaryKey}
                WHERE p.{$this->foreignPivotKey} = :parentKey";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':parentKey', $parentKey);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create model instances
        $instances = [];
        foreach ($results as $result) {
            $instance = new (get_class($this->related));
            foreach ($result as $key => $value) {
                $instance->{$key} = $value;
            }
            $instances[] = $instance;
        }

        return $instances;
    }

    public function eagerLoad(array $models)
    {
        $db = Database::getInstance();

        // Extract parent keys
        $keys = [];
        foreach ($models as $model) {
            $keys[] = $model->{$model->primaryKey};
        }

        if (empty($keys)) {
            return;
        }

        // Prepare placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        // Get all pivot and related data at once
        $sql = "SELECT p.{$this->foreignPivotKey}, r.* 
                FROM {$this->related->table} r
                INNER JOIN {$this->table} p ON p.{$this->relatedPivotKey} = r.{$this->related->primaryKey}
                WHERE p.{$this->foreignPivotKey} IN ({$placeholders})";

        $stmt = $db->prepare($sql);

        // Bind all parent keys
        foreach ($keys as $index => $key) {
            $stmt->bindValue($index + 1, $key);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group related models by foreign pivot key
        $dictionary = [];
        foreach ($results as $result) {
            $parentKey = $result[$this->foreignPivotKey];

            if (!isset($dictionary[$parentKey])) {
                $dictionary[$parentKey] = [];
            }

            // Remove the pivot key from the result
            unset($result[$this->foreignPivotKey]);

            // Create a related model instance
            $instance = new (get_class($this->related));
            foreach ($result as $key => $value) {
                $instance->{$key} = $value;
            }

            $dictionary[$parentKey][] = $instance;
        }

        // Set the relation on each model
        foreach ($models as $model) {
            $key = $model->{$model->primaryKey};
            if (isset($dictionary[$key])) {
                $model->setRelation(class_basename($this->related), $dictionary[$key]);
            } else {
                $model->setRelation(class_basename($this->related), []);
            }
        }
    }

    public function attach($id, array $attributes = [])
    {
        $db = Database::getInstance();

        if (is_null($id)) {
            return false;
        }

        $parentKey = $this->parent->{$this->parent->primaryKey};

        // Check if the relation already exists
        $sql = "SELECT * FROM {$this->table} 
                WHERE {$this->foreignPivotKey} = :parentKey 
                AND {$this->relatedPivotKey} = :relatedKey";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':parentKey', $parentKey);
        $stmt->bindValue(':relatedKey', $id);
        $stmt->execute();

        if ($stmt->fetch()) {
            // Relation already exists, just update attributes if provided
            if (!empty($attributes)) {
                $sets = [];
                foreach (array_keys($attributes) as $column) {
                    $sets[] = "{$column} = :{$column}";
                }

                $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " 
                        WHERE {$this->foreignPivotKey} = :parentKey 
                        AND {$this->relatedPivotKey} = :relatedKey";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':parentKey', $parentKey);
                $stmt->bindValue(':relatedKey', $id);

                foreach ($attributes as $key => $value) {
                    $stmt->bindValue(":{$key}", $value);
                }

                return $stmt->execute();
            }

            return true; // Already attached, no attributes to update
        }

        // Create new pivot record
        $fields = [
            $this->foreignPivotKey,
            $this->relatedPivotKey
        ];

        $values = [
            ':' . $this->foreignPivotKey,
            ':' . $this->relatedPivotKey
        ];

        foreach (array_keys($attributes) as $column) {
            $fields[] = $column;
            $values[] = ':' . $column;
        }

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $values) . ")";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':' . $this->foreignPivotKey, $parentKey);
        $stmt->bindValue(':' . $this->relatedPivotKey, $id);

        foreach ($attributes as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        return $stmt->execute();
    }

    public function detach($ids = null)
    {
        $db = Database::getInstance();
        $parentKey = $this->parent->{$this->parent->primaryKey};

        $sql = "DELETE FROM {$this->table} WHERE {$this->foreignPivotKey} = :parentKey";
        $params = [':parentKey' => $parentKey];

        // If specific IDs are provided, only detach those
        if (!is_null($ids)) {
            if (!is_array($ids)) {
                $ids = [$ids];
            }

            if (empty($ids)) {
                return 0; // Nothing to detach
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND {$this->relatedPivotKey} IN ({$placeholders})";
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':parentKey', $parentKey);

        // Bind the related IDs if provided
        if (!is_null($ids) && is_array($ids)) {
            foreach ($ids as $index => $id) {
                $stmt->bindValue($index + 1, $id);
            }
        }

        $stmt->execute();
        return $stmt->rowCount(); // Return number of affected rows
    }

    public function sync(array $ids, $detaching = true)
    {
        $db = Database::getInstance();
        $parentKey = $this->parent->{$this->parent->primaryKey};

        // Get current related IDs
        $currentIds = $this->getCurrentRelatedIds();

        // Determine which IDs need to be attached and detached
        $idsToAttach = array_diff($ids, $currentIds);
        $idsToDetach = $detaching ? array_diff($currentIds, $ids) : [];

        // Detach IDs that are not in the provided array
        if (!empty($idsToDetach)) {
            $this->detach($idsToDetach);
        }

        // Attach new IDs
        foreach ($idsToAttach as $id) {
            $this->attach($id);
        }

        return true;
    }

    protected function getCurrentRelatedIds()
    {
        $db = Database::getInstance();
        $parentKey = $this->parent->{$this->parent->primaryKey};

        $sql = "SELECT {$this->relatedPivotKey} FROM {$this->table} 
                WHERE {$this->foreignPivotKey} = :parentKey";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':parentKey', $parentKey);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function toggle(array $ids)
    {
        $db = Database::getInstance();
        $parentKey = $this->parent->{$this->parent->primaryKey};

        // Get current related IDs
        $currentIds = $this->getCurrentRelatedIds();

        // Determine which IDs to attach and detach
        $idsToAttach = array_diff($ids, $currentIds);
        $idsToDetach = array_intersect($ids, $currentIds);

        // Perform detach operations
        if (!empty($idsToDetach)) {
            $this->detach($idsToDetach);
        }

        // Perform attach operations
        foreach ($idsToAttach as $id) {
            $this->attach($id);
        }

        return true;
    }

    public function updateExistingPivot($id, array $attributes)
    {
        $db = Database::getInstance();
        $parentKey = $this->parent->{$this->parent->primaryKey};

        if (empty($attributes)) {
            return true; // Nothing to update
        }

        $sets = [];
        foreach (array_keys($attributes) as $column) {
            $sets[] = "{$column} = :{$column}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " 
                WHERE {$this->foreignPivotKey} = :parentKey 
                AND {$this->relatedPivotKey} = :relatedKey";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':parentKey', $parentKey);
        $stmt->bindValue(':relatedKey', $id);

        foreach ($attributes as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        return $stmt->execute();
    }

    public function wherePivot($column, $value, $operator = '=')
    {
        // This method allows filtering the related models based on a pivot column
        $db = Database::getInstance();
        $parentKey = $this->parent->{$this->parent->primaryKey};

        $allowedOperators = ['=', '<>', '>', '<', '>=', '<=', 'LIKE'];
        if (!in_array($operator, $allowedOperators)) {
            throw new \InvalidArgumentException("Invalid operator {$operator}");
        }

        $sql = "SELECT r.* FROM {$this->related->table} r
                INNER JOIN {$this->table} p ON p.{$this->relatedPivotKey} = r.{$this->related->primaryKey}
                WHERE p.{$this->foreignPivotKey} = :parentKey
                AND p.{$column} {$operator} :value";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':parentKey', $parentKey);
        $stmt->bindValue(':value', $value);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create model instances
        $instances = [];
        foreach ($results as $result) {
            $instance = new (get_class($this->related));
            foreach ($result as $key => $value) {
                $instance->{$key} = $value;
            }
            $instances[] = $instance;
        }

        return $instances;
    }

    public function withPivot(array $columns)
    {
        // This feature would require modifying the getResults and eagerLoad methods
        // to include the pivot columns in the result.
        // For a full implementation, more architectural changes would be needed.

        // Here's a simplified version that gets results with pivot data
        $db = Database::getInstance();

        $parentKey = $this->parent->{$this->parent->primaryKey};
        if (is_null($parentKey)) {
            return [];
        }

        $pivotColumns = '';
        if (!empty($columns)) {
            foreach ($columns as $column) {
                $pivotColumns .= ", p.{$column} as pivot_{$column}";
            }
        }

        $sql = "SELECT r.*{$pivotColumns} FROM {$this->related->table} r
                INNER JOIN {$this->table} p ON p.{$this->relatedPivotKey} = r.{$this->related->primaryKey}
                WHERE p.{$this->foreignPivotKey} = :parentKey";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':parentKey', $parentKey);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create model instances with pivot data
        $instances = [];
        foreach ($results as $result) {
            $instance = new (get_class($this->related));
            $pivotData = [];

            foreach ($result as $key => $value) {
                // Check if it's a pivot column
                if (strpos($key, 'pivot_') === 0) {
                    $pivotKey = substr($key, 6); // Remove 'pivot_' prefix
                    $pivotData[$pivotKey] = $value;
                } else {
                    $instance->{$key} = $value;
                }
            }

            // Attach pivot data to the model
            if (!empty($pivotData)) {
                $instance->pivot = (object) $pivotData;
            }

            $instances[] = $instance;
        }

        return $instances;
    }
}
