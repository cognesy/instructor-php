<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Validation\Exceptions\ValidationException;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\InferenceResponse;
use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Result\Result;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class RequestHandler
{
    protected StructuredOutputRequest  $request;
    protected CanGenerateResponse      $responseGenerator;
    protected CanGeneratePartials      $partialsGenerator;
    protected CanMaterializeRequest    $requestMaterializer;
    protected LLM                      $llm;
    protected EventDispatcherInterface $events;

    protected int $retries = 0;
    protected array $errors = [];

    public function __construct(
        StructuredOutputRequest  $request,
        CanGenerateResponse      $responseGenerator,
        CanGeneratePartials      $partialsGenerator,
        CanMaterializeRequest    $requestMaterializer,
        LLM                      $llm,
        EventDispatcherInterface $events,
    ) {
        $this->request = $request;
        $this->responseGenerator = $responseGenerator;
        $this->partialsGenerator = $partialsGenerator;
        $this->requestMaterializer = $requestMaterializer;
        $this->llm = $llm;
        $this->events = $events;
        $this->retries = 0;
        $this->errors = [];
    }

    // PUBLIC //////////////////////////////////////////////////////////////

    /**
     * Generates response value
     */
    public function responseFor(StructuredOutputRequest $request) : LLMResponse {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $llmResponse = $this->getInference($request)->response();
            $llmResponse->withContent(match($request->mode()) {
                OutputMode::Text => $llmResponse->content(),
                OutputMode::Tools => $llmResponse->toolCalls()->first()?->argsAsJson()
                    ?? $llmResponse->content() // fallback if no tool calls - some LLMs return just a string
                    ?? '',
                default => Json::fromString($llmResponse->content())->toString(), // OutputMode::MdJson, OutputMode::Json, OutputMode::JsonSchema
            });
            $partialResponses = [];
            $processingResult = $this->processResponse($request, $llmResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $request, $llmResponse, $partialResponses);

        return $llmResponse->withValue($value);
    }

    /**
     * Yields response value versions based on streamed responses
     *
     * @param StructuredOutputRequest $request
     * @return Generator<PartialLLMResponse>
     */
    public function streamResponseFor(StructuredOutputRequest $request) : Generator {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $stream = $this->getInference($request)->stream()->responses();
            yield from $this->partialsGenerator->getPartialResponses($stream, $request->responseModel());

            $llmResponse = $this->partialsGenerator->getCompleteResponse();
            $partialResponses = $this->partialsGenerator->partialResponses();
            $processingResult = $this->processResponse($request, $llmResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $request, $llmResponse, $partialResponses);

        yield $llmResponse->withValue($value);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    protected function getInference(StructuredOutputRequest $request) : InferenceResponse {
        return (new Inference)
            ->withLLM($this->llm)
            ->withEventDispatcher($this->events)
            ->with(
                messages: $this->requestMaterializer->toMessages($request),
                model: $request->model(),
                tools: $request->toolCallSchema(),
                toolChoice: $request->toolChoice(),
                responseFormat: $request->responseFormat(),
                options: $request->options(),
                mode: $request->mode()
            )
            ->create();
    }

    protected function processResponse(StructuredOutputRequest $request, LLMResponse $llmResponse, array $partialResponses) : Result {
        // we have LLMResponse here - let's process it: deserialize, validate, transform
        $processingResult = $this->responseGenerator->makeResponse(
            response: $llmResponse,
            responseModel: $request->responseModel(),
            mode: $request->mode()
        );

        if ($processingResult->isFailure()) {
            // retry - we have not managed to deserialize, validate or transform the response
            $this->handleError($processingResult, $request, $llmResponse, $partialResponses);
        }

        return $processingResult;
    }

    protected function finalizeResult(Result $processingResult, StructuredOutputRequest $request, LLMResponse $llmResponse, array $partialResponses) : mixed {
        if ($processingResult->isFailure()) {
            $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, $this->errors));
            throw new ValidationException(
                message: "Validation recovery attempts limit reached after {$this->retries} attempt(s) due to: ".implode(", ", $this->errors),
                errors: $this->errors,
            );
        }

        // get final value
        $value = $processingResult->unwrap();
        // store response
        $request->setResponse($request->messages(), $llmResponse, $partialResponses, $value); // TODO: tx messages to Scripts
        // notify on response generation
        $this->events->dispatch(new ResponseGenerated($value));

        return $value;
    }

    protected function handleError(Result $processingResult, StructuredOutputRequest $request, LLMResponse $llmResponse, array $partialResponses) : void {
        $error = $processingResult->error();
        $this->errors = is_array($error) ? $error : [$error];

        // store failed response
        $request->addFailedResponse($request->messages(), $llmResponse, $partialResponses, $this->errors); // TODO: tx messages to Scripts
        $this->retries++;
        if (!$this->maxRetriesReached($request)) {
            $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $this->errors));
        }
    }

    protected function maxRetriesReached(StructuredOutputRequest $request) : bool {
        return $this->retries > $request->maxRetries();
    }
}
