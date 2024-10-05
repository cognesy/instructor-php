<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Data\LLMResponse;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Extras\LLM\InferenceResponse;
use Cognesy\Instructor\Stream;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Result\Result;
use Exception;
use Generator;

class RequestHandler
{
    protected EventDispatcher $events;

    protected int $retries = 0;
    protected array $errors = [];

    public function __construct(
        protected Request $request,
        protected CanGenerateResponse $responseGenerator,
        protected CanGeneratePartials $partialsGenerator,
        protected string $connection,
        protected ?CanHandleInference $driver,
        protected ?CanHandleHttp $httpClient,
        EventDispatcher $events,
    ) {
        $this->events = $events;
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->request->isStream()) {
            return $this->stream()->final();
        }
        $result = $this->responseFor($this->request);
        $this->events->dispatch(new InstructorDone(['result' => $result]));
        return $result->value();
    }

    /**
     * Executes the request and returns the response stream
     */
    public function stream() : Stream {
        // TODO: do we need this? cannot we just turn streaming on?
        if (!$this->request->isStream()) {
            throw new Exception('Instructor::stream() method requires response streaming: set "stream" = true in the request options.');
        }
        $stream = $this->streamResponseFor($this->request);
        return new Stream($stream, $this->events);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    /**
     * Generates response value
     */
    protected function responseFor(Request $request) : LLMResponse {
        $this->init();

        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $llmResponse = $this->getInference($request)->toLLMResponse();
            $llmResponse->content = match($request->mode()) {
                Mode::Text => $llmResponse->content,
                default => Json::from($llmResponse->content)->toString(),
            };
            $partialResponses = [];
            $processingResult = $this->processResponse($request, $llmResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $request, $llmResponse, $partialResponses);

        return $llmResponse->withValue($value);
    }

    /**
     * Yields response value versions based on streamed responses
     * @param Request $request
     * @return Generator<PartialLLMResponse|LLMResponse>
     */
    protected function streamResponseFor(Request $request) : Generator {
        $this->init();

        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $stream = $this->getInference($request)->toPartialLLMResponses();
            yield from $this->partialsGenerator->getPartialResponses($stream, $request->responseModel());

            $llmResponse = $this->partialsGenerator->getCompleteResponse();
            $partialResponses = $this->partialsGenerator->partialResponses();
            $processingResult = $this->processResponse($request, $llmResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $request, $llmResponse, $partialResponses);

        yield $llmResponse->withValue($value);
    }

    protected function init() : void {
        $this->retries = 0;
        $this->errors = [];
    }

    protected function getInference(Request $request) : InferenceResponse {
        $inference = new Inference(
            connection: $this->connection,
            httpClient: $this->httpClient,
            driver: $this->driver,
            events: $this->events,
        );
        return $inference
            ->create(
                $request->toMessages(),
                $request->model(),
                $request->toolCallSchema(),
                $request->toolChoice(),
                $request->responseFormat(),
                $request->options(),
                $request->mode()
            );
    }

    protected function processResponse(Request $request, LLMResponse $llmResponse, array $partialResponses) : Result {
        // we have LLMResponse here - let's process it: deserialize, validate, transform
        $processingResult = $this->responseGenerator->makeResponse($llmResponse, $request->responseModel());

        if ($processingResult->isFailure()) {
            // retry - we have not managed to deserialize, validate or transform the response
            $this->handleError($processingResult, $request, $llmResponse, $partialResponses);
        }

        return $processingResult;
    }

    protected function finalizeResult(Result $processingResult, Request $request, LLMResponse $llmResponse, array $partialResponses) : mixed {
        if ($processingResult->isFailure()) {
            $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, $this->errors));
            throw new Exception("Validation recovery attempts limit reached after {$this->retries} attempts due to: ".implode(", ", $this->errors));
        }

        // get final value
        $value = $processingResult->unwrap();
        // store response
        $request->setResponse($request->messages(), $llmResponse, $partialResponses, $value); // TODO: tx messages to Scripts
        // notify on response generation
        $this->events->dispatch(new ResponseGenerated($value));

        return $value;
    }

    protected function handleError(Result $processingResult, Request $request, LLMResponse $llmResponse, array $partialResponses) : void {
        $error = $processingResult->error();
        $this->errors = is_array($error) ? $error : [$error];

        // store failed response
        $request->addFailedResponse($request->messages(), $llmResponse, $partialResponses, $this->errors); // TODO: tx messages to Scripts
        $this->retries++;
        if (!$this->maxRetriesReached($request)) {
            $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $this->errors));
        }
    }

    protected function maxRetriesReached(Request $request) : bool {
        return $this->retries > $request->maxRetries();
    }
}
