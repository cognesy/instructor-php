<?php

namespace Cognesy\Instructor\Contracts\DataModel;

interface CanAccessField
{
    public function name(): string;

    public function get(): mixed;
    public function set(mixed $value): static;
}