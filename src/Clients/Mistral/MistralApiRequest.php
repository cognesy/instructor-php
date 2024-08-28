<?php
namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

class MistralApiRequest extends ApiRequest
{
    public function tools() : array {
        return $this->tools;
    }

    public function getToolChoice(): string|array {
        return 'any';
    }

    protected function getResponseFormat(): array {
        return ['type' => 'json_object'];
    }

    protected function getResponseSchema(): array {
        return [];
    }
}