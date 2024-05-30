<?php

namespace Cognesy\Instructor\Extras\Module\Task;

use Closure;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;

class CallClosure extends ExecutableTask
{
    private Closure $callable;

    public function __construct(Closure $callable) {
        $this->callable = $callable;
        parent::__construct();
    }

    public function signature(): string|HasSignature {
        return SignatureFactory::fromCallable($this->callable);
    }

    public function forward(mixed ...$args): mixed {
        return ($this->callable)(...$args);
    }
}
