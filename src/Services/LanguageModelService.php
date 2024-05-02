<?php

namespace Cognesy\Instructor\Services;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\Response;
use Cognesy\Instructor\Data\ResponseModel;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;

// TODO: this is part of refactoring in progress - currently not used

class LanguageModelService
{
    public function respond(
        Request $request,
        ResponseModel $responseModel,
        CanCallApi $client
    ) : Response {
        return new Response;
    }

    public function stream(
        Request $request,
        ResponseModel $responseModel,
        CanCallApi $client
    ) : Generator {
        yield null;
    }

    public function async(
        Request $request,
        ResponseModel $responseModel,
        CanCallApi $client
    ) : PromiseInterface {
        return null;
    }
}