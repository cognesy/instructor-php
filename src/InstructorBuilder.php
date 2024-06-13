<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\EventDispatcher;

class InstructorBuilder
{
    public CanCallApi $client;
    public EventDispatcher $eventDispatcher;
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

    public function withClient(CanCallApi $client) : static {
        $this->client = $client;
        return $this;
    }

    public function withConfig(Configuration $config) : static {
        $this->config = $config;
        return $this;
    }

    public function withEventDispatcher(EventDispatcher $eventDispatcher) : static {
        $this->eventDispatcher = $eventDispatcher;
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

    public static function new() : InstructorBuilder {
        return new InstructorBuilder();
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
    ) : InstructorBuilder {
        $builder = new InstructorBuilder();
        $builder->eventDispatcher = $eventDispatcher;
        $builder->config = $config;
        $builder->client = $client;
        $builder->debug = $debug;
        $builder->cache = $cache;
        $builder->stopOnDebug = $stopOnDebug;
        $builder->onEvent = $onEvent;
        $builder->wiretap = $wiretap;
        $builder->onSequenceUpdate = $onSequenceUpdate;
        $builder->onError = $onError;
        return $builder;
    }
}
