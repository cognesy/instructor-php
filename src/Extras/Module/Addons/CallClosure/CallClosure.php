<?php
namespace Cognesy\Instructor\Extras\Module\Addons\CallClosure;

use Closure;
use Cognesy\Instructor\Extras\Module\Addons\InstructorModule\InstructorModule;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;

class CallClosure extends InstructorModule
{
    private Closure $callable;

    public function __construct(Closure $callable) {
        $this->callable = $callable;
    }

    public function signature(): string|Signature {
        return SignatureFactory::fromCallable($this->callable);
    }

    public function forward(mixed ...$args): mixed {
        return ($this->callable)(...$args);
    }
}
