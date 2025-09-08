<?php declare(strict_types=1);

namespace Tests\Unit;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpClientErrorException;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\ServerErrorException;
use Cognesy\Http\Exceptions\TimeoutException;
use PHPUnit\Framework\TestCase;

class ExceptionHierarchyTest extends TestCase
{
    public function test_enhanced_http_request_exception_stores_all_context()
    {
        $request = new HttpRequest('https://api.example.com/users', 'GET', [], '', []);
        $exception = new HttpRequestException(
            'Test error',
            $request,
            null,
            1.5
        );

        expect($exception->getRequest())->toBe($request);
        expect($exception->getResponse())->toBeNull();
        expect($exception->getDuration())->toBe(1.5);
        expect($exception->isRetriable())->toBeFalse();
        expect($exception->getStatusCode())->toBeNull();
    }

    public function test_network_exception_is_retriable_by_default()
    {
        $exception = new NetworkException('Network error');

        expect($exception->isRetriable())->toBeTrue();
        expect($exception)->toBeInstanceOf(HttpRequestException::class);
    }

    public function test_connection_exception_extends_network_exception()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        $exception = new ConnectionException('Connection refused', $request);

        expect($exception->isRetriable())->toBeTrue();
        expect($exception)->toBeInstanceOf(NetworkException::class);
        expect($exception)->toBeInstanceOf(HttpRequestException::class);
        expect($exception->getRequest())->toBe($request);
    }

    public function test_timeout_exception_extends_network_exception()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        $exception = new TimeoutException('Request timeout', $request, 30.0);

        expect($exception->isRetriable())->toBeTrue();
        expect($exception)->toBeInstanceOf(NetworkException::class);
        expect($exception)->toBeInstanceOf(HttpRequestException::class);
        expect($exception->getDuration())->toBe(30.0);
    }

    public function test_client_error_exception_retry_logic()
    {
        // 429 Too Many Requests should be retriable
        $exception429 = new HttpClientErrorException(429);
        expect($exception429->isRetriable())->toBeTrue();
        expect($exception429->getStatusCode())->toBe(429);
        
        // Other 4xx errors should not be retriable
        $exception404 = new HttpClientErrorException(404);
        expect($exception404->isRetriable())->toBeFalse();
        expect($exception404->getStatusCode())->toBe(404);
        
        $exception401 = new HttpClientErrorException(401);
        expect($exception401->isRetriable())->toBeFalse();
    }

    public function test_server_error_exception_always_retriable()
    {
        $exception500 = new ServerErrorException(500);
        expect($exception500->isRetriable())->toBeTrue();
        expect($exception500->getStatusCode())->toBe(500);
        
        $exception503 = new ServerErrorException(503);
        expect($exception503->isRetriable())->toBeTrue();
        expect($exception503->getStatusCode())->toBe(503);
    }

    public function test_all_exceptions_extend_base_http_request_exception()
    {
        $exceptions = [
            new NetworkException('Network error'),
            new ConnectionException('Connection error'),
            new TimeoutException('Timeout error'),
            new HttpClientErrorException(404),
            new ServerErrorException(500),
        ];

        foreach ($exceptions as $exception) {
            expect($exception)->toBeInstanceOf(HttpRequestException::class);
        }
    }

    public function test_message_formatting_includes_context()
    {
        $request = new HttpRequest('https://api.example.com/users', 'GET', [], '', []);
        $exception = new HttpRequestException(
            'API call failed',
            $request,
            null,
            2.3
        );

        $message = $exception->getMessage();
        expect($message)->toContain('API call failed');
        expect($message)->toContain('GET https://api.example.com/users');
        expect($message)->toContain('Duration: 2.30s');
    }
}