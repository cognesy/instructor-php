<?php declare(strict_types=1);
namespace Cognesy\Instructor\Contracts;

use Countable;

interface Sequenceable extends Countable
{
    public static function of(string $class, string $name = '', string $description = '') : static;
    public function toArray() : array;
    public function push(mixed $item) : void;
    public function pop() : mixed;
    public function isEmpty() : bool;
}