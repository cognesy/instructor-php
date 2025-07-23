<?php declare(strict_types=1);

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use GuzzleHttp\Exception\RequestException;

function retryRequest($client, $request, $maxRetries = 3): HttpResponse {
    $attempts = 0;
    $lastException = null;

    $shouldRetry = function ($exception) {
        // Only retry on connection issues and certain status codes
        $retryStatusCodes = [429, 500, 502, 503, 504];

        if ($exception instanceof RequestException) {
            $previous = $exception->getPrevious();

            // Retry on connection errors
            if ($previous instanceof \GuzzleHttp\Exception\ConnectException ||
                $previous instanceof \Symfony\Component\HttpClient\Exception\TransportException) {
                return true;
            }

            // Check for specific HTTP status codes
            $response = $exception->getResponse();
            if ($response && in_array($response->getStatusCode(), $retryStatusCodes)) {
                return true;
            }
        }

        return false;
    };

    while ($attempts < $maxRetries) {
        try {
            return $client->withRequest($request)->get();
        } catch (RequestException $e) {
            $lastException = $e;
            $attempts++;

            if (!$shouldRetry($e) || $attempts >= $maxRetries) {
                throw $e;
            }

            // Exponential backoff with jitter
            $sleepTime = (2 ** $attempts) + random_int(0, 1000) / 1000;
            sleep($sleepTime);

            error_log("Retry attempt $attempts after error: {$e->getMessage()}");
        }
    }

    throw $lastException; // Should never reach here, but just in case
}

$client = HttpClient::default();
$request = new HttpRequest(url: 'https://example.com/api/resource', method: 'GET', headers: [], body: '', options: []);
$response = retryRequest($client, $request);
assert($response->statusCode() === 200, 'Expected status code 200');