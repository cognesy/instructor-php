<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Contracts\CanHandleSyncRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\RawRequestHandler;
use Cognesy\Instructor\Core\RequestHandler;
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
        $requestHandler = $this->config->get(RequestHandler::class);
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
        /** @var RequestHandler $requestHandler */
        $requestHandler = $this->config->get(RequestHandler::class);
        try {
            $this->request = $this->requestFactory->fromData(
                $this->requestData->withCachedContext($this->cachedContext)
            );
            yield from $requestHandler->streamResponseFor($this->request);
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleRawRequest() : string {
        $this->dispatchQueuedEvents();
        /** @var RawRequestHandler $requestHandler */
        $requestHandler = $this->config->get(RawRequestHandler::class);
        try {
            $this->request = $this->requestFactory->fromData(
                $this->requestData->withCachedContext($this->cachedContext)
            );
            return $requestHandler->responseFor($this->request);
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleRawStreamRequest() : Iterable {
        $this->dispatchQueuedEvents();
        /** @var RawRequestHandler $requestHandler */
        $requestHandler = $this->config->get(RawRequestHandler::class);
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
