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
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Utils\EventStreamReader;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

abstract class BaseInferenceDriver implements CanHandleInference
{
    protected LLMConfig $config;
    protected HttpClient $httpClient;
    protected EventDispatcherInterface $events;

    protected CanTranslateInferenceRequest $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function makeResponseFor(InferenceRequest $request) : InferenceResponse {
        $httpRequest = $this->toHttpRequest($request);
        $httpResponse = $this->makeHttpResponse($httpRequest);
        return $this->httpResponseToInference($httpResponse);
    }

    /** iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable {
        $httpRequest = $this->toHttpRequest($request);
        $httpResponse = $this->makeHttpResponse($httpRequest);
        return $this->httpResponseToInferenceStream($httpResponse);
    }

    public function toHttpRequest(InferenceRequest $request): HttpRequest {
        $this->events->dispatch(new InferenceRequested(['request' => $request->toArray()]));
        return $this->requestTranslator->toHttpRequest($request);
    }

    public function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse {
        try {
            $inferenceResponse = $this->responseTranslator->fromResponse($httpResponse);
            if ($inferenceResponse === null) {
                throw new RuntimeException('Failed to translate HTTP response to InferenceResponse');
            }
        } catch (Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'exception' => $e->getMessage(),
                'statusCode' => $httpResponse->statusCode() ?? 500,
                'headers' => $httpResponse->headers() ?? [],
                'body' => $httpResponse->body() ?? '',
            ]));
            throw $e;
        }

        $this->events->dispatch(new InferenceResponseCreated(['response' => $inferenceResponse->toArray()]));
        return $inferenceResponse;
    }

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
                'exception' => $e->getMessage(),
                'statusCode' => $httpResponse->statusCode() ?? 500,
                'headers' => $httpResponse->headers() ?? [],
                'body' => $httpResponse->body() ?? '',
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
                'exception' => $e->getMessage(),
                'request' => $request->toArray(),
            ]));
            throw $e;
        }

        if ($httpResponse->statusCode() >= 400) {
            $this->events->dispatch(new InferenceFailed([
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