<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Data\Request;
use Throwable;

trait HandlesRequest
{
    private RequestFactory $requestFactory;
    private Request $request;
    private RequestHandler $requestHandler;

    public function getRequest() : Request {
        return $this->request;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    protected function handleRequest() : mixed {
        $this->dispatchQueuedEvents();
        $requestHandler = $this->requestHandler;
        try {
            $this->request = $this->requestFactory->fromData(
                $this->requestData->withCachedContext($this->cachedContext)
            );
            return $requestHandler->responseFor($this->request);
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleStreamRequest() : Iterable {
        $this->dispatchQueuedEvents();
        $requestHandler = $this->requestHandler;
        try {
            $this->request = $this->requestFactory->fromData(
                $this->requestData->withCachedContext($this->cachedContext)
            );
            yield from $requestHandler->streamResponseFor($this->request);
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }
}
