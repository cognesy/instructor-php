<?php

declare(strict_types=1);

use Cognesy\Http\Drivers\Curl\HeaderParser;

it('keeps only the headers from the latest response block', function () {
    $parser = new HeaderParser;

    foreach ([
        "HTTP/1.1 302 Found\r\n",
        "Location: https://intermediate.example.test\r\n",
        "Set-Cookie: hop=one\r\n",
        "X-Hop: intermediate\r\n",
        "\r\n",
        "HTTP/1.1 200 OK\r\n",
        "Content-Type: application/json\r\n",
        "Set-Cookie: final=one\r\n",
        "Set-Cookie: final=two\r\n",
        "\r\n",
    ] as $line) {
        $parser->parse($line);
    }

    expect($parser->statusCode())->toBe(200)
        ->and($parser->headers())->toBe([
            'Content-Type' => ['application/json'],
            'Set-Cookie' => ['final=one', 'final=two'],
        ]);
});

it('preserves normal repeated headers within one response block', function () {
    $parser = new HeaderParser;
    $parser->parse("HTTP/1.1 200 OK\r\n");
    $parser->parse("Warning: one\r\n");
    $parser->parse("Warning: two\r\n");

    expect($parser->headers())->toBe(['Warning' => ['one', 'two']]);
});

it('waits for the final response after informational status blocks', function () {
    $parser = new HeaderParser;
    $parser->parse("HTTP/1.1 101 Switching Protocols\r\n");
    $parser->parse("Connection: Upgrade\r\n");
    $parser->parse("Upgrade: h2c\r\n");
    $parser->parse("\r\n");

    expect($parser->statusCode())->toBe(0)
        ->and($parser->headers())->toBe([]);

    $parser->parse("HTTP/2 200 OK\r\n");
    $parser->parse("Content-Type: application/json\r\n");

    expect($parser->statusCode())->toBe(200)
        ->and($parser->headers())->toBe(['Content-Type' => ['application/json']]);
});
