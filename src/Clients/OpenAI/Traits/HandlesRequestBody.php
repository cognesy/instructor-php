<?php
namespace Cognesy\Instructor\Clients\OpenAI\Traits;

use Cognesy\Instructor\Enums\Mode;

trait HandlesRequestBody
{
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
        return $this->jsonSchema ?? [];
    }

    protected function getResponseFormat(): array {
        if ($this->mode == Mode::Json) {
            return ['type' => 'json_object'];
        }
        return $this->responseFormat;
    }
}
