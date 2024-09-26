<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\Extras\LLM\InferenceRequest;

class AzureOpenAIDriver extends OpenAIDriver
{
    // OVERRIDES /////////////////////////////////////////////

    protected function getEndpointUrl(InferenceRequest $request): string {
        return str_replace(
                search: array_map(fn($key) => "{".$key."}", array_keys($this->config->metadata)),
                replace: array_values($this->config->metadata),
                subject: "{$this->config->apiUrl}{$this->config->endpoint}"
            ) . $this->getUrlParams();
    }

    protected function getUrlParams(): string {
        $params = array_filter([
            'api-version' => $this->config->metadata['apiVersion'] ?? '',
        ]);
        if (!empty($params)) {
            return '?' . http_build_query($params);
        }
        return '';
    }

    protected function getRequestHeaders(): array {
        return [
            'Api-Key' => $this->config->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}