<?php

namespace Forpart\Core\Traits;

trait HasSerializers
{
    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $vars = get_object_vars($this);
        $internal = [
            'db',
            'table',
            'primaryKey',
            'fillable',
            'methods',
            'whereClause',
            'bindings',
            'hidden',
            'with',
            'loaded'
        ];

        // Remove each of these internal properties
        foreach ($internal as $prop) {
            if (array_key_exists($prop, $vars)) {
                unset($vars[$prop]);
            }
        }

        // Remove any keys defined in the hidden array
        foreach ($this->hidden as $key) {
            if (array_key_exists($key, $vars)) {
                unset($vars[$key]);
            }
        }

        // Include relations in the array representation
        if (!empty($this->relations)) {
            foreach ($this->relations as $relation => $value) {
                if (is_object($value) && method_exists($value, 'toArray')) {
                    $vars[$relation] = $value->toArray();
                } elseif (is_array($value)) {
                    $relationArray = [];
                    foreach ($value as $item) {
                        if (is_object($item) && method_exists($item, 'toArray')) {
                            $relationArray[] = $item->toArray();
                        } else {
                            $relationArray[] = $item;
                        }
                    }
                    $vars[$relation] = $relationArray;
                } else {
                    $vars[$relation] = $value;
                }
            }
        }

        return $vars;
    }

    /**
     * Convert the model to its JSON representation.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Convert the model to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the model to a string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return array
     */
    public function getVisible()
    {
        $properties = get_object_vars($this);

        foreach ($this->hidden as $key) {
            unset($properties[$key]);
        }

        return array_keys($properties);
    }

    /**
     * Get the hidden attributes for the model.
     *
     * @return array
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param array $hidden
     * @return $this
     */
    public function setHidden(array $hidden)
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Make the given model attributes visible.
     *
     * @param array|string $attributes
     * @return $this
     */
    public function makeVisible($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_diff($this->hidden, $attributes);

        return $this;
    }

    /**
     * Make the given model attributes hidden.
     *
     * @param array|string $attributes
     * @return $this
     */
    public function makeHidden($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_unique(array_merge($this->hidden, $attributes));

        return $this;
    }
}
