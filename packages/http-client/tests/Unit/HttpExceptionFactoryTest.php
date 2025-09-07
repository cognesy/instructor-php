<?php declare(strict_types=1);

namespace Tests\Unit;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\ClientErrorException;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\ServerErrorException;
use Cognesy\Http\Exceptions\TimeoutException;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HttpExceptionFactoryTest extends TestCase
{
    public function test_factory_creates_client_error_exception_for_4xx_status()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        
        $exception = HttpExceptionFactory::fromStatusCode(404, $request);
        
        expect($exception)->toBeInstanceOf(ClientErrorException::class);
        expect($exception->getStatusCode())->toBe(404);
        expect($exception->getRequest())->toBe($request);
    }

    public function test_factory_creates_server_error_exception_for_5xx_status()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        
        $exception = HttpExceptionFactory::fromStatusCode(500, $request, null, 1.5);
        
        expect($exception)->toBeInstanceOf(ServerErrorException::class);
        expect($exception->getStatusCode())->toBe(500);
        expect($exception->getDuration())->toBe(1.5);
    }

    public function test_factory_throws_for_invalid_status_code()
    {
        expect(fn() => HttpExceptionFactory::fromStatusCode(200))
            ->toThrow(InvalidArgumentException::class, 'Invalid HTTP status code: 200');
            
        expect(fn() => HttpExceptionFactory::fromStatusCode(399))
            ->toThrow(InvalidArgumentException::class, 'Invalid HTTP status code: 399');
    }

    public function test_factory_detects_connection_errors()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        $driverException = new Exception('Could not resolve host: api.example.com');
        
        $exception = HttpExceptionFactory::fromDriverException($driverException, $request);
        
        expect($exception)->toBeInstanceOf(ConnectionException::class);
        expect($exception->getMessage())->toContain('Could not resolve host');
        expect($exception->getPrevious())->toBe($driverException);
    }

    public function test_factory_detects_timeout_errors()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        $driverException = new Exception('Operation timed out after 30 seconds');
        
        $exception = HttpExceptionFactory::fromDriverException($driverException, $request, 30.1);
        
        expect($exception)->toBeInstanceOf(TimeoutException::class);
        expect($exception->getDuration())->toBe(30.1);
        expect($exception->getPrevious())->toBe($driverException);
    }

    public function test_factory_connection_error_patterns()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        
        $patterns = [
            'connect() failed: Connection refused',
            'Could not resolve host: bad-domain.com',
            'getaddrinfo failed: Name or service not known',
            'Connection refused by server',
        ];
        
        foreach ($patterns as $message) {
            $driverException = new Exception($message);
            $exception = HttpExceptionFactory::fromDriverException($driverException, $request);
            
            expect($exception)->toBeInstanceOf(ConnectionException::class, 
                "Pattern '{$message}' should create ConnectionException");
        }
    }

    public function test_factory_timeout_error_patterns()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        
        $patterns = [
            'timeout was reached',
            'Operation timed out',
            'Request timed out after 30 seconds',
            'Connection timeout after 5 seconds',
        ];
        
        foreach ($patterns as $message) {
            $driverException = new Exception($message);
            $exception = HttpExceptionFactory::fromDriverException($driverException, $request);
            
            expect($exception)->toBeInstanceOf(TimeoutException::class,
                "Pattern '{$message}' should create TimeoutException");
        }
    }

    public function test_factory_fallback_to_generic_network_exception()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        $driverException = new Exception('Some unknown network error');
        
        $exception = HttpExceptionFactory::fromDriverException($driverException, $request, 2.5);
        
        expect($exception)->toBeInstanceOf(NetworkException::class);
        expect($exception->getMessage())->toContain('Some unknown network error');
        expect($exception->getDuration())->toBe(2.5);
        expect($exception->getPrevious())->toBe($driverException);
    }

    public function test_factory_handles_guzzle_specific_exceptions()
    {
        $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);
        
        // Test with mock Guzzle ConnectException (if available)
        if (class_exists('\\GuzzleHttp\\Exception\\ConnectException')) {
            // We'll test the class name detection since we can't easily create Guzzle exceptions
            $driverException = new Exception('GuzzleHttp\\Exception\\ConnectException: Connection refused');
            $exception = HttpExceptionFactory::fromDriverException($driverException, $request);
            expect($exception)->toBeInstanceOf(NetworkException::class); // Falls back to generic
        } else {
            // Test string-based detection
            $driverException = new Exception('connect() failed');
            $exception = HttpExceptionFactory::fromDriverException($driverException, $request);
            expect($exception)->toBeInstanceOf(ConnectionException::class);
        }
    }
}