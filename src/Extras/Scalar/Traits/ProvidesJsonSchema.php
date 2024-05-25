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
        $array = [
            '$comment' => Scalar::class,
            'type' => 'object',
            'properties' => [
                $this->name => [
                    '$comment' => $this->enumType ?? '',
                    'description' => $this->description,
                    'type' => $this->type->toJsonType(),
                ],
            ],
        ];
        if (!empty($this->options)) {
            $array['properties'][$this->name]['enum'] = $this->options;
        }
        if ($this->required) {
            $array['required'] = [$this->name];
        }
        return $array;
    }
}