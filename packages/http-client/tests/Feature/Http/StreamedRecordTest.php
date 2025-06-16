<?php

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;
use Cognesy\Http\Middleware\RecordReplay\StreamedRequestRecord;

test('creates from streamed HTTP interaction', function() {
    // Arrange
    $request = new HttpRequest(
        'https://api.example.com/stream',
        'GET',
        ['Accept' => 'application/json'],
        '',
        ['stream' => true]
    );
    
    $chunks = ['{"part1":', '"value1",', '"part2":"value2"}'];
    $response = MockHttpResponse::streaming(chunks: $chunks);
    
    // Act
    $record = StreamedRequestRecord::fromStreamedInteraction($request, $response);
    
    // Assert
    expect($record)->toBeInstanceOf(StreamedRequestRecord::class);
    expect($record->getUrl())->toBe('https://api.example.com/stream');
    expect($record->getMethod())->toBe('GET');
    expect($record->getResponseBody())->toBe('{"part1":"value1","part2":"value2"}');
    expect($record->getChunks())->toBe($chunks);
    expect($record->getChunkCount())->toBe(3);
    expect($record->hasChunks())->toBeTrue();
});

test('converts streamed record to and from JSON', function() {
    // Arrange
    $request = new HttpRequest(
        'https://api.example.com/stream',
        'GET',
        ['Accept' => 'application/json'],
        '',
        ['stream' => true]
    );
    
    $chunks = ['{"part1":', '"value1",', '"part2":"value2"}'];
    $response = MockHttpResponse::streaming(chunks: $chunks);
    
    // Act
    $original = StreamedRequestRecord::fromStreamedInteraction($request, $response);
    $json = $original->toJson();
    $recreated = StreamedRequestRecord::fromJson($json);
    
    // Assert
    expect($recreated)->not()->toBeNull();
    expect($recreated)->toBeInstanceOf(StreamedRequestRecord::class);
    expect($recreated->getChunks())->toBe($chunks);
    expect($recreated->getResponseBody())->toBe('{"part1":"value1","part2":"value2"}');
});

test('creates streaming response from record', function() {
    // Arrange
    $request = new HttpRequest(
        'https://api.example.com/stream',
        'GET',
        [],
        '',
        ['stream' => true]
    );
    
    $chunks = ['{"part1":', '"value1",', '"part2":"value2"}'];
    $originalResponse = MockHttpResponse::streaming(chunks: $chunks);
    $record = StreamedRequestRecord::fromStreamedInteraction($request, $originalResponse);
    
    // Act - Get a streaming response
    $streamingResponse = $record->toResponse(true);
    
    // Collect chunks from the stream
    $receivedChunks = [];
    foreach ($streamingResponse->stream() as $chunk) {
        $receivedChunks[] = $chunk;
    }
    
    // Assert chunks match
    expect($receivedChunks)->toBe($chunks);
    
    // Act - Get a non-streaming response
    $nonStreamingResponse = $record->toResponse(false);
    
    // Assert full body is available
    expect($nonStreamingResponse->body())->toBe('{"part1":"value1","part2":"value2"}');
});

test('factory method creates correct record type', function() {
    // Arrange
    $streamRequest = new HttpRequest(
        'https://api.example.com/stream',
        'GET',
        [],
        '',
        ['stream' => true]
    );
    
    $normalRequest = new HttpRequest(
        'https://api.example.com/users',
        'GET',
        [],
        '',
        []
    );
    
    $chunks = ['{"id":', '123', '}'];
    $streamResponse = MockHttpResponse::streaming(chunks: $chunks);
    $normalResponse = MockHttpResponse::success(body: '{"id":123}');
    
    // Act
    $streamRecord = StreamedRequestRecord::createAppropriateRecord($streamRequest, $streamResponse);
    $normalRecord = StreamedRequestRecord::createAppropriateRecord($normalRequest, $normalResponse);
    
    // Assert
    expect($streamRecord)->toBeInstanceOf(StreamedRequestRecord::class);
    expect($normalRecord)->not()->toBeInstanceOf(StreamedRequestRecord::class);
});
