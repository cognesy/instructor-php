<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Inference\Drivers\Bedrock;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Bedrock\BedrockOpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BedrockOpenAIRequestAdapterTest extends TestCase
{
    private function createAdapter(array $configData = []): BedrockOpenAIRequestAdapter
    {
        $defaultConfig = [
            'apiUrl' => 'https://bedrock-runtime.{region}.amazonaws.com/openai/v1',
            'endpoint' => '/chat/completions',
            'apiKey' => 'test-bedrock-api-key',
            'model' => 'anthropic.claude-3-5-sonnet-20241022-v2:0',
            'metadata' => [
                'region' => 'us-east-1',
            ],
        ];

        $config = LLMConfig::fromArray(array_merge($defaultConfig, $configData));
        $bodyFormat = new OpenAICompatibleBodyFormat($config, new OpenAIMessageFormat());

        return new BedrockOpenAIRequestAdapter($config, $bodyFormat);
    }

    public function test_creates_http_request_with_bedrock_endpoint(): void
    {
        $adapter = $this->createAdapter([
            'metadata' => ['region' => 'us-west-2']
        ]);

        $inferenceRequest = new InferenceRequest(
            messages: Messages::fromArray([['role' => 'user', 'content' => 'Hello']]),
            model: 'anthropic.claude-3-5-sonnet-20241022-v2:0'
        );

        $httpRequest = $adapter->toHttpRequest($inferenceRequest);

        $this->assertEquals('https://bedrock-runtime.us-west-2.amazonaws.com/openai/v1/chat/completions', $httpRequest->url());
        $this->assertEquals('POST', $httpRequest->method());
        $this->assertEquals('Bearer test-bedrock-api-key', $httpRequest->headers()['Authorization']);
        $this->assertEquals('application/json', $httpRequest->headers()['Accept']);
    }

    public function test_includes_guardrail_headers_when_configured(): void
    {
        $adapter = $this->createAdapter([
            'metadata' => [
                'region' => 'us-east-1',
                'guardrailId' => 'test-guardrail-id',
                'guardrailVersion' => '1.0',
            ]
        ]);

        $inferenceRequest = new InferenceRequest(
            messages: Messages::fromArray([['role' => 'user', 'content' => 'Hello']])
        );

        $httpRequest = $adapter->toHttpRequest($inferenceRequest);

        $this->assertEquals('test-guardrail-id', $httpRequest->headers()['X-Amzn-Bedrock-GuardrailIdentifier']);
        $this->assertEquals('1.0', $httpRequest->headers()['X-Amzn-Bedrock-GuardrailVersion']);
    }

    public function test_throws_exception_for_missing_api_key(): void
    {
        $adapter = $this->createAdapter(['apiKey' => '']);

        $inferenceRequest = new InferenceRequest(
            messages: Messages::fromArray([['role' => 'user', 'content' => 'Hello']])
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bedrock API key required');
        $adapter->toHttpRequest($inferenceRequest);
    }

    public function test_throws_exception_for_invalid_region(): void
    {
        $adapter = $this->createAdapter([
            'metadata' => ['region' => 'invalid-region']
        ]);

        $inferenceRequest = new InferenceRequest(
            messages: Messages::fromArray([['role' => 'user', 'content' => 'Hello']])
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported AWS region for Bedrock: invalid-region');
        $adapter->toHttpRequest($inferenceRequest);
    }

    public function test_allows_any_model_id_string(): void
    {
        $adapter = $this->createAdapter();

        $inferenceRequest = new InferenceRequest(
            messages: Messages::fromArray([['role' => 'user', 'content' => 'Hello']]),
            model: 'us.anthropic.claude-3-5-sonnet-20241022-v2:0'
        );

        $httpRequest = $adapter->toHttpRequest($inferenceRequest);

        $this->assertEquals('POST', $httpRequest->method());
    }

    public function test_supports_streaming_requests(): void
    {
        $adapter = $this->createAdapter();

        $inferenceRequest = new InferenceRequest(
            messages: Messages::fromArray([['role' => 'user', 'content' => 'Hello']]),
            options: ['stream' => true]
        );

        $httpRequest = $adapter->toHttpRequest($inferenceRequest);

        $this->assertTrue($httpRequest->options()['stream']);
    }

    public function test_defaults_to_us_east_1_region(): void
    {
        $adapter = $this->createAdapter([
            'metadata' => [] // No region specified
        ]);

        $inferenceRequest = new InferenceRequest(
            messages: Messages::fromArray([['role' => 'user', 'content' => 'Hello']])
        );

        $httpRequest = $adapter->toHttpRequest($inferenceRequest);

        $this->assertEquals('https://bedrock-runtime.us-east-1.amazonaws.com/openai/v1/chat/completions', $httpRequest->url());
    }

    public function test_filters_empty_headers(): void
    {
        $adapter = $this->createAdapter([
            'metadata' => [
                'region' => 'us-east-1',
                'guardrailId' => '', // Empty guardrail ID should be filtered
                'guardrailVersion' => '1.0',
            ]
        ]);

        $inferenceRequest = new InferenceRequest(
            messages: Messages::fromArray([['role' => 'user', 'content' => 'Hello']])
        );

        $httpRequest = $adapter->toHttpRequest($inferenceRequest);

        $this->assertArrayNotHasKey('X-Amzn-Bedrock-GuardrailIdentifier', $httpRequest->headers());
        $this->assertEquals('1.0', $httpRequest->headers()['X-Amzn-Bedrock-GuardrailVersion']);
    }
}
