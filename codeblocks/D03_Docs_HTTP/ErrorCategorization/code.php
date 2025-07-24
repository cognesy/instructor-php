<?php declare(strict_types=1);

try {
    $response = $client->withRequest($request)->get();

    // Check for error responses
    if ($response->statusCode() >= 400) {
        $this->handleErrorResponse($response);
        return;
    }

    // Process successful response
    $this->processResponse($response);

} catch (RequestException $e) {
    $previous = $e->getPrevious();

    if ($previous instanceof \GuzzleHttp\Exception\ConnectException ||
        $previous instanceof \Symfony\Component\HttpClient\Exception\TransportException) {
        // Handle connection errors
        $this->handleConnectionError($e);
    } elseif ($previous instanceof \GuzzleHttp\Exception\RequestException ||
              $previous instanceof \Symfony\Component\HttpClient\Exception\HttpExceptionInterface) {
        // Handle HTTP protocol errors
        $this->handleHttpError($e);
    } else {
        // Handle other exceptions
        $this->handleUnexpectedError($e);
    }
}
