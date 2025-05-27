<?php
namespace Cognesy\Experimental\Module\Signature\Contracts;

use Cognesy\Schema\Data\Schema\Schema;

interface HasOutputSchema
{
    public function toOutputSchema() : Schema;
}