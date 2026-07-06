<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

final class CapturingGuzzleClient implements ClientInterface
{
    /** @var array<string,mixed> */
    public array $options = [];

    public function send(RequestInterface $request, array $options = []): ResponseInterface {
        $this->options = $options;
        return new Response(200, [], '{}');
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface {
        return Create::promiseFor($this->send($request, $options));
    }

    public function request(string $method, $uri, array $options = []): ResponseInterface {
        return $this->send(new Request($method, $uri), $options);
    }

    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface {
        return Create::promiseFor($this->request($method, $uri, $options));
    }

    public function getConfig(?string $option = null): mixed {
        return null;
    }
}

it('passes shared transport options to guzzle requests', function () {
    $client = new CapturingGuzzleClient();
    $driver = new GuzzleDriver(
        config: new HttpClientConfig(
            driver: 'guzzle',
            verifyTls: false,
            followRedirects: true,
            maxRedirects: 2,
            httpVersion: '1.1',
        ),
        events: new EventDispatcher(),
        clientInstance: $client,
    );

    $driver->handle(new HttpRequest(
        url: 'https://example.test',
        method: 'GET',
        headers: [],
        body: '',
        options: [],
    ));

    expect($client->options['verify'])->toBeFalse()
        ->and($client->options['allow_redirects'])->toBe(['max' => 2])
        ->and($client->options['version'])->toBe('1.1');
});
