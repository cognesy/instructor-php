<?php

namespace Cognesy\Instructor\Extras\Agent\Contracts;

interface CanHandleMemory
{
    public function toMemory(string $key, mixed $data): void;
    public function fromMemory(string $key): mixed;
}