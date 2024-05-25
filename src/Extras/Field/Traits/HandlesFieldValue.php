<?php
namespace Cognesy\Instructor\Extras\Field\Traits;

trait HandlesFieldValue
{
    private mixed $value = null;

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
        return $this->value;
    }

    public function isEmpty() : bool {
        return is_null($this->value) || empty($this->value);
    }
}