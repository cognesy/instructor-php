<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Contracts;

use Cognesy\Schema\Data\Schema\Schema;

interface HasOutputSchema
{
    public function toOutputSchema() : Schema;
}