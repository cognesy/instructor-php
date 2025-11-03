<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Utils\Result\Result;

interface CanValidateResponse
{
    public function validate(object $response, ResponseModel $responseModel) : Result;
}