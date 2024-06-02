<?php

namespace Cognesy\Instructor\Extras\Module\TaskData\Traits\TaskDataClass;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait HandlesSchema
{
    public function toSchema(): Schema {
        return $this->signature()->toOutputSchema();
    }
}