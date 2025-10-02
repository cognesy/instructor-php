<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Modules;

use Closure;
use Cognesy\Experimental\ModPredict\Core\Module;
use Cognesy\Experimental\Signature\Signature;
use Cognesy\Experimental\Signature\SignatureFactory;

class CallClosure extends Module
{
    protected Closure $callable;
    protected Signature $signature;

    public function __construct(Closure $callable) {
        $this->callable = $callable;
        $this->signature = SignatureFactory::fromCallable($this->callable);
    }

    public function signature(): Signature {
        return $this->signature;
    }

    public function for(mixed ...$args): mixed {
        return ($this)(...$args)->get('result');
    }

    #[\Override]
    public function forward(mixed ...$args): array {
        return [
            'result' => ($this->callable)(...$args)
        ];
    }
}
