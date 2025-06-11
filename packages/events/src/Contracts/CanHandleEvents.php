<?php

namespace Cognesy\Events\Contracts;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

interface CanHandleEvents extends EventDispatcherInterface, ListenerProviderInterface
{
    public function wiretap(callable $listener): void;
    public function addListener(string $name, callable $listener): void;
}