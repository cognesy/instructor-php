<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait HandlesConversion
{
    public function toInputSchema(): Schema {
        return $this->input;
    }

    public function toOutputSchema(): Schema {
        return $this->output;
    }

    public function toSchema(): Schema {
        return $this->output;
    }
}
