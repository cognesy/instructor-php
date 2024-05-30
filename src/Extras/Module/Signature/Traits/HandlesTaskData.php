<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits;

use Cognesy\Instructor\Extras\Module\DataModel\Contracts\DataModel;

trait HandlesTaskData
{
    public function input(): DataModel {
        return $this->input;
    }

    public function output(): DataModel {
        return $this->output;
    }

    public function toArray(): array {
        return array_merge(
            $this->input->getValues(),
            $this->output->getValues(),
        );
    }
}