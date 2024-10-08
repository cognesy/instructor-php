<?php

namespace Cognesy\Instructor\Features\Http\Data;

use Cognesy\Instructor\Features\Http\Enums\HttpClientType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class HttpClientConfig
{
    public function __construct(
        public HttpClientType $httpClientType = HttpClientType::Guzzle,
        public int $connectTimeout = 3,
        public int $requestTimeout = 30,
        public int $idleTimeout = -1,
    ) {}

    public static function load(string $client) : HttpClientConfig {
        if (!Settings::has('http', "clients.$client")) {
            throw new InvalidArgumentException("Unknown client: $client");
        }
        return new HttpClientConfig(
            httpClientType: HttpClientType::from(Settings::get('http', "clients.$client.httpClientType")),
            connectTimeout: Settings::get(group: "http", key: "clients.$client.connectTimeout", default: 30),
            requestTimeout: Settings::get("http", "clients.$client.requestTimeout", 3),
            idleTimeout: Settings::get(group: "http", key: "clients.$client.idleTimeout", default: 0),
        );
    }
}
