<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;

beforeEach(function () {
    $this->adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());
});

it('extracts JSON body from data: prefixed line', function () {
    $line = 'data: {"type":"content_block_delta","delta":{"type":"text_delta","text":"Hi"}}';
    expect($this->adapter->toEventBody($line))
        ->toBe('{"type":"content_block_delta","delta":{"type":"text_delta","text":"Hi"}}');
});

it('returns empty string for event: lines', function () {
    // Anthropic sends "event: message_start", "event: content_block_delta", etc.
    expect($this->adapter->toEventBody('event: message_start'))->toBe('');
    expect($this->adapter->toEventBody('event: content_block_delta'))->toBe('');
    expect($this->adapter->toEventBody('event: message_stop'))->toBe('');
});

it('returns empty string for blank lines', function () {
    expect($this->adapter->toEventBody(''))->toBe('');
});

it('returns empty string for comment lines', function () {
    // SSE spec: lines starting with colon are comments
    expect($this->adapter->toEventBody(': keepalive'))->toBe('');
});

it('trims whitespace from extracted data body', function () {
    $line = 'data:  {"type":"ping"}  ';
    expect($this->adapter->toEventBody($line))->toBe('{"type":"ping"}');
});

it('does not treat data: within a value as a prefix', function () {
    // A line that doesn't start with "data:" should be skipped
    $line = '{"data: not a prefix"}';
    expect($this->adapter->toEventBody($line))->toBe('');
});

it('handles data: with no payload as empty string', function () {
    expect($this->adapter->toEventBody('data:'))->toBe('');
    expect($this->adapter->toEventBody('data: '))->toBe('');
});

it('returns false for explicit done markers', function () {
    expect($this->adapter->toEventBody('data: [DONE]'))->toBeFalse();
});

it('returns false for message_stop payload', function () {
    expect($this->adapter->toEventBody('data: {"type":"message_stop"}'))->toBeFalse();
});
