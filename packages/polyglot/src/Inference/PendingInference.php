<?php

namespace Cognesy\Polyglot\Inference;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Json\Json;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Represents an inference response handling object that processes responses
 * based on the configuration and streaming state. Provides methods to
 * retrieve the response in different formats. PendingInference does not
 * execute any request to the underlying LLM API until the data is accessed
 * via its methods (`get()`, `response()`).
 */
class PendingInference
{
    protected readonly CanHandleInference $driver;
    protected readonly EventDispatcherInterface $events;
    protected readonly InferenceRequest $request;

    protected HttpResponse $httpResponse;
    protected string $responseContent = '';

    public function __construct(
        InferenceRequest         $request,
        CanHandleInference       $driver,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->request = $request;
        $this->events = $eventDispatcher;
        $this->driver = $driver;
    }

    /**
     * Determines whether the content is streamed.
     *
     * @return bool True if the content is being streamed, false otherwise.
     */
    public function isStreamed() : bool {
        return $this->request->isStreamed();
    }

    /**
     * Converts the response to its text representation.
     *
     * @return string The textual representation of the response. If streaming, retrieves the final content; otherwise, retrieves the standard content.
     */
    public function get() : string {
        return $this->response()->content();
    }

    /**
     * Initiates and returns an inference stream for the response.
     *
     * @return InferenceStream The initialized inference stream.
     * @throws InvalidArgumentException If the response is not configured for streaming.
     */
    public function stream() : InferenceStream {
        if (!$this->isStreamed()) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }

        return new InferenceStream(
            request: $this->request,
            driver: $this->driver,
            eventDispatcher: $this->events,
        );
    }

    // AS API RESPONSE OBJECT ///////////////////////////////////

    /**
     * Converts the response content to a JSON representation.
     *
     * @return array The JSON representation of the content as an associative array.
     */
    public function asJson() : string {
        return Json::fromString($this->get())->toString();
    }

    /**
     * Converts the response content to a JSON representation.
     *
     * @return array The JSON representation of the content as an associative array.
     */
    public function asJsonData() : array {
        return Json::fromString($this->get())->toArray();
    }

    /**
     * Generates and returns an InferenceResponse based on the streaming status.
     *
     * @return InferenceResponse The constructed InferenceResponse object, either fully or from partial responses if streaming is enabled.
     */
    public function response() : InferenceResponse {
        $response = match($this->isStreamed()) {
            false => $this->driver->makeResponseFor($this->request), // $this->tryMakeInferenceResponse(),
            true => InferenceResponse::fromPartialResponses($this->stream()->all()),
        };
        return $response;
    }

    // INTERNAL /////////////////////////////////////////////////

//    private function tryMakeInferenceResponse() : InferenceResponse {
//        try {
//            return $this->makeInferenceResponse();
//        } catch (Exception $e) {
//            $this->events->dispatch(new InferenceFailed([
//                'exception' => $e->getMessage(),
//                'statusCode' => $this->httpResponse()->statusCode() ?? 500,
//                'headers' => $this->httpResponse()->headers() ?? [],
//                'body' => $this->httpResponseBody() ?? '',
//            ]));
//            throw $e;
//        }
//    }
//
//    private function makeInferenceResponse() : InferenceResponse {
//        $inferenceResponse = $this->driver->fromResponse($this->httpResponse());
//        $this->events->dispatch(new InferenceResponseCreated(['response' => $inferenceResponse?->toArray() ?? []]));
//        return $inferenceResponse;
//    }
//
//    private function httpResponseBody() : string {
//        if (empty($this->responseContent)) {
//            $this->responseContent = $this->httpResponse()->body();
//        }
//        return $this->responseContent;
//    }
//
//    private function httpResponse() : HttpResponse {
//        if (!isset($this->httpResponse)) {
//            $this->events->dispatch(new InferenceRequested(['request' => $this->request->toArray()]));
//            $this->httpResponse = $this->driver->handle($this->request);
//        }
//        return $this->httpResponse;
//    }
}