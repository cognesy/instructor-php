<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data\Handles;

final readonly class VarHandle implements Handle
{
    public function __construct(private string $name) {}

    public function id(): string {
        return "var://" . $this->name;
    }

    public function name(): string {
        return $this->name;
    }
}
