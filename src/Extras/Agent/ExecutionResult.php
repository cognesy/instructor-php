<?php

namespace Cognesy\Instructor\Extras\Agent;

class ExecutionResult
{
    public function __construct(
        protected mixed $value = null,
        protected ?Agent $agent = null,
        protected ?ExecutionContext $context = null,
    ) {}

    public function value(): mixed {
        return $this->value;
    }

    public function text(): string {
        return (string) $this->value;
    }

    public function agent(): ?Agent {
        return $this->agent;
    }

    public function context(): ?ExecutionContext {
        return $this->context;
    }
}