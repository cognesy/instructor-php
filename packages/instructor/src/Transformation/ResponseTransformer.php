<?php declare(strict_types=1);
namespace Cognesy\Instructor\Transformation;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Events\Response\ResponseTransformationAttempt;
use Cognesy\Instructor\Events\Response\ResponseTransformationFailed;
use Cognesy\Instructor\Events\Response\ResponseTransformed;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Transformation\Exceptions\ResponseTransformationException;
use Cognesy\Utils\Result\Result;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseTransformer
{
    use Traits\ResponseTransformer\HandlesMutation;

    public function __construct(
        private EventDispatcherInterface $events,
        /** @var CanTransformData[] $transformers */
        private array $transformers,
        private StructuredOutputConfig $config,
    ) {}

    public function transform(mixed $data) : Result {
        return match(true) {
            $data instanceof CanTransformSelf => $this->transformSelf($data),
            default => $this->transformData($data),
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

    protected function transformData(mixed $input) : Result {
        if (empty($this->transformers)) {
            return Result::success($input);
        }

        // clone the input as transformers may mutate it
        $data = match(true) {
            is_object($input) => clone $input,
            default => $input,
        };

        foreach ($this->transformers as $transformer) {
            $transformer = match(true) {
                is_string($transformer) && is_subclass_of($transformer, CanTransformData::class) => new $transformer(),
                $transformer instanceof CanTransformData => $transformer,
                default => throw new Exception('Transformer must implement CanTransformData interface'),
            };

            $this->events->dispatch(new ResponseTransformationAttempt(['data' => $data]));
            $result = Result::try(fn() => $transformer->transform($data));
            if ($result->isSuccessAndNull()) {
                // if the transformer returns null, we skip it
                continue;
            }
            if ($result->isFailure()) {
                // if the transformer failed - try with the next one
                $this->events->dispatch(new ResponseTransformationFailed(['data' => $data, 'error' => $result->errorMessage()]));
                if ($this->config->throwOnTransformationFailure()) {
                    throw new ResponseTransformationException($result->errorMessage());
                }
                continue;
            }

            // we take the transformed data and continue transforming it
            $data = $result->unwrap();
        }

        return Result::success($data);
    }
}
