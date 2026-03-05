<?php declare(strict_types=1);

namespace Troubleshooting\ErrorHandling;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpClientErrorException;
use Cognesy\Http\Exceptions\ServerErrorException;
use Cognesy\Http\Exceptions\TimeoutException;

final class ApiService
{
    public function fetch(string $url): string
    {
        $client = (new HttpClientBuilder())
            ->withConfig(new HttpClientConfig(failOnError: true))
            ->create();

        $request = new HttpRequest($url, 'GET', ['Accept' => 'application/json'], '', []);

        try {
            return $client->withRequest($request)->content();
        } catch (TimeoutException|ConnectionException $error) {
            return 'Temporary network issue. Retry.';
        } catch (ServerErrorException $error) {
            return 'Upstream service failed.';
        } catch (HttpClientErrorException $error) {
            return 'Request rejected by API.';
        }
    }
}
