<?php
namespace Cognesy\Instructor\Extras\Module\Addons\CallClosure;

use Closure;
use Cognesy\Instructor\Extras\Module\Core\DynamicModule;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Module\Signature\Signature;

class CallClosure extends DynamicModule
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
