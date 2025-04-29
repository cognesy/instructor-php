<?php

namespace Cognesy\Instructor\Extras\Scalar\Traits;

use Cognesy\Instructor\Features\Deserialization\Exceptions\DeserializationException;
use Cognesy\Utils\Json\Json;
use Exception;

trait HandlesDeserialization
{
    /**
     * Deserialize JSON into scalar value
     */
    public function fromJson(string $jsonData, ?string $toolName = null) : static {
        if (empty($jsonData)) {
            $this->value = $this->defaultValue;
            return $this;
        }
        try {
            // decode JSON into array
            $array = Json::decode($jsonData);
        } catch (Exception $e) {
            throw new DeserializationException($e->getMessage(), $this->name, $jsonData);
        }
        // check if value exists in JSON
        $this->value = $array[$this->name] ?? $this->defaultValue;
        return $this;
    }
}