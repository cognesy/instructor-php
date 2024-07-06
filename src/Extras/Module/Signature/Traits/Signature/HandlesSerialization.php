<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Schema\Factories\SchemaConverter;

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

    public static function fromJson(array $data) {
        $converter = new SchemaConverter;
        $signature = new static(
            $converter->fromJsonSchema($data['input']),
            $converter->fromJsonSchema($data['output']),
            $data['description'] ?? '',
        );
    }
}