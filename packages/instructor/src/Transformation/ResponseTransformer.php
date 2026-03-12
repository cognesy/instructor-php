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
        private ?CanTransformData $transformer,
        private StructuredOutputConfig $config,
    ) {}

    #[\Override]
    public function transform(mixed $data, ResponseModel $responseModel) : Result {
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
        if ($this->transformer === null) {
            return Result::success($input);
        }

        // clone the input as transformer may mutate it
        $data = match(true) {
            is_object($input) => clone $input,
            default => $input,
        };

        $this->events->dispatch(new ResponseTransformationAttempt(['data' => $data]));
        $result = Result::try(fn() => $this->transformer->transform($data));
        if ($result->isSuccessAndNull()) {
            return Result::success($input);
        }
        if ($result->isFailure()) {
            $errorMessage = $result->exception()->getMessage();
            $this->events->dispatch(new ResponseTransformationFailed(['data' => $data, 'error' => $errorMessage]));
            if ($this->config->throwOnTransformationFailure()) {
                throw new ResponseTransformationException($errorMessage);
            }
            return Result::success($input);
        }

        return Result::success($result->unwrap());
    }
}
