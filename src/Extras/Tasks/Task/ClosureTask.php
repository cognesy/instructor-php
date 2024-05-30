<?php

namespace Cognesy\Instructor\Extras\Tasks\Task;

use Closure;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Tasks\Signature\SignatureFactory;

class ClosureTask extends ExecutableTask
{
    private Closure $callable;

    public function __construct(Closure $callable) {
        $this->callable = $callable;
        parent::__construct();
    }

    public function forward(mixed ...$args): mixed {
        return ($this->callable)(...$args);
    }

    public function signature(): string|HasSignature {
        return SignatureFactory::fromCallable($this->callable);
    }
}
