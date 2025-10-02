<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline;

use Cognesy\Experimental\Pipeline\Contracts\Operator;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A serializable specification for an Operator (a value object).
 *
 * This DTO holds the class name and constructor arguments needed to
 * instantiate an operator, making the pipeline definition portable.
 *
 */
readonly final class OperatorSpec implements JsonSerializable
{
    public function __construct(
        public string $class,
        public array $args = [],
    ) {
        if (!class_exists($class) || !in_array(Operator::class, class_implements($class))) {
            throw new InvalidArgumentException(
                "Operator class {$class} must exist and implement the " . Operator::class . " interface.",
            );
        }
    }

    public static function from(string $class, mixed ...$args): self {
        return new self($class, $args);
    }

    #[\Override]
    public function jsonSerialize(): array {
        return ['class' => $this->class, 'args' => $this->args];
    }
}
