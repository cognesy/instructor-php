<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Contracts;

interface HasInputOutputSchema extends HasInputSchema, HasOutputSchema
{
    public function description() : string;

    public function toSignatureString() : string;
}