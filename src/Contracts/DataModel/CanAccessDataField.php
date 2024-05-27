<?php
namespace Cognesy\Instructor\Contracts\DataModel;

interface CanAccessDataField
{
    public function get(): mixed;
    public function set(mixed $value): static;
}
