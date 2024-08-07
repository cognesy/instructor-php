<?php
namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

trait HandlesApiRequestData
{
    protected array $data = [];

    public function data() : array {
        $requestedSchema = $this->requestedSchema();
        return array_filter(array_merge(
            $this->data,
            [
                'mode' => $this->mode(),
                'client_type' => ClientType::fromClientClass($this->client()),
                'tools' => $this->toolCallSchema() ?? [],
                'tool_choice' => $this->toolChoice() ?? [],
                'json_schema' => $this->jsonSchema() ?? [],
                'schema_name' => match(true) {
                    is_string($requestedSchema) => $requestedSchema,
                    is_array($requestedSchema) => $requestedSchema['name'] ?? 'default_schema',
                    is_object($requestedSchema) => get_class($requestedSchema),
                    default => 'default_schema',
                },
            ]
        ));
    }
}
