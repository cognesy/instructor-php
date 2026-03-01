<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Errors\ProviderErrorClassifier;
use Cognesy\Polyglot\Inference\Exceptions\ProviderTransientException;

it('classifies HTTP 408 response as retriable transient provider error', function () {
    $response = HttpResponse::sync(
        statusCode: 408,
        headers: ['Content-Type' => 'application/json'],
        body: '{"error":{"message":"Request timed out"}}',
    );

    $error = ProviderErrorClassifier::fromHttpResponse($response);

    expect($error)->toBeInstanceOf(ProviderTransientException::class)
        ->and($error->statusCode)->toBe(408)
        ->and($error->isRetriable())->toBeTrue();
});

it('classifies HTTP 408 request exception as retriable transient provider error', function () {
    $request = new HttpRequest(
        url: 'https://api.example.com/v1/chat/completions',
        method: 'POST',
        headers: ['Content-Type' => 'application/json'],
        body: [],
        options: [],
    );
    $response = HttpResponse::sync(
        statusCode: 408,
        headers: ['Content-Type' => 'application/json'],
        body: '{"error":{"message":"Request timed out"}}',
    );
    $httpError = new HttpRequestException(
        message: 'Request timed out',
        request: $request,
        response: $response,
    );

    $error = ProviderErrorClassifier::fromHttpException($httpError);

    expect($error)->toBeInstanceOf(ProviderTransientException::class)
        ->and($error->statusCode)->toBe(408)
        ->and($error->isRetriable())->toBeTrue();
});

it('retry policy retries provider errors classified from HTTP 408', function () {
    $response = HttpResponse::sync(
        statusCode: 408,
        headers: ['Content-Type' => 'application/json'],
        body: '{"error":{"message":"Request timed out"}}',
    );
    $providerError = ProviderErrorClassifier::fromHttpResponse($response);
    $policy = new InferenceRetryPolicy(maxAttempts: 3);

    expect($policy->shouldRetryException($providerError))->toBeTrue();
});
