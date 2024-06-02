<?php

namespace Cognesy\Instructor\Extras\Module\CallData\Traits\CallDataClass;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait HandlesSchema
{
    public function toSchema(): Schema {
        return $this->signature()->toOutputSchema();
    }
}