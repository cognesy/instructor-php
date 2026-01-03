<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Scalar\Traits;

trait HandlesDeserialization
{
    /**
     * Deserialize array into scalar value
     */
    #[\Override]
    public function fromArray(array $data, ?string $toolName = null) : static {
        // check if value exists in JSON
        $this->value = $data[$this->name] ?? $this->defaultValue;
        return $this;
    }
}
