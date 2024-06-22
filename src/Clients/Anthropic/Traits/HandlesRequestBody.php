<?php
namespace Cognesy\Instructor\Clients\Anthropic\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Enums\Mode;

// ANTHROPIC API

trait HandlesRequestBody {
    public function messages(): array {
        if ($this->noScript()) {
            return $this->messages;
        }

        if($this->mode->is(Mode::Tools)) {
            unset($this->scriptContext['json_schema']);
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

    public function system(): string {
        if ($this->noScript()) {
            return $this->system;
        }

        return $this->script
            ->withContext($this->scriptContext)
            ->select(['system'])
            ->toString();
    }

    public function getToolChoice(): string|array {
        return match(true) {
            empty($this->tools) => '',
            is_array($this->toolChoice) => [
                'type' => 'tool',
                'name' => $this->toolChoice['function']['name'],
            ],
            empty($this->toolChoice) => [
                'type' => 'auto',
            ],
            default => [
                'type' => $this->toolChoice,
            ],
        };
    }

    public function tools(): array {
        if (empty($this->tools)) {
            return [];
        }

        $anthropicFormat = [];
        foreach ($this->tools as $tool) {
            $anthropicFormat[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'],
            ];
        }

        return $anthropicFormat;
    }

    protected function getResponseFormat(): array {
        return [];
    }

    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }
}
