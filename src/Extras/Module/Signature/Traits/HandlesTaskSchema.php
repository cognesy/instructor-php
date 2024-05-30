<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait HandlesTaskSchema
{
    public function toInputSchema(): Schema {
        return $this->input->toSchema();
    }

    public function toOutputSchema(): Schema {
        return $this->output->toSchema();
    }

    public function description(): string {
        return $this->description;
    }
}