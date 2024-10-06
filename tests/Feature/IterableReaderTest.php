<?php

namespace Tests\Feature;

use Cognesy\Instructor\Extras\Http\IterableReader;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\StreamDataReceived;
use Mockery as Mock;

beforeEach(function () {
    // Create a mock for the EventDispatcher
    $this->mockEventDispatcher = Mock::mock(EventDispatcher::class);
    $this->mockEventDispatcher->shouldReceive('dispatch')->with(Mock::type(StreamDataReceived::class));
});

it('streams synthetic OpenAI streaming data correctly without parser', function () {
    $reader = new IterableReader(events: $this->mockEventDispatcher);

    $generator = function () {
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "Hello, ", "index": 0}]}'. "\n";
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "world!", "index": 1}]}' . "\n";
    };

    $expected = [
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "Hello, ", "index": 0}]}',
        '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{"text": "world!", "index": 1}]}',
    ];

    $result = iterator_to_array($reader->stream($generator()));
    expect($result)->toEqual($expected);
});

it('processes synthetic OpenAI streaming data with a custom parser', function () {
    $parser = fn($line) => strtoupper($line);
    $reader = new IterableReader(parser: $parser, events: $this->mockEventDispatcher);

    $generator = function () {
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "Hello, ", "index": 0}]}' . "\n";
        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
        yield '"text": "world!", "index": 1}]}' . "\n";
    };

    $expected = [
        '{"ID": "CMPL-XYZ", "OBJECT": "TEXT_COMPLETION", "CHOICES": [{"TEXT": "HELLO, ", "INDEX": 0}]}',
        '{"ID": "CMPL-XYZ", "OBJECT": "TEXT_COMPLETION", "CHOICES": [{"TEXT": "WORLD!", "INDEX": 1}]}',
    ];

    $result = iterator_to_array($reader->stream($generator()));
    expect($result)->toEqual($expected);
});

//it('dispatches events when streaming synthetic OpenAI data', function () {
//    $this->mockEventDispatcher->shouldReceive('dispatch')->times(2)->with(Mock::type(StreamDataReceived::class));
//
//    $reader = new IterableReader(events: $this->mockEventDispatcher);
//
//    $generator = function () {
//        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
//        yield '"text": "Hello, ", "index": 0}]}' . "\n";
//        yield '{"id": "cmpl-xyz", "object": "text_completion", "choices": [{';
//        yield '"text": "world!", "index": 1}]}' . "\n";
//    };
//
//    // Convert to array to force full iteration and event dispatching
//    iterator_to_array($reader->stream($generator()));
//});

it('skips empty lines correctly in synthetic OpenAI data', function () {
    $reader = new IterableReader(events: $this->mockEventDispatcher);

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

    $result = iterator_to_array($reader->stream($generator()));
    expect($result)->toEqual($expected);
});

it('handles incomplete lines correctly in synthetic OpenAI data', function () {
    $reader = new IterableReader(events: $this->mockEventDispatcher);

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

    $result = iterator_to_array($reader->stream($generator()));
    expect($result)->toEqual($expected);
});