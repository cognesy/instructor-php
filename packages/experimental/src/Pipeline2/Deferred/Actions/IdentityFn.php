<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Actions;

use Cognesy\Experimental\Pipeline2\Contracts\CanProcessPayload;

class IdentityFn implements CanProcessPayload
{
    public function __invoke(mixed $payload): mixed {
        return $payload;
    }
}