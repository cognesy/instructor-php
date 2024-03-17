<?php
namespace Cognesy\Instructor\Contracts;

use Countable;

interface Sequenceable extends Countable
{
    public static function of(string $class, string $name = '', string $description = '') : static;
    public function toArray() : array;
}