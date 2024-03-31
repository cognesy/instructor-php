<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Data\Request;

class ResponseStream implements CanHandleRequest
{
    public function respondTo(Request $request): mixed {
        // get ResponseModel for the request
        // get client instance

        // if streaming requested
        //     get partial response
        //     if error return Result::failure
        //
        //     aggregate partial responses until response object update possible
        //     updated object = deserialize and transform partial response
        //     if deserialization or transformation error - continue
        //     yield response objects wrapped in Result monad
        //
        //     all partial responses retrieved
        // else
        //     get final response object

        // final object = deserialize, validate, transform
        // if error return Result::failure
        //     try repeating the process until max retries reached
        // return object wrapped in Result monad
    }

    protected function partials(Generator $stream, ResponseModel $responseModel) : Generator {
        yield $this->getPartialResponses($stream, $responseModel)
            ->onError(fn($error) => Result::failure($error))
            ->
    }
}