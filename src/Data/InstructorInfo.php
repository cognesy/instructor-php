<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\EventDispatcher;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Not used - may be removed in the future.')]
class InstructorInfo
{
    public EventDispatcher $events;
    public Configuration $config;
    public CanCallApi $client;
    public bool $stopOnDebug;
    public bool $debug;
    public bool $cache;

    // STATIC ENTRY POINTS ////////////////////////////////////////////////////////////

    public static function new() : static {
        return new static();
    }

    public static function with(
        EventDispatcher $eventDispatcher = null,
        Configuration $config = null,
        CanCallApi $client = null,
        bool $debug = null,
        bool $stopOnDebug = null,
        bool $cache = null,
    ) : static {
        $data = new static();
        $data->events = $eventDispatcher;
        $data->config = $config;
        $data->client = $client;
        $data->debug = $debug;
        $data->cache = $cache;
        $data->stopOnDebug = $stopOnDebug;
        return $data;
    }

    // SETTERS ////////////////////////////////////////////////////////////////////////

    public function withConfig(Configuration $config) : static {
        $this->config = $config;
        return $this;
    }

    public function withEventDispatcher(EventDispatcher $eventDispatcher) : static {
        $this->events = $eventDispatcher;
        return $this;
    }

    public function withClient(CanCallApi $client) : static {
        $this->client = $client;
        return $this;
    }

    public function withDebug(bool $debug, bool $stopOnDebug) : static {
        $this->debug = $debug;
        $this->stopOnDebug = $stopOnDebug;
        return $this;
    }

    public function withCache(bool $cache) : static {
        $this->cache = $cache;
        return $this;
    }
}
