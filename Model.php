<?php

namespace Forpart\Core;

use Forpart\Core\Database;
use Forpart\Core\Relations\Relation;
use Forpart\Core\Traits\HasAttributes;
use Forpart\Core\Traits\HasRelationships;
use Forpart\Core\Traits\HasQueryBuilder;
use Forpart\Core\Traits\HasSerializers;
use Forpart\Core\Traits\HasTimestamps;
use PDO;

abstract class Model implements \JsonSerializable
{
    use HasAttributes;
    use HasRelationships;
    use HasQueryBuilder;
    use HasSerializers;
    use HasTimestamps;

    /**
     * The database connection instance.
     *
     * @var PDO
     */
    protected $db;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The instace of the model.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = [];

    /**
     * The loaded relationships for the model.
     *
     * @var array
     */
    protected $relations = [];

    /**
     * Indicates which relations are loaded.
     * 
     * @var array
     */
    protected $loaded = [];

    /**
     * Create a new model instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->initializeAttributes();
        $this->bootTraits();
    }

    /**
     * Boot all traits on the model.
     *
     * @return void
     */
    protected function bootTraits()
    {
        $traits = class_uses_recursive(static::class);

        foreach ($traits as $trait) {
            if (method_exists($this, $method = 'boot' . class_basename($trait))) {
                $this->$method();
            }
        }
    }

    /**
     * Initialize model attributes.
     *
     * @return void
     */
    protected function initializeAttributes()
    {
        if (method_exists($this, 'cast')) {
            foreach ($this->cast() as $field => $type) {
                if ($type === 'hidden') {
                    $this->hidden[] = $field;
                }
            }
        }
    }

    /**
     * Get the current date/time in the configured timezone.
     *
     * @return string
     */
    protected function now()
    {
        $config = PathResolver::configPath('config.php');
        $timezone = $config['timezone'] ?? 'UTC';
        $dt = new \DateTime('now', new \DateTimeZone($timezone));
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Save the model to the database.
     *
     * @return bool|object
     */
    public function save()
    {
        // Determine if this is a new record or an existing one
        $exists = isset($this->attributes[$this->primaryKey]) && !empty($this->attributes[$this->primaryKey]);

        // Update timestamps based on whether it's a new record
        $this->touchTimestamps(!$exists);

        if ($exists) {
            // This is an update operation

            // Get model's primary key value
            $id = $this->attributes[$this->primaryKey];

            // Filter attributes through fillable
            $attributes = array_intersect_key($this->attributes, array_flip($this->fillable));

            // Build SET clause
            $fields = [];
            $params = [];

            foreach ($attributes as $key => $value) {
                $fields[] = "{$key} = ?";
                $params[] = $value;
            }

            // Build the SQL query
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
            $params[] = $id;

            // Prepare and execute statement
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } else {
            // Create operation
            return $this->create($this->attributes);
        }
    }

    /**
     * Execute a raw SQL query against the database.
     *
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     */
    protected function query($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Handle dynamic method calls on the model.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($name, $arguments)
    {
        // Try to call the method from our defined methods
        if (isset($this->methods[$name]) && is_callable($this->methods[$name])) {
            return call_user_func_array(
                $this->methods[$name]->bindTo($this, static::class),
                $arguments
            );
        }

        // Fallback to local method if it exists
        if (method_exists($this, $name . 'Method')) {
            return call_user_func_array([$this, $name . 'Method'], $arguments);
        }

        throw new \BadMethodCallException("Method {$name} does not exist.");
    }

    /**
     * Handle dynamic static method calls on the model.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        $instance = new static();
        return $instance->__call($name, $arguments);
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        // Check if relation is already loaded
        if (isset($this->relations[$key])) {
            return $this->relations[$key];
        }

        // If relation method exists, load and cache it
        if (method_exists($this, $key)) {
            $relation = $this->$key();
            if ($relation instanceof Relation) {
                return $this->relations[$key] = $relation->getResults();
            }
        }

        // Return attribute if exists
        return $this->attributes[$key] ?? null;
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        // Store in the attributes array
        $this->attributes[$key] = $value;
    }
}
