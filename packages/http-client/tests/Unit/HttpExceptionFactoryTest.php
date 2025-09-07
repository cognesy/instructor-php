<?php

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\ClientErrorException;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\ServerErrorException;
use Cognesy\Http\Exceptions\TimeoutException;

it('creates client error exception for 4xx status', function () {
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
    
    $exception = HttpExceptionFactory::fromStatusCode(404, $request);
    
    expect($exception)->toBeInstanceOf(ClientErrorException::class);
    expect($exception->getStatusCode())->toBe(404);
    expect($exception->getRequest())->toBe($request);
});

it('creates server error exception for 5xx status', function () {
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
    
    $exception = HttpExceptionFactory::fromStatusCode(500, $request, null, 1.5);
    
    expect($exception)->toBeInstanceOf(ServerErrorException::class);
    expect($exception->getStatusCode())->toBe(500);
    expect($exception->getDuration())->toBe(1.5);
});

it('throws for invalid status code', function () {
    expect(fn() => HttpExceptionFactory::fromStatusCode(200))
        ->toThrow(InvalidArgumentException::class, 'Invalid HTTP status code: 200');
        
    expect(fn() => HttpExceptionFactory::fromStatusCode(399))
        ->toThrow(InvalidArgumentException::class, 'Invalid HTTP status code: 399');
});

it('demonstrates driver-specific exception mapping for connection errors', function () {
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
    $originalException = new Exception('Could not resolve host: api.example.com');
    
    // This shows how drivers would create connection exceptions
    $exception = new ConnectionException('Could not resolve host: api.example.com', $request, null, $originalException);
    
    expect($exception)->toBeInstanceOf(ConnectionException::class);
    expect($exception->getMessage())->toContain('Could not resolve host');
    expect($exception->getPrevious())->toBe($originalException);
    expect($exception->isRetriable())->toBeTrue();
});

it('demonstrates driver-specific exception mapping for timeout errors', function () {
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
    $originalException = new Exception('Operation timed out after 30 seconds');
    
    // This shows how drivers would create timeout exceptions
    $exception = new TimeoutException('Operation timed out after 30 seconds', $request, 30.1, $originalException);
    
    expect($exception)->toBeInstanceOf(TimeoutException::class);
    expect($exception->getDuration())->toBe(30.1);
    expect($exception->getPrevious())->toBe($originalException);
    expect($exception->isRetriable())->toBeTrue();
});

it('demonstrates connection error patterns handled by drivers', function () {
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
    
    $patterns = [
        'connect() failed: Connection refused',
        'Could not resolve host: bad-domain.com',
        'getaddrinfo failed: Name or service not known',
        'Connection refused by server',
    ];
    
    foreach ($patterns as $message) {
        $originalException = new Exception($message);
        // Simulating how drivers would map these patterns
        $exception = new ConnectionException($message, $request, null, $originalException);
        
        expect($exception)->toBeInstanceOf(ConnectionException::class);
        expect($exception->getMessage())->toContain($message);
        expect($exception->isRetriable())->toBeTrue();
    }
});

it('demonstrates timeout error patterns handled by drivers', function () {
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
    
    $patterns = [
        'timeout was reached',
        'Operation timed out',
        'Request timed out after 30 seconds', 
        'Connection timeout after 5 seconds',
    ];
    
    foreach ($patterns as $message) {
        $originalException = new Exception($message);
        // Simulating how drivers would map these patterns
        $exception = new TimeoutException($message, $request, 15.0, $originalException);
        
        expect($exception)->toBeInstanceOf(TimeoutException::class);
        expect($exception->getMessage())->toContain($message);
        expect($exception->isRetriable())->toBeTrue();
    }
});

it('demonstrates fallback to generic network exception', function () {
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
    $originalException = new Exception('Some unknown network error');
    
    // This shows how drivers would create generic network exceptions
    $exception = new NetworkException('Some unknown network error', $request, null, 2.5, $originalException);
    
    expect($exception)->toBeInstanceOf(NetworkException::class);
    expect($exception->getMessage())->toContain('Some unknown network error');
    expect($exception->getDuration())->toBe(2.5);
    expect($exception->getPrevious())->toBe($originalException);
    expect($exception->isRetriable())->toBeTrue();
});

it('shows that factory only handles status codes while drivers handle their own exceptions', function () {
    // HttpExceptionFactory is simplified to only handle HTTP status codes
    // Each driver is responsible for mapping its specific exceptions to our hierarchy
    
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
    
    // Factory handles status codes
    $clientError = HttpExceptionFactory::fromStatusCode(404, $request);
    expect($clientError)->toBeInstanceOf(ClientErrorException::class);
    expect($clientError->isRetriable())->toBeFalse(); // 404 is not retriable
    
    $serverError = HttpExceptionFactory::fromStatusCode(500, $request);
    expect($serverError)->toBeInstanceOf(ServerErrorException::class);
    expect($serverError->isRetriable())->toBeTrue(); // 500 is retriable
    
    // Special case: 429 Too Many Requests is retriable
    $rateLimitError = HttpExceptionFactory::fromStatusCode(429, $request);
    expect($rateLimitError)->toBeInstanceOf(ClientErrorException::class);
    expect($rateLimitError->isRetriable())->toBeTrue(); // 429 is retriable
});