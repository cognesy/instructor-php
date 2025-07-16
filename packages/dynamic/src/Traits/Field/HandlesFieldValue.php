<?php declare(strict_types=1);
namespace Cognesy\Dynamic\Traits\Field;

trait HandlesFieldValue
{
    private mixed $value;
    private mixed $defaultValue = null;

    /**
     * Sets field value
     *
     * @param mixed $value
     * @return $this
     */
    public function set(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    /**
     * Returns field value
     */
    public function get() : mixed {
        if (!isset($this->value)) {
            return $this->defaultValue;
        }
        return $this->value;
    }

    public function isEmpty() : bool {
        return is_null($this->get()) || empty($this->get());
    }

    public function withDefaultValue(mixed $value) : self {
        $this->defaultValue = $value;
        return $this;
    }

    public function hasDefaultValue() : bool {
        return !is_null($this->defaultValue);
    }

    public function defaultValue() : mixed {
        return $this->defaultValue;
    }
}