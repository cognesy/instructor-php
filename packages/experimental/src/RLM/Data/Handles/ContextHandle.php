<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data\Handles;

final readonly class ContextHandle implements Handle
{
    public function __construct(private string $ref) {}

    public function id(): string {
        return $this->ref;
    }

    public static function artifact(string $uri): self {
        return new self($uri);
    }

    public static function variable(string $name): self {
        return new self("var://{$name}");
    }
}

