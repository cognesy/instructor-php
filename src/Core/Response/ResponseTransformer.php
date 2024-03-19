<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ResponseHandler\ResponseTransformationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseTransformationFailed;
use Cognesy\Instructor\Events\ResponseHandler\ResponseTransformed;
use Cognesy\Instructor\Utils\Result;
use Exception;

class ResponseTransformer
{
    public function __construct(
        private EventDispatcher $eventDispatcher
    ) {}

    public function transform(object $object) : Result {
        return match(true) {
            $object instanceof CanTransformSelf => $this->transformSelf($object),
            default => Result::success($object),
        };
    }

    protected function transformSelf(CanTransformSelf $object) : Result {
        $this->eventDispatcher->dispatch(new ResponseTransformationAttempt($object));
        try {
            $transformed = $object->transform();
        } catch (Exception $e) {
            $this->eventDispatcher->dispatch(new ResponseTransformationFailed($object, $e->getMessage()));
            return Result::failure($e->getMessage());
        }
        $this->eventDispatcher->dispatch(new ResponseTransformed($transformed));
        return Result::success($transformed);
    }
}