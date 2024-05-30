<?php

namespace Cognesy\Instructor\Extras\Module\Addons\CallClosure;

use Closure;
use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;

class CallClosure extends Module
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
