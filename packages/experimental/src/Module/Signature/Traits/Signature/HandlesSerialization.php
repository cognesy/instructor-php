<?php

namespace Cognesy\Experimental\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Features\Schema\Factories\JsonSchemaToSchema;

trait HandlesSerialization
{
    public function toJson() {
        return [
            'shortSignature' => $this->shortSignature,
            'fullSignature' => $this->fullSignature,
            'description' => $this->description,
            'input' => $this->input->toJsonSchema(),
            'output' => $this->output->toJsonSchema(),
        ];
    }

    public static function fromJson(array $data) : static {
        $converter = new JsonSchemaToSchema;
        return new static(
            $converter->fromJsonSchema($data['input']),
            $converter->fromJsonSchema($data['output']),
            $data['description'] ?? '',
        );
    }
}