<?php

namespace Core\Relations;

use Core\Relations\Relation;


class BelongsTo extends Relation
{
    protected $foreignKey;
    protected $ownerKey;

    public function __construct($parent, $related, $foreignKey, $ownerKey)
    {
        parent::__construct($parent, $related);
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function getResults()
    {
        $foreignKeyValue = $this->parent->{$this->foreignKey};
        if (is_null($foreignKeyValue)) {
            return null;
        }

        return $this->related->where($this->ownerKey, $foreignKeyValue)->first();
    }

    public function eagerLoad(array $models)
    {
        // Extract foreign keys
        $keys = [];
        foreach ($models as $model) {
            if (!is_null($model->{$this->foreignKey})) {
                $keys[] = $model->{$this->foreignKey};
            }
        }

        if (empty($keys)) {
            return;
        }

        // Get all related models in one query
        $related = $this->related->whereIn($this->ownerKey, $keys)->get();

        // Create a lookup of owner key to related model
        $dictionary = [];
        foreach ($related as $model) {
            $dictionary[$model->{$this->ownerKey}] = $model;
        }

        // Set the relation on each model
        foreach ($models as $model) {
            $key = $model->{$this->foreignKey};
            if (isset($dictionary[$key])) {
                $model->setRelation(class_basename($this->related), $dictionary[$key]);
            } else {
                $model->setRelation(class_basename($this->related), null);
            }
        }
    }
}
