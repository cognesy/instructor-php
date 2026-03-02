<?php

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Middleware\RecordReplay\RequestRecords;

beforeEach(function() {
    $this->testStorageDir = sys_get_temp_dir() . '/http_test_records_' . uniqid();
    $this->records = new RequestRecords($this->testStorageDir);
});

afterEach(function() {
    // Clean up test recordings
    if (is_dir($this->testStorageDir)) {
        $files = glob($this->testStorageDir . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->testStorageDir);
    }
});

test('saves a recorded HTTP interaction', function() {
    // Arrange
    $request = new HttpRequest(
        'https://api.example.com/users',
        'GET',
        ['Accept' => 'application/json'],
        '',
        []
    );
    
    $response = MockHttpResponseFactory::success(body: '{"id":123}');
    
    // Act
    $filename = $this->records->save($request, $response);
    
    // Assert
    expect(is_file($filename))->toBeTrue();
    expect($this->records->count())->toBe(1);
    
    // Verify file contents
    $json = file_get_contents($filename);
    $data = json_decode($json, true);
    
    expect($data)->toBeArray();
    expect($data)->toHaveKey('request');
    expect($data)->toHaveKey('response');
    expect($data['request']['url'])->toBe('https://api.example.com/users');
    expect($data['response']['body'])->toBe('{"id":123}');
});

test('finds a recorded HTTP interaction', function() {
    // Arrange
    $request = new HttpRequest(
        'https://api.example.com/users',
        'GET',
        ['Accept' => 'application/json'],
        '',
        []
    );
    
    $response = MockHttpResponseFactory::success(body: '{"id":123}');
    
    // Save the recording
    $this->records->save($request, $response);
    
    // Act
    $record = $this->records->find($request);
    
    // Assert
    expect($record)->not()->toBeNull();
    expect($record->getUrl())->toBe('https://api.example.com/users');
    expect($record->getResponseBody())->toBe('{"id":123}');
});

test('returns null when no recording found', function() {
    // Arrange
    $request = new HttpRequest(
        'https://api.example.com/nonexistent',
        'GET',
        [],
        '',
        []
    );
    
    // Act
    $record = $this->records->find($request);
    
    // Assert
    expect($record)->toBeNull();
});

test('deletes a recorded HTTP interaction', function() {
    // Arrange
    $request = new HttpRequest(
        'https://api.example.com/users',
        'GET',
        [],
        '',
        []
    );
    
    $response = MockHttpResponseFactory::success(body: '{"id":123}');
    
    // Save the recording
    $this->records->save($request, $response);
    expect($this->records->count())->toBe(1);
    
    // Act
    $result = $this->records->delete($request);
    
    // Assert
    expect($result)->toBeTrue();
    expect($this->records->count())->toBe(0);
});

test('clears all recordings', function() {
    // Arrange
    $requests = [
        new HttpRequest('https://api.example.com/users', 'GET', [], '', []),
        new HttpRequest('https://api.example.com/posts', 'GET', [], '', []),
        new HttpRequest('https://api.example.com/comments', 'GET', [], '', [])
    ];
    
    $response = MockHttpResponseFactory::success(body: '{"result": true}');
    
    // Save multiple recordings
    foreach ($requests as $request) {
        $this->records->save($request, $response);
    }
    
    expect($this->records->count())->toBe(3);
    
    // Act
    $deleted = $this->records->clear();
    
    // Assert
    expect($deleted)->toBe(3);
    expect($this->records->count())->toBe(0);
});

test('retrieves all recordings', function() {
    // Arrange
    $requests = [
        new HttpRequest('https://api.example.com/users', 'GET', [], '', []),
        new HttpRequest('https://api.example.com/posts', 'GET', [], '', []),
        new HttpRequest('https://api.example.com/comments', 'GET', [], '', [])
    ];
    
    $response = MockHttpResponseFactory::success(body: '{"result": true}');
    
    // Save multiple recordings
    foreach ($requests as $request) {
        $this->records->save($request, $response);
    }
    
    // Act
    $allRecords = $this->records->all();
    
    // Assert
    expect($allRecords)->toHaveCount(3);
    
    // Check that we can access properties on all records
    foreach ($allRecords as $record) {
        expect($record->getResponseBody())->toBe('{"result": true}');
    }
});

test('changes storage directory', function() {
    // Arrange
    $request = new HttpRequest(
        'https://api.example.com/users',
        'GET',
        [],
        '',
        []
    );
    
    $response = MockHttpResponseFactory::success(body: '{"id":123}');
    
    // Save a recording in the original directory
    $this->records->save($request, $response);
    expect($this->records->count())->toBe(1);
    
    // Act
    $newDir = sys_get_temp_dir() . '/http_test_records_new_' . uniqid();
    $this->records->setStorageDir($newDir);
    
    // Assert
    expect($this->records->getStorageDir())->toBe($newDir);
    expect($this->records->count())->toBe(0); // New directory is empty
    
    // Clean up the new directory
    if (is_dir($newDir)) {
        rmdir($newDir);
    }
});

test('throws when recording cannot be written to storage', function() {
    $request = new HttpRequest(
        'https://api.example.com/users',
        'GET',
        ['Accept' => 'application/json'],
        '',
        []
    );

    $response = MockHttpResponseFactory::success(body: '{"id":123}');
    $readOnlyDir = sys_get_temp_dir() . '/http_test_records_ro_' . uniqid();
    mkdir($readOnlyDir, 0777, true);
    chmod($readOnlyDir, 0555);
    $this->records->setStorageDir($readOnlyDir);

    try {
        expect(fn() => $this->records->save($request, $response))
            ->toThrow(RuntimeException::class);
    } finally {
        chmod($readOnlyDir, 0777);
        rmdir($readOnlyDir);
    }
});
