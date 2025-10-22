<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data\Handles;

readonly class ResultHandle implements Handle
{
    public function __construct(private string $ref) {}

    public function id(): string {
        return $this->ref;
    }

    public static function from(string $ref): self {
        return new self($ref);
    }
}

