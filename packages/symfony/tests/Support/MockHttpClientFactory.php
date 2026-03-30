<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MockHttpClientFactory
{
    public static function withResponse(string $content, int $statusCode = 200): MockHttpClient
    {
        return new MockHttpClient([
            new MockResponse($content, ['http_code' => $statusCode]),
        ]);
    }
}
