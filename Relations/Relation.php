<?php

namespace Forpart\Core\Relations;

use Forpart\Core\Model;

abstract class Relation
{
    protected $parent;
    protected $related;

    public function __construct($parent, $related)
    {
        $this->parent = $parent;
        $this->related = $related;
    }

    abstract public function getResults();
    abstract public function eagerLoad(array $models);
}
