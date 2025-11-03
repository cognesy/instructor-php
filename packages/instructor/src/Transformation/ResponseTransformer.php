<?php declare(strict_types=1);
namespace Cognesy\Instructor\Transformation;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Response\ResponseTransformationAttempt;
use Cognesy\Instructor\Events\Response\ResponseTransformationFailed;
use Cognesy\Instructor\Events\Response\ResponseTransformed;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Transformation\Exceptions\ResponseTransformationException;
use Cognesy\Utils\Result\Result;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseTransformer implements CanTransformResponse
{
    public function __construct(
        private EventDispatcherInterface $events,
        /** @var array<CanTransformData|class-string<CanTransformData>> $transformers */
        private array $transformers,
        private StructuredOutputConfig $config,
    ) {}

    #[\Override]
    public function transform(mixed $data, ResponseModel $responseModel) : Result {
        return match(true) {
            $data instanceof CanTransformSelf => $this->transformSelf($data),
            default => $this->transformData($data),
        };
    }

    /** @param CanTransformData[] $transformers */
    public function appendTransformers(array $transformers) : self {
        $this->transformers = array_merge($this->transformers, $transformers);
        return $this;
    }

    /** @param CanTransformData[] $transformers */
    public function setTransformers(array $transformers) : self {
        $this->transformers = $transformers;
        return $this;
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
                $errorMessage = $result->exception()->getMessage();
                $this->events->dispatch(new ResponseTransformationFailed(['data' => $data, 'error' => $errorMessage]));
                if ($this->config->throwOnTransformationFailure()) {
                    throw new ResponseTransformationException($errorMessage);
                }
                continue;
            }

            // we take the transformed data and continue transforming it
            $data = $result->unwrap();
        }

        return Result::success($data);
    }
}
