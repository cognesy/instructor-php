<?php
namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
interface CanHandlePartialResponse
{
    public function handlePartialResponse(string $partialJsonData, ResponseModel $responseModel): void;
}