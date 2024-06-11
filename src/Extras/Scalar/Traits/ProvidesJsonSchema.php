<?php

namespace Cognesy\Instructor\Extras\Scalar\Traits;

use Cognesy\Instructor\Extras\Scalar\Scalar;

trait ProvidesJsonSchema
{
    /**
     * Custom JSON schema for scalar value - we ignore all fields in this class and pass only what we want
     * by manually creating the array representing JSON Schema of our desired structure.
     */
    public function toJsonSchema() : array {
        $name = $this->name;
        $array = [
            '$comment' => Scalar::class,
            'type' => 'object',
            'properties' => [
                $name => [
                    '$comment' => $this->enumType ?? '',
                    'description' => $this->description,
                    'type' => $this->type->toJsonType(),
                ],
            ],
        ];
        if (!empty($this->options)) {
            /** @noinspection UnsupportedStringOffsetOperationsInspection */
            $array['properties'][$name]['enum'] = $this->options;
        }
        if ($this->required) {
            $array['required'] = [$name];
        }
        return $array;
    }
}