<?php

namespace Forpart\Core\Relations;

use Forpart\Core\Relations\Relation;

class HasMany extends Relation
{
    protected $foreignKey;
    protected $localKey;

    public function __construct($parent, $related, $foreignKey, $localKey)
    {
        parent::__construct($parent, $related);
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function getResults()
    {
        $localKeyValue = $this->parent->{$this->localKey};
        if (is_null($localKeyValue)) {
            return [];
        }

        return $this->related->where($this->foreignKey, $localKeyValue)->get();
    }

    public function eagerLoad(array $models)
    {
        // Extract parent keys
        $keys = [];
        foreach ($models as $model) {
            if (!is_null($model->{$this->localKey})) {
                $keys[] = $model->{$this->localKey};
            }
        }

        if (empty($keys)) {
            return;
        }

        // Get all related models in one query
        $related = $this->related->whereIn($this->foreignKey, $keys)->get();

        // Group related models by foreign key
        $dictionary = [];
        foreach ($related as $model) {
            $key = $model->{$this->foreignKey};
            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $model;
        }

        // Set the relation on each model
        foreach ($models as $model) {
            $key = $model->{$this->localKey};
            if (isset($dictionary[$key])) {
                $model->setRelation(class_basename($this->related), $dictionary[$key]);
            } else {
                $model->setRelation(class_basename($this->related), []);
            }
        }
    }
}
