<?php
namespace Cognesy\Instructor\Experimental\Module\Signature\Contracts;

use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;

interface HasOutputSchema
{
    public function toOutputSchema() : Schema;
}