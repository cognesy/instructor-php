<?php
namespace Cognesy\Instructor\Transformation;

use Cognesy\Instructor\Events\Response\ResponseTransformationAttempt;
use Cognesy\Instructor\Events\Response\ResponseTransformationFailed;
use Cognesy\Instructor\Events\Response\ResponseTransformed;
use Cognesy\Instructor\Transformation\Contracts\CanTransformObject;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Utils\Result\Result;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseTransformer
{
    use Traits\ResponseTransformer\HandlesMutation;

    public function __construct(
        private EventDispatcherInterface $events,
        /** @var CanTransformObject[] $transformers */
        private array $transformers = []
    ) {}

    public function transform(object $object) : Result {
        return match(true) {
            $object instanceof CanTransformSelf => $this->transformSelf($object),
            default => $this->transformObject($object),
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function transformSelf(CanTransformSelf $object) : Result {
        $this->events->dispatch(new ResponseTransformationAttempt(['object' => $object]));
        try {
            $transformed = $object->transform();
        } catch (Exception $e) {
            $this->events->dispatch(new ResponseTransformationFailed(['object' => $object, 'error' => $e->getMessage()]));
            return Result::failure($e->getMessage());
        }
        $this->events->dispatch(new ResponseTransformed(['response' => json_encode($transformed)]));
        return Result::success($transformed);
    }

    protected function transformObject(object $object) : Result {
        if (empty($this->transformers)) {
            return Result::success($object);
        }

        $data = $object->clone();
        foreach ($this->transformers as $transformer) {
            $transformer = match(true) {
                is_string($transformer) && is_subclass_of($transformer, CanTransformObject::class) => new $transformer(),
                $transformer instanceof CanTransformObject => $transformer,
                default => throw new Exception('Transformer must implement CanTransformObject interface'),
            };
            $this->events->dispatch(new ResponseTransformationAttempt(['object' => $data]));
            // TODO: should we catch exceptions here?
            $result = $transformer->transform($data);
            if (!empty($result)) {
                $data = $result;
            }
        }
        return Result::success($data);
    }
}
