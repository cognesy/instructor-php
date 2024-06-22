<?php
namespace Cognesy\Instructor\Clients\Cohere\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

// COHERE API

trait HandlesRequestBody
{
    public function messages(): array {
        if ($this->noScript()) {
            return $this->messages;
        }

        return $this
            ->withMetaSections($this->script)
            ->withContext($this->scriptContext)
            ->select([
                'system',
                'pre-input', 'messages', 'input', 'post-input',
                'pre-prompt', 'prompt', 'post-prompt',
                'pre-examples', 'examples', 'post-examples',
                'pre-retries', 'retries', 'post-retries'
            ])
            ->toNativeArray(ClientType::fromRequestClass($this), mergePerRole: true);
    }

    public function preamble(): string {
        return '';
//        return $this->script
//            ->withContext($this->scriptContext)
//            ->select(['system'])
//            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }

    public function chatHistory(): array {
        return $this->clientType->toNativeMessages([]);
//        return $this->script
//            ->withContext($this->scriptContext)
//            ->select(['messages', 'data-ack', 'prompt', 'examples'])
//            ->toNativeArray(ClientType::fromRequestClass(static::class));
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
        return [];
    }

    protected function getResponseSchema(): array {
        return [];
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
