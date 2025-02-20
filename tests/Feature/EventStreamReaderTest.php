<?php

namespace Tests\Feature;

use Cognesy\LLM\LLM\Events\StreamDataParsed;
use Cognesy\LLM\LLM\Events\StreamDataReceived;
use Cognesy\LLM\LLM\EventStreamReader;
use Cognesy\Utils\Events\EventDispatcher;
use Mockery as Mock;

beforeEach(function () {
    // Create a mock for the EventDispatcher
    $this->mockEventDispatcher = Mock::mock(EventDispatcher::class);
});

it('streams synthetic OpenAI streaming data correctly without parser', function () {
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataReceived::class));
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataParsed::class));

    $reader = new EventStreamReader(events: $this->mockEventDispatcher);

    $generator = function () {
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "Hello, ", "index": 0}]}'. "\n";
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "world!", "index": 1}]}' . "\n";
    };

    $expected = [
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "Hello, ", "index": 0}]}',
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "world!", "index": 1}]}',
    ];

    $result = iterator_to_array($reader->eventsFrom($generator()));
    expect($result)->toEqual($expected);
});

it('processes synthetic OpenAI streaming data with a custom parser', function () {
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataReceived::class));
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataParsed::class));

    $parser = fn($line) => substr($line, 6);
    $reader = new EventStreamReader(parser: $parser, events: $this->mockEventDispatcher);

    $generator = function () {
        yield 'data: {"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "Hello, ", "index": 0}]}'. "\n";
        yield 'data: {"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "world!", "index": 1}]}' . "\n";
    };

    $expected = [
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "Hello, ", "index": 0}]}',
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "world!", "index": 1}]}',
    ];

    $result = iterator_to_array($reader->eventsFrom($generator()));
    expect($result)->toMatchArray($expected);
});

it('dispatches events when streaming synthetic OpenAI data', function () {
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataReceived::class));
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataParsed::class));

    $reader = new EventStreamReader(events: $this->mockEventDispatcher);

    $generator = function () {
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "Hello, ", "index": 0}]}' . "\n";
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "world!", "index": 1}]}' . "\n";
    };

    // Convert to array to force full iteration and event dispatching
    iterator_to_array($reader->eventsFrom($generator()));
});

it('skips empty lines correctly in synthetic OpenAI data', function () {
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(5)->with(Mock::type(StreamDataReceived::class));
    $this->mockEventDispatcher->shouldReceive('dispatch')->twice()->with(Mock::type(StreamDataParsed::class));

    $reader = new EventStreamReader(events: $this->mockEventDispatcher);

    $generator = function () {
        yield  "\n";
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "Hello, ", "index": 0}]}'  . "\n";
        yield  "\n";
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "world!", "index": 1}]}' . "\n";
        yield  "\n";
    };

    $expected = [
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "Hello, ", "index": 0}]}',
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "world!", "index": 1}]}',
    ];

    $result = iterator_to_array($reader->eventsFrom($generator()));
    expect($result)->toEqual($expected);
});

it('handles incomplete lines correctly in synthetic OpenAI data', function () {
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataReceived::class));
    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataParsed::class));

    $reader = new EventStreamReader(events: $this->mockEventDispatcher);

    $generator = function () {
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "Hello"';
        yield ', "index": 0}]}' . "\n";
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "world!", "index": 1}]}' . "\n";
    };

    $expected = [
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "Hello", "index": 0}]}',
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "world!", "index": 1}]}',
    ];

    $result = iterator_to_array($reader->eventsFrom($generator()));
    expect($result)->toEqual($expected);
});
