<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature\Contracts;

use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\DataModel;

interface HasSignature extends HasInputSchema, HasOutputSchema
{
    public const ARROW = '->';

    public function input() : DataModel;
    public function output() : DataModel;

    public function description() : string;

    public function withArgs(mixed ...$inputs) : static;

    public function toSignatureString() : string;

    public function toArray() : array;
}
