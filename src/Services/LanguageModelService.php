<?php

namespace Cognesy\Instructor\Services;

use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\Response;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;

// TODO: this is part of refactoring in progress - currently not used

class LanguageModelService
{
    public function respond(Request $request) : Response {
        return new Response;
    }

    public function stream(Request $request) : Generator {
        yield null;
    }

    public function async(Request $request) : ?PromiseInterface {
        return null;
    }
}