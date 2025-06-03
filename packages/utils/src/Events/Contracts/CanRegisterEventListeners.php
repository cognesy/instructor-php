<?php

namespace Cognesy\Utils\Events\Contracts;

interface CanRegisterEventListeners
{
    public function wiretap(callable $listener): static;
    public function addListener(string $name, callable $listener): static;
}