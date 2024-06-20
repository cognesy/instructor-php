<?php

namespace Cognesy\Instructor\Transformation;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Response\ResponseTransformationAttempt;
use Cognesy\Instructor\Events\Response\ResponseTransformationFailed;
use Cognesy\Instructor\Events\Response\ResponseTransformed;
use Cognesy\Instructor\Transformation\Contracts\CanTransformObject;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Utils\Result;
use Exception;

class ResponseTransformer
{
    public function __construct(
        private EventDispatcher $events,
        /** @var CanTransformObject[] $transformers */
        private array $transformers = []
    ) {}

    public function transform(object $object) : Result {
        return match(true) {
            $object instanceof CanTransformSelf => $this->transformSelf($object),
            default => $this->transformObject($object),
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

    protected function transformObject(object $object) : Result {
        if (empty($this->transformers)) {
            return Result::success($object);
        }
        // transform
        $data = $object->clone();
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof CanTransformObject) {
                throw new Exception('Transformer must implement CanTransformObject interface');
            }
            $this->events->dispatch(new ResponseTransformationAttempt($data));
            $data = $transformer->transform($data);
        }
        return Result::success($data);
    }
}
