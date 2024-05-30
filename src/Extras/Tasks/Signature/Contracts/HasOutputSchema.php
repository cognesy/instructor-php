<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature\Contracts;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

interface HasOutputSchema
{
    public function toOutputSchema() : Schema;
}