<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Generator;

interface CanGeneratePartials
{
    public function getPartialResponses(ApiClient $apiCallRequest, Request $request, ResponseModel $responseModel) : Generator;
}
