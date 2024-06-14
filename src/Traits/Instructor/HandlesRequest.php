<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Throwable;

trait HandlesRequest
{
    private RequestFactory $requestFactory;

    // INTERNAL ////////////////////////////////////////////////////////////////////

    protected function handleRequest() : mixed {
        $this->dispatchQueuedEvents();
        /** @var RequestHandler $requestHandler */
        $requestHandler = $this->config->get(CanHandleRequest::class);
        try {
            return $requestHandler->respondTo(
                $this->requestFactory->fromData($this->requestData)
            );
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleStreamRequest() : Iterable {
        $this->dispatchQueuedEvents();
        /** @var StreamRequestHandler $streamHandler */
        $streamHandler = $this->config->get(CanHandleStreamRequest::class);
        try {
            yield from $streamHandler->respondTo(
                $this->requestFactory->fromData($this->requestData)
            );
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }
}