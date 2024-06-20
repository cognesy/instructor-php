<?php

namespace Cognesy\Instructor\Transformation;

use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Response\ResponseTransformationAttempt;
use Cognesy\Instructor\Events\Response\ResponseTransformationFailed;
use Cognesy\Instructor\Events\Response\ResponseTransformed;
use Cognesy\Instructor\Utils\Result;
use Exception;

class ResponseTransformer
{
    public function __construct(
        private EventDispatcher $events
    ) {}

    public function transform(object $object) : Result {
        return match(true) {
            $object instanceof CanTransformSelf => $this->transformSelf($object),
            default => Result::success($object),
        };
    }

    protected function transformSelf(CanTransformSelf $object) : Result {
        $this->events->dispatch(new ResponseTransformationAttempt($object));
        try {
            $transformed = $object->transform();
        } catch (Exception $e) {
            $this->events->dispatch(new ResponseTransformationFailed($object, $e->getMessage()));
            return Result::failure($e->getMessage());
        }
        $this->events->dispatch(new ResponseTransformed($transformed));
        return Result::success($transformed);
    }
}