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
        $summary = $this->valueSummary($object);
        $this->events->dispatch(new ResponseTransformationAttempt($summary));
        try {
            $transformed = $object->transform();
        } catch (Exception $e) {
            $this->events->dispatch(new ResponseTransformationFailed([
                ...$summary,
                'errorMessage' => $e->getMessage(),
                'errorType' => $e::class,
            ]));
            return Result::failure($e->getMessage());
        }
        $this->events->dispatch(new ResponseTransformed($this->valueSummary($transformed)));
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

        $summary = $this->valueSummary($data);
        $this->events->dispatch(new ResponseTransformationAttempt($summary));
        $result = Result::try(fn() => $this->transformer->transform($data));
        if ($result->isSuccessAndNull()) {
            return Result::success($input);
        }
        if ($result->isFailure()) {
            $errorMessage = $result->exception()->getMessage();
            $this->events->dispatch(new ResponseTransformationFailed([
                ...$summary,
                'errorMessage' => $errorMessage,
                'errorType' => $result->exception()::class,
            ]));
            if ($this->config->throwOnTransformationFailure()) {
                throw new ResponseTransformationException($errorMessage);
            }
            return Result::success($input);
        }

        return Result::success($result->unwrap());
    }

    private function valueSummary(mixed $value) : array
    {
        return match (true) {
            is_object($value) => [
                'valueType' => $value::class,
                'fieldCount' => count(get_object_vars($value)),
            ],
            is_array($value) => [
                'valueType' => 'array',
                'itemCount' => count($value),
                'keys' => array_slice(array_keys($value), 0, 20),
            ],
            default => [
                'valueType' => get_debug_type($value),
            ],
        };
    }
}
