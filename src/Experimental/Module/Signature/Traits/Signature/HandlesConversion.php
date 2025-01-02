<?php
namespace Cognesy\Instructor\Experimental\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;

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
