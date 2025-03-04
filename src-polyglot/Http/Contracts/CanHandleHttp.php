<?php
namespace Cognesy\Polyglot\Http\Contracts;

use Cognesy\Polyglot\Http\Data\HttpClientRequest;

interface CanHandleHttp
{
    public function handle(HttpClientRequest $request) : HttpClientResponse;
}
