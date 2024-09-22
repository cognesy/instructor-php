<?php
namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

class MistralApiRequest extends ApiRequest
{
    public function tools() : array {
        return $this->tools;
    }

    public function toolChoice(): string|array {
        return 'any';
    }

    public function responseFormat(): array {
        return ['type' => 'json_object'];
    }

    public function responseSchema(): array {
        return [];
    }
}