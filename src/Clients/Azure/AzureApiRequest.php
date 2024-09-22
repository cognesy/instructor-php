<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;

class AzureApiRequest extends ApiRequest
{
    public function __construct(
        array            $body = [],
        string           $endpoint = '',
        string           $method = 'POST',
        ApiRequestConfig $requestConfig = null,
        array            $data = [],
    ) {
        if ($this->isStreamed()) {
            $body['stream_options']['include_usage'] = true;
        }

        parent::__construct(
            body: $body,
            endpoint: $endpoint,
            method: $method,
            requestConfig: $requestConfig,
            data: $data,
        );
    }

    public function tools(): array {
        return $this->tools;
    }

    public function toolChoice(): string|array {
        if (empty($this->tools)) {
            return '';
        }
        return $this->toolChoice ?: 'auto';
    }

    public function responseSchema() : array {
        return $this->jsonSchema ?? [];
    }

    public function responseFormat(): array {
        return match($this->mode) {
            Mode::Json => ['type' => 'json_object'],
            default => $this->responseFormat ?? []
        };
    }
}