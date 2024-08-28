<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;
use Saloon\Enums\Method;

class AzureApiRequest extends ApiRequest
{
    public function __construct(
        array            $body = [],
        string           $endpoint = '',
        Method           $method = Method::POST,
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
        return match($this->mode) {
            Mode::Json => ['type' => 'json_object'],
            default => $this->responseFormat ?? []
        };
    }
}