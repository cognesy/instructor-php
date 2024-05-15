<?php

namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

class MistralApiRequest extends ApiRequest
{
    protected function getResponseFormat(): array {
        return ['type' => 'json_object'];
    }

    protected function getToolChoice(): string|array {
        if (empty($this->tools)) {
            return '';
        }
        return $this->toolChoice ?: 'any';
    }
}