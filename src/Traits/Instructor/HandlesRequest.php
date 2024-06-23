<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Data\Request;
use Throwable;

trait HandlesRequest
{
    private RequestFactory $requestFactory;
    private Request $request;

    public function getRequest() : Request {
        return $this->request;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    protected function handleRequest() : mixed {
        $this->dispatchQueuedEvents();
        /** @var RequestHandler $requestHandler */
        $requestHandler = $this->config->get(CanHandleRequest::class);
        try {
            $this->request = $this->requestFactory->fromData($this->requestData);
            return $requestHandler->respondTo($this->request);
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleStreamRequest() : Iterable {
        $this->dispatchQueuedEvents();
        /** @var StreamRequestHandler $streamHandler */
        $streamHandler = $this->config->get(CanHandleStreamRequest::class);
        try {
            $this->request = $this->requestFactory->fromData($this->requestData);
            yield from $streamHandler->respondTo($this->request);
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }
}