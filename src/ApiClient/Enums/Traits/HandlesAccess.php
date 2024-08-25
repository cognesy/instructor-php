<?php

namespace Cognesy\Instructor\ApiClient\Enums\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

trait HandlesAccess
{
    public function is(ClientType $clientType) : bool {
        return $this->value === $clientType->value;
    }
}
