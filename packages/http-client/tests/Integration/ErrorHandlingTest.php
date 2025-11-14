<?php declare(strict_types=1);

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Laravel\LaravelDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Exceptions\HttpClientErrorException;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\ServerErrorException;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ErrorHandlingTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = IntegrationTestServer::start();
    }

    protected function tearDown(): void
    {
        // Server cleanup handled by IntegrationTestServer
        parent::tearDown();
    }

    public static function driverProvider(): array
    {
        return [
            ['curl'],
            ['guzzle'],
            ['laravel'], 
            ['symfony'],
        ];
    }

    private function createDriver(string $type): object
    {
        $config = new HttpClientConfig(failOnError: true);
        $events = new EventDispatcher();
        
        return match($type) {
            'curl' => new CurlDriver($config, $events),
            'guzzle' => new GuzzleDriver($config, $events),
            'laravel' => new LaravelDriver($config, $events),
            'symfony' => new SymfonyDriver($config, $events),
            default => throw new \InvalidArgumentException("Unknown driver type: {$type}"),
        };
    }

    /**
     * @dataProvider driverProvider
     */
    public function test_all_drivers_throw_consistent_client_error_exceptions(string $driverType)
    {
        $driver = $this->createDriver($driverType);
        $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);
        
        try {
            $driver->handle($request);
            $this->fail('Expected exception to be thrown');
        } catch (HttpClientErrorException $e) {
            expect($e->getStatusCode())->toBe(404);
            expect($e->isRetriable())->toBeFalse();
            expect($e->getRequest())->toBe($request);
            // Duration is no longer asserted; drivers no longer track it
        }
    }

    /**
     * @dataProvider driverProvider
     */
    public function test_all_drivers_throw_consistent_server_error_exceptions(string $driverType)
    {
        $driver = $this->createDriver($driverType);
        $request = new HttpRequest($this->baseUrl . '/status/500', 'GET', [], '', []);
        
        try {
            $driver->handle($request);
            $this->fail('Expected exception to be thrown');
        } catch (ServerErrorException $e) {
            expect($e->getStatusCode())->toBe(500);
            expect($e->isRetriable())->toBeTrue();
            expect($e->getRequest())->toBe($request);
            // Duration is no longer asserted; drivers no longer track it
        }
    }

    /**
     * @dataProvider driverProvider
     */
    public function test_429_rate_limit_error_is_retriable(string $driverType)
    {
        $driver = $this->createDriver($driverType);
        $request = new HttpRequest($this->baseUrl . '/status/429', 'GET', [], '', []);
        
        try {
            $driver->handle($request);
            $this->fail('Expected exception to be thrown');
        } catch (HttpClientErrorException $e) {
            expect($e->getStatusCode())->toBe(429);
            expect($e->isRetriable())->toBeTrue(); // 429 is the only retriable 4xx
        }
    }

    /**
     * @dataProvider driverProvider
     */
    public function test_drivers_do_not_throw_when_fail_on_error_disabled(string $driverType)
    {
        $config = new HttpClientConfig(failOnError: false);
        $events = new EventDispatcher();
        
        $driver = match($driverType) {
            'curl' => new CurlDriver($config, $events),
            'guzzle' => new GuzzleDriver($config, $events),
            'laravel' => new LaravelDriver($config, $events),
            'symfony' => new SymfonyDriver($config, $events),
        };
        
        $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);
        
        // Should return response instead of throwing
        $response = $driver->handle($request);
        expect($response->statusCode())->toBe(404);
    }

    public function test_network_error_handling_with_invalid_host()
    {
        $driver = $this->createDriver('guzzle');
        $request = new HttpRequest('https://invalid-host-that-does-not-exist.com', 'GET', [], '', []);
        
        try {
            $driver->handle($request);
            $this->fail('Expected exception to be thrown');
        } catch (NetworkException $e) {
            expect($e->isRetriable())->toBeTrue();
            expect($e->getRequest())->toBe($request);
            // Should be a more specific exception type
            expect($e)->toBeInstanceOf(NetworkException::class);
        }
    }

    public function test_backward_compatibility_catch_all_exceptions()
    {
        $driver = $this->createDriver('guzzle');
        $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);
        
        try {
            $driver->handle($request);
            $this->fail('Expected exception to be thrown');
        } catch (HttpRequestException $e) {
            // All new exceptions should be catchable as HttpRequestException
            expect($e->getRequest())->toBe($request);
            // Duration is no longer asserted; drivers no longer track it
        }
    }

    public function test_http_client_integration_with_new_exceptions()
    {
        $config = new HttpClientConfig(failOnError: true);
        $driver = $this->createDriver('guzzle');
        $client = new HttpClient($driver);
        
        try {
            $response = $client->withRequest(new HttpRequest($this->baseUrl . '/status/422', 'GET', [], '', []))->get();
            $this->fail('Expected exception to be thrown');
        } catch (HttpClientErrorException $e) {
            expect($e->getStatusCode())->toBe(422);
            expect($e->isRetriable())->toBeFalse();
        }
    }

    public function test_exception_message_formatting()
    {
        $driver = $this->createDriver('guzzle');
        $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);
        
        try {
            $driver->handle($request);
        } catch (HttpClientErrorException $e) {
            $message = $e->getMessage();
            expect($message)->toContain('HTTP 404 Client Error');
            expect($message)->toContain('GET ' . $this->baseUrl . '/status/404');
            expect($message)->toContain('Status: 404');
            // Drivers no longer include duration in messages
        }
    }

    public function test_exception_provides_full_context()
    {
        $driver = $this->createDriver('guzzle');
        $request = new HttpRequest($this->baseUrl . '/status/500', 'GET', [], '', []);
        
        try {
            $driver->handle($request);
        } catch (ServerErrorException $e) {
            // Test all context methods
            expect($e->getRequest())->toBe($request);
            expect($e->getResponse())->not->toBeNull();
            expect($e->getStatusCode())->toBe(500);
            // Duration is optional; do not assert on it
            expect($e->isRetriable())->toBeTrue();
        }
    }
}
