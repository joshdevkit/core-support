<?php

namespace Forpart\Core\Traits;

use Forpart\Core\Relations\BelongsTo;
use Forpart\Core\Relations\BelongsToMany;
use Forpart\Core\Relations\HasMany;
use Forpart\Core\Relations\HasOne;

trait HasRelationships
{
    /**
     * The loaded relationships cache.
     *
     * @var array
     */
    protected $methods = [];

    /**
     * Boot the HasRelationships trait for a model.
     *
     * @return void
     */
    protected function bootHasRelationships()
    {
        $this->registerRelationMethods();
    }

    /**
     * Register the basic relation methods.
     *
     * @return void
     */
    protected function registerRelationMethods()
    {
        // With method for eager loading
        $this->methods['with'] = function ($relations = null) {
            if (is_null($relations)) {
                throw new \InvalidArgumentException("The 'with' method expects at least one argument.");
            }

            if (is_string($relations)) {
                $this->with[] = $relations;
            } elseif (is_array($relations)) {
                $this->with = array_merge($this->with, $relations);
            } else {
                throw new \InvalidArgumentException("The 'with' method expects a string or an array of relations.");
            }

            return $this;
        };


        // Define hasOne relation
        $this->methods['hasOne'] = function ($related, $foreignKey = null, $localKey = null) {
            $related = new $related();
            $localKey = $localKey ?: $this->primaryKey;
            $foreignKey = $foreignKey ?: strtolower(class_basename(get_class($this))) . '_id';

            return new HasOne($this, $related, $foreignKey, $localKey);
        };

        // Define hasMany relation
        $this->methods['hasMany'] = function ($related, $foreignKey = null, $localKey = null) {
            $related = new $related();
            $localKey = $localKey ?: $this->primaryKey;
            $foreignKey = $foreignKey ?: strtolower(class_basename(get_class($this))) . '_id';

            return new HasMany($this, $related, $foreignKey, $localKey);
        };

        // Define belongsTo relation
        $this->methods['belongsTo'] = function ($related, $foreignKey = null, $ownerKey = null) {
            $related = new $related();
            $ownerKey = $ownerKey ?: $related->primaryKey;
            $foreignKey = $foreignKey ?: strtolower(class_basename($related)) . '_id';

            return new BelongsTo($this, $related, $foreignKey, $ownerKey);
        };

        // Define belongsToMany relation
        $this->methods['belongsToMany'] = function ($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null) {
            $related = new $related();

            // If no pivot table is provided, create one from model names in alphabetical order
            if (is_null($table)) {
                $models = [
                    strtolower(class_basename($this)),
                    strtolower(class_basename($related))
                ];
                sort($models);
                $table = implode('_', $models);
            }

            $foreignPivotKey = $foreignPivotKey ?: strtolower(class_basename($this)) . '_id';
            $relatedPivotKey = $relatedPivotKey ?: strtolower(class_basename($related)) . '_id';

            return new BelongsToMany($this, $related, $table, $foreignPivotKey, $relatedPivotKey);
        };
    }

    /**
     * Load relations for a single model.
     *
     * @param array $relations
     * @return void
     */
    protected function loadRelations(array $relations)
    {
        foreach ($relations as $relation) {
            // Skip if already loaded
            if (isset($this->loaded[$relation])) {
                continue;
            }

            // Check if the relation method exists
            if (method_exists($this, $relation)) {
                // Get the relation object
                $relationObj = $this->$relation();

                // Load the related data
                $this->relations[$relation] = $relationObj->getResults();
                $this->loaded[$relation] = true;
            }
        }
    }

    /**
     * Eager load relations for multiple models.
     *
     * @param array $models
     * @param array $relations
     * @return void
     */
    protected function eagerLoadRelations(array $models, array $relations)
    {
        foreach ($relations as $relation) {
            // Skip if relation doesn't exist
            if (!method_exists($this, $relation)) {
                continue;
            }

            // Create the relation instance
            $relationObj = $this->$relation();

            // Let the relation handle loading itself onto the models
            $relationObj->eagerLoad($models);
        }
    }

    /**
     * Get a specified relationship.
     *
     * @param string $name
     * @return mixed
     */
    public function getRelation($name)
    {
        if (!is_string($name) && !is_int($name)) {
            return null;
        }

        return $this->relations[$name] ?? null;
    }

    /**
     * Set the specific relationship on the model.
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setRelation($name, $value)
    {
        if (!is_string($name) && !is_int($name)) {
            throw new \InvalidArgumentException("Relation name must be a string or integer");
        }

        $this->relations[$name] = $value;
        $this->loaded[$name] = true;

        return $this;
    }
}
