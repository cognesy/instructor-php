<?php

namespace Cognesy\Instructor\Extras\Http;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Extras\Http\Drivers\GuzzleDriver;
use Cognesy\Instructor\Extras\Http\Drivers\SymfonyDriver;
use Cognesy\Instructor\Extras\Http\Enums\HttpClientType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class HttpClient
{
    protected EventDispatcher $events;
    protected CanHandleHttp $driver;

    public function __construct(string $client = '', EventDispatcher $events = null) {
        $this->events = $events ?? new EventDispatcher();
        $config = HttpClientConfig::load($client ?: Settings::get('http', "defaultClient"));
        $this->driver = $this->makeDriver($config);
    }

    public static function make(string $client = '', ?EventDispatcher $events = null) : CanHandleHttp {
        return (new self($client, $events))->get();
    }

    public function withClient(string $name) : self {
        $config = HttpClientConfig::load($name);
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    public function withConfig(HttpClientConfig $config) : self {
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    public function withDriver(CanHandleHttp $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    public function get() : CanHandleHttp {
        return $this->driver;
    }

    private function makeDriver(HttpClientConfig $config) : CanHandleHttp {
        return match ($config->httpClientType) {
            HttpClientType::Guzzle => new GuzzleDriver(config: $config, events: $this->events),
            HttpClientType::Symfony => new SymfonyDriver(config: $config, events: $this->events),
            default => throw new InvalidArgumentException("Client not supported: {$config->httpClientType->value}"),
        };
    }
}