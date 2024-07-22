<?php
namespace Cognesy\Instructor\Clients\Cohere\Traits;

// COHERE API

trait HandlesRequestBody
{
    protected function model() : string {
        return $this->model;
    }

    public function messages(): array {
        return $this->messages;
    }

    public function tools(): array {
        if (empty($this->tools)) {
            return [];
        }
        $cohereFormat = [];
        foreach ($this->tools as $tool) {
            $parameters = [];
            foreach ($tool['function']['parameters']['properties'] as $name => $param) {
                $parameters[] = array_filter([
                    'name' => $name,
                    'description' => $param['description'] ?? '',
                    'type' => $this->toCohereType($param),
                    'required' => in_array($name, $this->tools['function']['parameters']['required']??[]),
                ]);
            }
            $cohereFormat[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'parameters_definitions' => $parameters,
            ];
        }
        return $cohereFormat;
    }

    public function getToolChoice(): string|array {
        return '';
    }

    protected function getResponseFormat(): array {
        return $this->responseFormat['format'] ?? [];
    }

    protected function getResponseSchema(): array {
        return $this->responseFormat['schema'] ?? [];
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    private function toCohereType(array $param) : string {
        $type = $param['type'] ?? 'string';
        return match($type) {
            'string' => 'str',
            'number' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            'array' => throw new \Exception('Array type not supported by Cohere'),
            'object' => throw new \Exception('Object type not supported by Cohere'),
            default => throw new \Exception('Unknown type'),
        };
    }
}
