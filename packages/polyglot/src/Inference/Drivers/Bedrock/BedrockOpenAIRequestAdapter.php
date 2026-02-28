<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Bedrock;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

class BedrockOpenAIRequestAdapter implements CanTranslateInferenceRequest
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    #[\Override]
    public function toHttpRequest(InferenceRequest $request): HttpRequest
    {
        return new HttpRequest(
            url: $this->toUrl($request),
            method: 'POST',
            headers: $this->toHeaders($request),
            body: $this->bodyFormat->toRequestBody($request),
            options: ['stream' => $request->isStreamed()],
        );
    }

    // INTERNAL /////////////////////////////////////////////

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

        return array_filter($headers);
    }

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
