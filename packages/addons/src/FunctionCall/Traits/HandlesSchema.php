<?php
namespace Cognesy\Addons\FunctionCall\Traits;

use Cognesy\Schema\Data\Schema\Schema;

trait HandlesSchema
{
    public function toSchema(): Schema {
        return $this->arguments->toSchema();
    }

    public function toJsonSchema(): array {
        return $this->arguments->toJsonSchema();
    }

    public function toToolCall() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->toJsonSchema(),
            ],
        ];
    }
}