<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Generator;

interface CanGeneratePartials
{
    public function getPartialResponses(Request $request, ResponseModel $responseModel, array $messages = []) : Generator;
}
