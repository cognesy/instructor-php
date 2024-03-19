<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ResponseHandler\ResponseTransformed;

class ResponseTransformer
{
    public function __construct(
        private EventDispatcher $eventDispatcher
    ) {}

    public function transform(object $object) : mixed {
        return match(true) {
            $object instanceof CanTransformSelf => $this->transformSelf($object),
            default => $object
        };
    }

    protected function transformSelf(CanTransformSelf $object) : mixed {
        $result = $object->transform();
        $this->eventDispatcher->dispatch(new ResponseTransformed($result));
        return $result;
    }
}