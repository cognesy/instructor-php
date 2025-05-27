<?php
namespace Cognesy\Experimental\Module\Signature\Contracts;

use Cognesy\Schema\Data\Schema\Schema;

interface HasInputSchema
{
    public function toInputSchema() : Schema;
}