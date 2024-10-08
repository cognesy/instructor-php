<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Contracts;

use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;

interface HasInputSchema
{
    public function toInputSchema() : Schema;
}