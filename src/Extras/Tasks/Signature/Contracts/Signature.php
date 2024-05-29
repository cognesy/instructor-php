<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature\Contracts;

use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\TaskData;

interface Signature
{
    public const ARROW = '->';

    public function data() : TaskData;

    public function description() : string;

    public function withArgs(mixed ...$inputs) : static;

    public function toSignatureString() : string;
}
