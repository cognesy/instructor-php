<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2UsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;

dataset('malformed_response_adapters', [
    'openai' => [fn() => new OpenAIResponseAdapter(new OpenAIUsageFormat())],
    'anthropic' => [fn() => new AnthropicResponseAdapter(new AnthropicUsageFormat())],
    'gemini' => [fn() => new GeminiResponseAdapter(new GeminiUsageFormat())],
    'cohere-v2' => [fn() => new CohereV2ResponseAdapter(new CohereV2UsageFormat())],
    'deepseek' => [fn() => new DeepseekResponseAdapter(new OpenAIUsageFormat())],
    'minimaxi' => [fn() => new MinimaxiResponseAdapter(new OpenAIUsageFormat())],
    'openresponses' => [fn() => new OpenResponsesResponseAdapter(new OpenResponsesUsageFormat())],
]);

it('throws on invalid JSON response payload in response adapters', function (callable $makeAdapter): void {
    /** @var CanTranslateInferenceResponse $adapter */
    $adapter = $makeAdapter();
    $response = HttpResponse::sync(
        statusCode: 200,
        headers: ['Content-Type' => 'application/json'],
        body: '{invalid-json',
    );

    expect(fn() => $adapter->fromResponse($response))
        ->toThrow(RuntimeException::class);
})->with('malformed_response_adapters');

it('throws on invalid JSON stream payload in response adapters', function (callable $makeAdapter): void {
    /** @var CanTranslateInferenceResponse $adapter */
    $adapter = $makeAdapter();

    expect(fn() => iterator_to_array($adapter->fromStreamDeltas(['{invalid-json'])))
        ->toThrow(RuntimeException::class);
})->with('malformed_response_adapters');

it('throws on malformed response shape in response adapters', function (callable $makeAdapter): void {
    /** @var CanTranslateInferenceResponse $adapter */
    $adapter = $makeAdapter();
    $response = HttpResponse::sync(
        statusCode: 200,
        headers: ['Content-Type' => 'application/json'],
        body: '{}',
    );

    expect(fn() => $adapter->fromResponse($response))
        ->toThrow(RuntimeException::class);
})->with('malformed_response_adapters');
