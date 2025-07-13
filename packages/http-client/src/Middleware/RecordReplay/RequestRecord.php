<?php

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;

/**
 * RequestRecord
 * 
 * A value object representing a single recorded HTTP interaction (request and response).
 */
class RequestRecord
{
    /**
     * @var array Request data
     */
    private array $requestData;
    
    /**
     * @var array Response data
     */
    private array $responseData;
    
    /**
     * Constructor
     * 
     * @param array $requestData Request data
     * @param array $responseData Response data
     */
    public function __construct(array $requestData, array $responseData)
    {
        $this->requestData = $requestData;
        $this->responseData = $responseData;
    }
    
    /**
     * Create a new RequestRecord from a request and response
     * 
     * @param HttpRequest $request The HTTP request
     * @param \Cognesy\Http\Contracts\HttpResponse $response The HTTP response
     * @return self
     */
    public static function fromInteraction(HttpRequest $request, HttpResponse $response): self
    {
        // For streamed requests, use the specialized StreamedRequestRecord
        if ($request->isStreamed()) {
            return StreamedRequestRecord::fromStreamedInteraction($request, $response);
        }
        
        $requestData = [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
            'options' => $request->options(),
        ];
        
        $responseData = [
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ];
        
        return new self($requestData, $responseData);
    }
    
    /**
     * Create a RequestRecord from JSON string
     * 
     * @param string $json JSON string
     * @return self|null
     */
    public static function fromJson(string $json): ?self
    {
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['request']) || !isset($data['response'])) {
            return null;
        }
        
        // If chunks exist, create a StreamedRequestRecord
        if (isset($data['chunks'])) {
            return StreamedRequestRecord::fromJson($json);
        }
        
        return new self($data['request'], $data['response']);
    }
    
    /**
     * Convert record to JSON string
     * 
     * @param bool $prettyPrint Whether to pretty print the JSON
     * @return string
     */
    public function toJson(bool $prettyPrint = true): string
    {
        $data = [
            'request' => $this->requestData,
            'response' => $this->responseData,
        ];
        
        return json_encode($data, $prettyPrint ? JSON_PRETTY_PRINT : 0);
    }
    
    /**
     * Check if this record matches the given request
     * 
     * @param HttpRequest $request Request to compare
     * @return bool
     */
    public function matches(HttpRequest $request): bool
    {
        // Match basic request properties
        if ($this->requestData['url'] !== $request->url()) {
            return false;
        }
        
        if ($this->requestData['method'] !== $request->method()) {
            return false;
        }
        
        // Match body (if not empty)
        $requestBody = $request->body()->toString();
        if (!empty($requestBody) && !empty($this->requestData['body']) && $this->requestData['body'] !== $requestBody) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Create an HttpResponse from this record
     * 
     * @param bool $isStreaming Whether to return a streaming response
     * @return \Cognesy\Http\Contracts\HttpResponse
     */
    public function toResponse(bool $isStreaming = false): HttpResponse
    {
        // Non-streaming records should always return non-streaming responses
        return MockHttpResponse::success(
            $this->responseData['statusCode'] ?? 200,
            $this->responseData['headers'] ?? [],
            $this->responseData['body'] ?? '',
        );
    }
    
    /**
     * Check if this record represents a streamed response
     * 
     * @return bool
     */
    public function isStreamed(): bool
    {
        return $this instanceof StreamedRequestRecord;
    }
    
    /**
     * Factory method to create the appropriate record type based on the request
     * 
     * @param HttpRequest $request
     * @param \Cognesy\Http\Contracts\HttpResponse $response
     * @return RequestRecord
     */
    public static function createAppropriate(HttpRequest $request, HttpResponse $response): RequestRecord
    {
        if ($request->isStreamed()) {
            return StreamedRequestRecord::fromStreamedInteraction($request, $response);
        }
        
        return self::fromInteraction($request, $response);
    }
    
    /**
     * Get the request URL
     * 
     * @return string
     */
    public function getUrl(): string
    {
        return $this->requestData['url'] ?? '';
    }
    
    /**
     * Get the request method
     * 
     * @return string
     */
    public function getMethod(): string
    {
        return $this->requestData['method'] ?? '';
    }
    
    /**
     * Get the request body
     * 
     * @return string
     */
    public function getRequestBody(): string
    {
        return $this->requestData['body'] ?? '';
    }
    
    /**
     * Get the response body
     * 
     * @return string
     */
    public function getResponseBody(): string
    {
        return $this->responseData['body'] ?? '';
    }
    
    /**
     * Get the response status code
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->responseData['statusCode'] ?? 200;
    }
    
    /**
     * Get the response headers
     * 
     * @return array
     */
    public function getResponseHeaders(): array
    {
        return $this->responseData['headers'] ?? [];
    }
    
    /**
     * Get the request data array
     * 
     * @return array
     */
    protected function getRequestData(): array
    {
        return $this->requestData;
    }
    
    /**
     * Get the response data array
     * 
     * @return array
     */
    protected function getResponseData(): array
    {
        return $this->responseData;
    }
}
