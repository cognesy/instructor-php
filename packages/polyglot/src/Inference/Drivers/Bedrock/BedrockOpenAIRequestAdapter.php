<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Bedrock;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\BaseHttpRequestAdapter;

class BedrockOpenAIRequestAdapter extends BaseHttpRequestAdapter
{
    #[\Override]
    protected function toHeaders(InferenceRequest $request): array
    {
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];

        // Primary authentication: Bedrock API key
        if (!empty($this->config->apiKey)) {
            $headers['Authorization'] = "Bearer {$this->config->apiKey}";
        } else {
            // Fallback: AWS credentials with SigV4 signing
            // TODO: Implement AWS SigV4 signing when API key not available
            throw new \RuntimeException('Bedrock API key required. AWS credential authentication not yet implemented.');
        }

        // Optional Bedrock-specific headers
        $metadata = $this->config->metadata;
        if (!empty($metadata['guardrailId'])) {
            $headers['X-Amzn-Bedrock-GuardrailIdentifier'] = $metadata['guardrailId'];
        }
        if (!empty($metadata['guardrailVersion'])) {
            $headers['X-Amzn-Bedrock-GuardrailVersion'] = $metadata['guardrailVersion'];
        }

        return array_filter($headers, static fn (mixed $value): bool => (bool) $value);
    }

    #[\Override]
    protected function toUrl(InferenceRequest $request): string
    {
        $region = $this->config->metadata['region'] ?? 'us-east-1';

        // Validate region
        if (!BedrockConfiguration::validateRegion($region)) {
            throw new \InvalidArgumentException("Unsupported AWS region for Bedrock: {$region}");
        }

        // Build region-specific endpoint using configuration helper
        $baseUrl = BedrockConfiguration::buildEndpoint($region);

        return "{$baseUrl}{$this->config->endpoint}";
    }
}
