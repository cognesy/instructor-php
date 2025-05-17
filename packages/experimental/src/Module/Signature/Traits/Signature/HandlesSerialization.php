<?php

namespace Cognesy\Experimental\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Features\Schema\Factories\JsonSchemaToSchema;

trait HandlesSerialization
{
    public function toArray() : array {
        return [
            'shortSignature' => $this->shortSignature,
            'fullSignature' => $this->fullSignature,
            'description' => $this->description,
            'input' => $this->input->toJsonSchema(),
            'output' => $this->output->toJsonSchema(),
        ];
    }

    public static function fromJsonData(array $data) : static {
        $converter = new JsonSchemaToSchema;
        return new static(
            $converter->fromJsonSchema($data['input']),
            $converter->fromJsonSchema($data['output']),
            $data['description'] ?? '',
        );
    }
}