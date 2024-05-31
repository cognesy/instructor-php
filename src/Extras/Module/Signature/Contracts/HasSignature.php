<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Contracts;

interface HasSignature extends HasInputOutputSchema, HasInputOutputData
{
    public const ARROW = '->';
}
