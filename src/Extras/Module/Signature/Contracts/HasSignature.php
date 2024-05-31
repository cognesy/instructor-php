<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Contracts;

use Cognesy\Instructor\Extras\Module\DataModel\Contracts\DataModel;

interface HasSignature extends HasInputSchema, HasOutputSchema, CanHaveErrors
{
    public const ARROW = '->';

    // DATA ENTRY /////////////////////////////////////////////////////////

    public function withArgs(mixed ...$inputs) : static;

    // DATA MODEL ACCESS //////////////////////////////////////////////////

    public function input() : DataModel;
    public function output() : DataModel;

    public function toArray() : array;

    public function description() : string;

    public function toSignatureString() : string;
}
