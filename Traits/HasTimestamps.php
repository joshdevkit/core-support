<?php

namespace Core\Traits;

trait HasTimestamps
{
    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    protected $timestamps = true;

    /**
     * Update the model's update timestamp.
     *
     * @return bool
     */
    public function touch()
    {
        if (!$this->timestamps) {
            return false;
        }

        $this->touchTimestamps(false);

        return $this->save();
    }

    /**
     * Set the value of model timestamps.
     *
     * @param  bool  $isCreating
     * @return void
     */
    protected function touchTimestamps($isCreating = false)
    {
        if (!$this->timestamps) {
            return;
        }

        $time = $this->now();

        if ($isCreating && static::CREATED_AT) {
            $this->{static::CREATED_AT} = $time;
        }

        if (static::UPDATED_AT) {
            $this->{static::UPDATED_AT} = $time;
        }
    }

    /**
     * Disable timestamps for the current operation.
     *
     * @return $this
     */
    public function withoutTimestamps()
    {
        $this->timestamps = false;

        return $this;
    }

    /**
     * Enable timestamps for the current operation.
     *
     * @return $this
     */
    public function withTimestamps()
    {
        $this->timestamps = true;

        return $this;
    }

    /**
     * Get the value of the model's created at attribute.
     *
     * @return mixed
     */
    public function getCreatedAtAttribute()
    {
        return $this->{static::CREATED_AT};
    }

    /**
     * Get the value of the model's updated at attribute.
     *
     * @return mixed
     */
    public function getUpdatedAtAttribute()
    {
        return $this->{static::UPDATED_AT};
    }
}
