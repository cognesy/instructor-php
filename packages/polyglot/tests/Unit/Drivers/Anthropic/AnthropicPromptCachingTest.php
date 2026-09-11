<?php

declare(strict_types=1);

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolResult;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;

function anthropicCacheBody(InferenceRequest $request, array $options = []): array
{
    return (new AnthropicBodyFormat(
        new LLMConfig(driver: 'anthropic', model: 'claude-sonnet-4-6', options: $options),
        new AnthropicMessageFormat(),
    ))->toRequestBody($request);
}

it('marks the actual final cached block without adding text or modifying live messages', function (ContentPart $part, string $wireType) {
    $role = match ($wireType) {
        'tool_use' => 'assistant',
        'tool_result' => 'tool',
        default => 'user',
    };
    $message = new Message(role: $role, parts: new ContentParts($part));
    $before = $message->toArray();
    $request = new InferenceRequest(
        messages: Messages::fromString('Live question'),
        cachedContext: new CachedInferenceContext(messages: new Messages($message), ttl: '1h'),
    );
    $body = anthropicCacheBody($request);
    expect($body['messages'][0]['content'])->toHaveCount(1)
        ->and($body['messages'][0]['content'][0]['type'])->toBe($wireType)
        ->and($body['messages'][0]['content'][0]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($body['messages'][1]['content'])->toBe('Live question')
        ->and($message->toArray())->toBe($before);
})->with([
    'text' => [ContentPart::text('Cached document'), 'text'],
    'tool use' => [ContentPart::toolCall(new ToolCall('search', ['q' => 'test'], 'call_1')), 'tool_use'],
    'tool result' => [ContentPart::toolResult(ToolResult::success('Found document', 'call_1')), 'tool_result'],
    'image' => [ContentPart::imageUrl('data:image/png;base64,aGVsbG8='), 'image'],
    'document' => [new ContentPart('document', ['source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => 'Document']]), 'document'],
]);

it('preserves signed thinking and interleaved text when adding a cache breakpoint', function () {
    $wire = [
        ['type' => 'text', 'text' => 'Before'],
        ['type' => 'thinking', 'thinking' => 'Reasoning', 'signature' => 'signature'],
        ['type' => 'text', 'text' => 'After'],
        ['type' => 'tool_use', 'id' => 'call_1', 'name' => 'search', 'input' => ['q' => 'test']],
    ];
    $message = (new AnthropicResponseAdapter(new AnthropicUsageFormat()))
        ->fromResponse(MockHttpResponseFactory::json(['content' => $wire]))->message();
    $body = anthropicCacheBody(new InferenceRequest(cachedContext: new CachedInferenceContext(messages: new Messages($message))));
    $wire[3]['cache_control'] = ['type' => 'ephemeral'];
    expect($body['messages'][0]['content'])->toBe($wire);
});

it('walks backwards past ineligible thinking blocks and never caches empty text', function () {
    $message = (new AnthropicResponseAdapter(new AnthropicUsageFormat()))->fromResponse(MockHttpResponseFactory::json([
        'content' => [['type' => 'thinking', 'thinking' => 'Reasoning', 'signature' => 'signature']],
    ]))->message();
    $cached = new CachedInferenceContext(messages: new Messages(Message::asUser('Stable'), $message));
    $body = anthropicCacheBody(new InferenceRequest(cachedContext: $cached));
    expect($body['messages'][0]['content'][0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($body['messages'][1]['content'])->toBe([['type' => 'thinking', 'thinking' => 'Reasoning', 'signature' => 'signature']]);
    $empty = Message::fromContentPart(ContentPart::text('')->withField('cache_control', ['type' => 'ephemeral']));
    $body = anthropicCacheBody(new InferenceRequest(cachedContext: new CachedInferenceContext(messages: new Messages($empty))));
    expect($body['messages'][0]['content'])->toBe([]);
});

it('preserves explicit block TTLs for system, user, assistant and tool blocks', function (string $role) {
    $control = ['type' => 'ephemeral', 'ttl' => '1h'];
    $part = match ($role) {
        'tool' => ContentPart::toolResult(ToolResult::success('Result', 'call_1')),
        'tool_use' => ContentPart::toolCall(new ToolCall('search', ['q' => 'test'], 'call_1')),
        default => ContentPart::text('Stable'),
    };
    $message = new Message(
        role: match ($role) {
            'tool_use' => 'assistant',
            default => $role,
        },
        parts: new ContentParts($part->withField('cache_control', $control)),
    );
    $request = new InferenceRequest(messages: new Messages($message));
    $body = anthropicCacheBody($request);
    $block = match ($role) {
        'system' => $body['system'][0],
        default => $body['messages'][0]['content'][0],
    };
    expect($block['cache_control'])->toBe($control);
    $cachedBody = anthropicCacheBody(new InferenceRequest(cachedContext: new CachedInferenceContext(messages: new Messages($message))));
    expect($cachedBody)->toBe($body);
})->with(['system', 'user', 'assistant', 'tool', 'tool_use']);

it('round trips explicit TTL through the request builder and context mutations', function () {
    $tools = ToolDefinitions::fromArray([['type' => 'function', 'function' => ['name' => 'search', 'parameters' => ['type' => 'object']]]]);
    $request = (new InferenceRequestBuilder())
        ->withCachedContext(messages: new Messages(Message::asSystem('System'), Message::asUser('Document')), tools: $tools, ttl: '1h')
        ->create();
    $request = InferenceRequest::fromArray($request->toArray());
    $context = $request->cachedContext()
        ->withMessages($request->cachedContext()->messages())
        ->withTools($tools)
        ->withToolChoice(ToolChoice::auto())
        ->withResponseFormat(ResponseFormat::empty());
    $body = anthropicCacheBody($request->withCachedContext($context));
    expect($context->ttl())->toBe('1h')
        ->and($body['tools'][0]['cache_control']['ttl'])->toBe('1h')
        ->and($body['system'][0]['cache_control']['ttl'])->toBe('1h')
        ->and($body['messages'][0]['content'][0]['cache_control']['ttl'])->toBe('1h');
});

it('forwards automatic caching to the top level with config override and streaming', function (bool $stream) {
    $request = (new InferenceRequestBuilder())
        ->withMessages(Messages::fromString('Question'))
        ->withOptions(['cache_control' => ['type' => 'ephemeral', 'ttl' => '1h']])
        ->withStreaming($stream)->create();
    $config = new LLMConfig(driver: 'anthropic', options: ['cache_control' => ['type' => 'ephemeral']]);
    $http = (new AnthropicRequestAdapter($config, new AnthropicBodyFormat($config, new AnthropicMessageFormat())))->toHttpRequest($request);
    $body = json_decode($http->body()->toString(), true);
    expect($body['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($http->isStreamed())->toBe($stream)
        ->and($body['messages'][0]['content'])->toBe('Question');
})->with([false, true]);

it('rejects unsupported explicit TTL values', function () {
    expect(fn() => new CachedInferenceContext(ttl: '24h'))->toThrow(InvalidArgumentException::class);
});
