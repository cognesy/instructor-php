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
    protected ?Request $request = null;
    protected RequestFactory $requestFactory;

    protected function handleRequest() : mixed {
        try {
            /** @var RequestHandler $requestHandler */
            $requestHandler = $this->config()->get(CanHandleRequest::class);
            $this->startTimer();
            $response = $requestHandler->respondTo($this->getRequest());
            $this->stopTimer();
            $this->events->dispatch(new ResponseGenerated($response));
            return $response;
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function handleStreamRequest() : Iterable {
        try {
            /** @var StreamRequestHandler $streamHandler */
            $streamHandler = $this->config()->get(CanHandleStreamRequest::class);
            $this->startTimer();
            yield from $streamHandler->respondTo($this->getRequest());
            $this->stopTimer();
        } catch (Throwable $error) {
            return $this->handleError($error);
        }
    }

    protected function getRequest() : Request {
        if ($this->debug()) {
            $this->request->setOption('debug', true);
        }
        return $this->requestFactory->fromRequest($this->request);
    }
}