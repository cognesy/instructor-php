<?php
namespace Cognesy\Instructor\Extras\Structure\Traits\Field;

trait HandlesFieldValue
{
    private mixed $value = null;

    /**
     * Sets field value
     *
     * @param mixed $value
     * @return $this
     */
    public function set(mixed $value) : void {
        $this->value = $value;
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