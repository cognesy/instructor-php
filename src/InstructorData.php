<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\EventDispatcher;

class InstructorData
{
    public CanCallApi $client;
    public EventDispatcher $events;
    public Configuration $config;
    public bool $stopOnDebug;
    public bool $debug;
    public bool $cache;
    /** @var callable|null */
    public $onSequenceUpdate;
    /** @var callable|null */
    public $onError;
    /** @var callable|null */
    public $wiretap;
    /** @var callable|null */
    public $onEvent;

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
        callable $onEvent = null,
        callable $wiretap = null,
        callable $onSequenceUpdate = null,
        callable $onError = null,
    ) : static {
        $data = new static();
        $data->events = $eventDispatcher;
        $data->config = $config;
        $data->client = $client;
        $data->debug = $debug;
        $data->cache = $cache;
        $data->stopOnDebug = $stopOnDebug;
        $data->onEvent = $onEvent;
        $data->wiretap = $wiretap;
        $data->onSequenceUpdate = $onSequenceUpdate;
        $data->onError = $onError;
        return $data;
    }

    // SETTERS ////////////////////////////////////////////////////////////////////////

    public function withClient(CanCallApi $client) : static {
        $this->client = $client;
        return $this;
    }

    public function withConfig(Configuration $config) : static {
        $this->config = $config;
        return $this;
    }

    public function withEventDispatcher(EventDispatcher $eventDispatcher) : static {
        $this->events = $eventDispatcher;
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

    // LISTENER SETTERS ///////////////////////////////////////////////////////////////

    public function onEvent(callable $listener) : static {
        $this->onEvent = $listener;
        return $this;
    }

    public function wiretap(callable $listener) : static {
        $this->wiretap = $listener;
        return $this;
    }

    public function onError(callable $listener) : static {
        $this->onError = $listener;
        return $this;
    }

    public function onSequenceUpdate(callable $listener) : static {
        $this->onSequenceUpdate = $listener;
        return $this;
    }
}
