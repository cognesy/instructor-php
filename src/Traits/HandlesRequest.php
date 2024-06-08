<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Throwable;

trait HandlesRequest
{
    private ?Request $request = null;
    private RequestFactory $requestFactory;

    protected function getRequest() : Request {
        if ($this->request && $this->debug()) {
            $this->request->setOption('debug', true);
        }
        return $this->request;
    }

    protected function handleRequest() : mixed {
        try {
            /** @var RequestHandler $requestHandler */
            $requestHandler = $this->config->get(CanHandleRequest::class);
            $response = $requestHandler->respondTo($this->getRequest());
            $this->events->dispatch(new ResponseGenerated($response));
            return $response;
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleStreamRequest() : Iterable {
        try {
            /** @var StreamRequestHandler $streamHandler */
            $streamHandler = $this->config->get(CanHandleStreamRequest::class);
            yield from $streamHandler->respondTo($this->getRequest());
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }
}