<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Utils\EventStreamReader;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

abstract class BaseInferenceDriver implements CanHandleInference
{
    /** @phpstan-ignore-next-line */
    protected LLMConfig $config;
    /** @phpstan-ignore-next-line */
    protected HttpClient $httpClient;
    /** @phpstan-ignore-next-line */
    protected EventDispatcherInterface $events;

    /** @phpstan-ignore-next-line */
    protected CanTranslateInferenceRequest $requestTranslator;
    /** @phpstan-ignore-next-line */
    protected CanTranslateInferenceResponse $responseTranslator;


    #[\Override]
    public function makeResponseFor(InferenceRequest $request) : InferenceResponse {
        $httpRequest = $this->toHttpRequest($request);
        $httpResponse = $this->makeHttpResponse($httpRequest);
        return $this->httpResponseToInference($httpResponse);
    }

    /**
     * @return iterable<PartialInferenceResponse>
     */
    #[\Override]
    public function makeStreamResponsesFor(InferenceRequest $request): iterable {
        $httpRequest = $this->toHttpRequest($request);
        $httpResponse = $this->makeHttpResponse($httpRequest);
        return $this->httpResponseToInferenceStream($httpResponse);
    }

    #[\Override]
    public function toHttpRequest(InferenceRequest $request): HttpRequest {
        $this->events->dispatch(new InferenceRequested(['request' => $request->toArray()]));
        return $this->requestTranslator->toHttpRequest($request);
    }

    #[\Override]
    public function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse {
        try {
            $inferenceResponse = $this->responseTranslator->fromResponse($httpResponse);
            if ($inferenceResponse === null) {
                throw new RuntimeException('Failed to translate HTTP response to InferenceResponse');
            }
        } catch (Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'context' => 'Failed to process response',
                'exception' => $e->getMessage(),
                'statusCode' => $httpResponse->statusCode(),
                'headers' => $httpResponse->headers(),
                'body' => $httpResponse->body(),
            ]));
            throw $e;
        }

        $this->events->dispatch(new InferenceResponseCreated(['response' => $inferenceResponse->toArray()]));
        return $inferenceResponse;
    }

    /**
     * @return iterable<PartialInferenceResponse>
     */
    #[\Override]
    public function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable {
        try {
            $reader = new EventStreamReader(
                events: $this->events,
                parser: $this->toEventBody(...),
            );
            foreach($reader->eventsFrom($httpResponse->stream()) as $eventBody) {
                $partialResponse = $this->responseTranslator->fromStreamResponse($eventBody);
                if ($partialResponse === null) {
                    continue;
                }
                yield $partialResponse;
            }
        } catch (Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'context' => 'Failed to process streamed response',
                'exception' => $e->getMessage(),
                'statusCode' => $httpResponse->statusCode(),
                'headers' => $httpResponse->headers(),
                'body' => $httpResponse->body(),
            ]));
            throw $e;
        }
    }

    // INTERNAL //////////////////////////////////////////////

    protected function makeHttpResponse(HttpRequest $request): HttpResponse {
        try {
            $httpResponse = $this->httpClient->withRequest($request)->get();
        } catch(Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'context' => 'HTTP request sending failed',
                'exception' => $e->getMessage(),
                'request' => $request->toArray(),
            ]));
            throw $e;
        }

        if ($httpResponse->statusCode() >= 400) {
            $this->events->dispatch(new InferenceFailed([
                'context' => 'HTTP response received with error status',
                'statusCode' => $httpResponse->statusCode(),
                'headers' => $httpResponse->headers(),
                'body' => $httpResponse->body(),
            ]));
            throw new RuntimeException('HTTP request failed with status code ' . $httpResponse->statusCode());
        }
        return $httpResponse;
    }

    protected function toEventBody(string $data): string|bool {
        return $this->responseTranslator->toEventBody($data);
    }
}
