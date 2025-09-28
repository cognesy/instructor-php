<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2;

use Cognesy\Experimental\Pipeline2\Contracts\Operator;
use Cognesy\Experimental\Pipeline2\Operators\Around;
use Cognesy\Experimental\Pipeline2\Operators\Map;
use Cognesy\Experimental\Pipeline2\Operators\Tap;
use Cognesy\Experimental\Pipeline2\Operators\When;
use InvalidArgumentException;
use JsonSerializable;
use Serializable;

/**
 * A serializable specification for an Operator (a value object).
 *
 * This DTO holds the class name and constructor arguments needed to
 * instantiate an operator, making the pipeline definition portable.
 *
 */
readonly final class Op implements Serializable, JsonSerializable
{
    public function __construct(
        public string $class,
        public array $args = [],
    ) {
        $this->validateOperatorClass($class);
    }

    public static function from(string $class, mixed ...$args): self {
        return new self($class, $args);
    }

    // FACTORIES ////////////////////////////////////////

    public static function around(callable $callable): self {
        return new self(Around::class, [$callable]);
    }

    public static function map(callable $callable): self {
        return new self(Map::class, [$callable]);
    }

    public static function when(callable $predicate, callable $callable): self {
        return new self(When::class, [$callable]);
    }

    public static function tap(callable $callable): self {
        return new self(Tap::class, [$callable]);
    }

    // SERIALIZATION ///////////////////////////////////

    public function jsonSerialize(): array {
        return ['class' => $this->class, 'args' => $this->args];
    }

    public function serialize() {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data) {
        $this->__unserialize(unserialize($data, ['allowed_classes' => false]));
    }

    public function __serialize(): array {
        return ['class' => $this->class, 'args' => $this->args];
    }

    public function __unserialize(array $data): void {
        $this->class = $data['class'];
        $this->args = $data['args'];
    }

    // INTERNAL ////////////////////////////////////////

    private function validateOperatorClass(string $class) : void {
        if (!class_exists($class) || !in_array(Operator::class, class_implements($class))) {
            throw new InvalidArgumentException(
                "Operator class {$class} must exist and implement the " . Operator::class . " interface.",
            );
        }
    }
}
