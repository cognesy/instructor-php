<?php
namespace Cognesy\Schema\Contracts;

use Cognesy\Schema\Data\Schema\Schema;

/**
 * Response model can provide a Schema object for the response object
 */
interface CanProvideSchema
{
    public function toSchema() : Schema;
}
