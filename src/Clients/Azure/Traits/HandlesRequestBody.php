<?php
namespace Cognesy\Instructor\Clients\Azure\Traits;

use Cognesy\Instructor\Enums\Mode;

trait HandlesRequestBody
{
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
            ->toArray();
    }

    public function tools(): array {
        return $this->tools;
    }

    public function getToolChoice(): string|array {
        if (empty($this->tools)) {
            return '';
        }
        return $this->toolChoice ?: 'auto';
    }

    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }

    protected function getResponseFormat(): array {
        return $this->responseFormat['format'] ?? [];
    }
}