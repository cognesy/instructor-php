<?php
namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Utils\Debugger;
use Exception;
use Saloon\Http\Response;

trait HandlesDebug
{
    protected function tryDebugException(ApiRequest $request, Exception $exception): void {
        if ($request->requestConfig()->isDebug() && method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();
            if (!empty($response)) {
                Debugger::requestDebugger($response->getPendingRequest(), $response->getPsrRequest());
                // body cannot be accessed - see Saloon issue: https://github.com/saloonphp/saloon/issues/447
                Debugger::responseDebugger($response, $response->getPsrResponse(), $response->getPsrResponse()->getBody());
            }
        }
    }

    protected function tryDebug(ApiRequest $request, Response $response, string $body = null): void {
        if ($request->requestConfig()->isDebug()) {
            Debugger::requestDebugger($response->getPendingRequest(), $response->getPsrRequest());
            Debugger::responseDebugger($response, $response->getPsrResponse(), $body ?? '');
        }
    }
}