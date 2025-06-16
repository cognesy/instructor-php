<?php

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;
use Cognesy\Http\Middleware\RecordReplay\Exceptions\RecordingNotFoundException;
use Cognesy\Http\Middleware\RecordReplay\RecordReplayMiddleware;

beforeEach(function() {
    $this->testStorageDir = sys_get_temp_dir() . '/http_test_recordings_' . uniqid();
    
    // Create a mock driver for testing
    $this->mockDriver = new MockHttpDriver();
    $this->mockDriver->addResponse(
        MockHttpResponse::success(body: '{"original": true}'),
        'https://api.example.com/test',
        'GET'
    );
    
    $this->request = new HttpRequest(
        'https://api.example.com/test',
        'GET',
        ['Accept' => 'application/json'],
        '',
        []
    );
});

afterEach(function() {
    // Clean up test recordings
    if (is_dir($this->testStorageDir)) {
        $files = glob($this->testStorageDir . '/*.json');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->testStorageDir)) {
            rmdir($this->testStorageDir);
        }
    }
});

test('pass through mode forwards request to next handler', function() {
    // Arrange
    $middleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_PASS,
        $this->testStorageDir
    );
    
    // Act
    $response = $middleware->handle($this->request, $this->mockDriver);
    
    // Assert
    expect($response->body())->toBe('{"original": true}');
    expect($this->mockDriver->getReceivedRequests())->toHaveCount(1);
    expect(is_dir($this->testStorageDir))->toBeFalse(); // Directory not created in pass mode
});

test('record mode saves HTTP interaction', function() {
    // Arrange
    $middleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_RECORD,
        $this->testStorageDir
    );
    
    // Act
    $response = $middleware->handle($this->request, $this->mockDriver);
    
    // Assert
    expect($response->body())->toBe('{"original": true}');
    expect($this->mockDriver->getReceivedRequests())->toHaveCount(1);
    expect(is_dir($this->testStorageDir))->toBeTrue();
    
    // Check that recording exists
    $records = $middleware->getRecords();
    expect($records->count())->toBe(1);
    
    $record = $records->find($this->request);
    expect($record)->not()->toBeNull();
    expect($record->getResponseBody())->toBe('{"original": true}');
});

test('replay mode returns recorded response', function() {
    // Arrange - First record an interaction
    $recordMiddleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_RECORD,
        $this->testStorageDir
    );
    
    $recordMiddleware->handle($this->request, $this->mockDriver);
    
    // Create a different mock driver for verification
    $differentMockDriver = new MockHttpDriver();
    $differentMockDriver->addResponse(
        MockHttpResponse::success(body: '{"different": true}'),
        'https://api.example.com/test',
        'GET'
    );
    
    // Act - Create replay middleware
    $replayMiddleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_REPLAY,
        $this->testStorageDir
    );
    
    $response = $replayMiddleware->handle($this->request, $differentMockDriver);
    
    // Assert
    expect($response->body())->toBe('{"original": true}'); // Should get recorded response
    expect($differentMockDriver->getReceivedRequests())->toBeEmpty(); // Mock driver not called
});

test('replay mode falls back to real request when no recording found', function() {
    // Arrange
    $middleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_REPLAY,
        $this->testStorageDir,
        true // fallback enabled
    );
    
    // Act
    $response = $middleware->handle($this->request, $this->mockDriver);
    
    // Assert
    expect($response->body())->toBe('{"original": true}'); // Got response from mock driver
    expect($this->mockDriver->getReceivedRequests())->toHaveCount(1); // Mock driver was called
});

test('replay mode throws exception when no recording found and fallback disabled', function() {
    // Arrange
    $middleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_REPLAY,
        $this->testStorageDir,
        false // fallback disabled
    );
    
    // Act & Assert
    expect(fn() => $middleware->handle($this->request, $this->mockDriver))
        ->toThrow(RecordingNotFoundException::class);
});

test('changes mode during execution', function() {
    // Arrange
    $middleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_RECORD,
        $this->testStorageDir
    );
    
    // Record an interaction
    $middleware->handle($this->request, $this->mockDriver);
    expect($middleware->getRecords()->count())->toBe(1);
    
    // Reset mock driver
    $this->mockDriver->reset();
    $this->mockDriver->addResponse(
        MockHttpResponse::success(body: '{"new": true}'),
        'https://api.example.com/test',
        'GET'
    );
    
    // Act - Switch to replay mode
    $middleware->setMode(RecordReplayMiddleware::MODE_REPLAY);
    
    // Assert
    expect($middleware->getMode())->toBe(RecordReplayMiddleware::MODE_REPLAY);
    
    // Now we should get recorded response, not the new mock response
    $response = $middleware->handle($this->request, $this->mockDriver);
    expect($response->body())->toBe('{"original": true}');
    expect($this->mockDriver->getReceivedRequests())->toBeEmpty(); // Mock not called
});

test('sets fallback setting', function() {
    // Arrange
    $middleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_REPLAY,
        $this->testStorageDir,
        true // fallback enabled initially
    );
    
    // Act
    $middleware->setFallbackToRealRequests(false);
    
    // Assert that now it throws exception instead of falling back
    expect(fn() => $middleware->handle($this->request, $this->mockDriver))
        ->toThrow(RecordingNotFoundException::class);
});

test('changes storage directory', function() {
    // Arrange
    $middleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_RECORD,
        $this->testStorageDir
    );
    
    // Record to first directory
    $middleware->handle($this->request, $this->mockDriver);
    expect($middleware->getRecords()->count())->toBe(1);
    
    // Act - Change storage directory
    $newDir = sys_get_temp_dir() . '/http_test_recordings_new_' . uniqid();
    $middleware->setStorageDir($newDir);
    
    // Record the same request to new directory
    $middleware->handle($this->request, $this->mockDriver);
    
    // Assert
    expect($middleware->getRecords()->count())->toBe(1); // New directory has 1 recording
    
    // Clean up new directory
    if (is_dir($newDir)) {
        $files = glob($newDir . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($newDir);
    }
});
