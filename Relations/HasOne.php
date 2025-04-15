<?php

namespace Forpart\Core\Relations;

use Forpart\Core\Relations\Relation;

class HasOne extends Relation
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

        return $this->related->where($this->foreignKey, $localKeyValue)->first() ?? [];
    }


    public function eagerLoad(array $models)
    {
        $keys = [];
        foreach ($models as $model) {
            if (!is_null($model->{$this->localKey})) {
                $keys[] = $model->{$this->localKey};
            }
        }

        if (empty($keys)) {
            return;
        }

        $related = $this->related->whereIn($this->foreignKey, $keys)->get();

        $dictionary = [];
        foreach ($related as $model) {
            $dictionary[$model->{$this->foreignKey}] = $model;
        }

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
