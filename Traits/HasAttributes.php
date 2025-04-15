<?php

namespace Core\Traits;

trait HasAttributes
{
    /**
     * Get a subset of the model's attributes.
     *
     * @return array
     */
    protected function getAttributes()
    {
        return array_intersect_key(get_object_vars($this), array_flip($this->fillable));
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        $this->$key = $value;

        return $this;
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }

        return null;
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param array $attributes
     * @return $this
     */
    public function setRawAttributes(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $attributes
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function fill(array $attributes)
    {
        $invalidKeys = array_diff(array_keys($attributes), $this->fillable);
        if (!empty($invalidKeys)) {
            $invalidFields = implode(', ', $invalidKeys);
            throw new \InvalidArgumentException("Fields not allowed for mass assignment: {$invalidFields}");
        }

        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->$key = $value;
            }
        }

        return $this;
    }
}
