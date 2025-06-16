<?php

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;

/**
 * StreamedRequestRecord
 * 
 * A specialized value object for handling streamed HTTP interactions.
 * Focuses on properly capturing and replaying chunked/streamed responses.
 */
class StreamedRequestRecord extends RequestRecord
{
    /**
     * @var array Chunks from the streamed response
     */
    private array $chunks = [];
    
    /**
     * Constructor
     * 
     * @param array $requestData Request data
     * @param array $responseData Response data
     * @param array $chunks Response chunks
     */
    public function __construct(array $requestData, array $responseData, array $chunks = [])
    {
        parent::__construct($requestData, $responseData);
        $this->chunks = $chunks;
    }
    
    /**
     * Create a new StreamedRequestRecord from a request and streamed response
     * 
     * @param HttpRequest $request The HTTP request
     * @param HttpResponse $response The streamed HTTP response
     * @return self
     */
    public static function fromStreamedInteraction(HttpRequest $request, HttpResponse $response): self
    {
        $requestData = [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
            'options' => $request->options(),
        ];
        
        // Collect all chunks from the stream
        $chunks = [];
        $body = '';
        
        // Clone the generator to avoid consuming the original
        foreach ($response->stream() as $chunk) {
            $chunks[] = $chunk;
            $body .= $chunk;
        }
        
        $responseData = [
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $body, // Store the full body too for non-streaming access
        ];
        
        return new self($requestData, $responseData, $chunks);
    }
    
    /**
     * Create a StreamedRequestRecord from JSON string
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
        
        $chunks = $data['chunks'] ?? [];
        
        return new self($data['request'], $data['response'], $chunks);
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
            'request' => $this->getRequestData(),
            'response' => $this->getResponseData(),
            'chunks' => $this->chunks,
        ];
        
        return json_encode($data, $prettyPrint ? JSON_PRETTY_PRINT : 0);
    }
    
    /**
     * Create an HttpClientResponse from this record
     * 
     * @param bool $isStreaming Whether to return a streaming response
     * @return HttpResponse
     */
    public function toResponse(bool $isStreaming = true): HttpResponse
    {
        if ($isStreaming) {
            return MockHttpResponse::streaming(
                $this->getStatusCode(),
                $this->getResponseHeaders(),
                $this->chunks,
            );
        }
        
        return MockHttpResponse::success(
            $this->getStatusCode(),
            $this->getResponseHeaders(),
            $this->getResponseBody(),
            $this->chunks,
        );
    }
    
    /**
     * Get the response chunks
     * 
     * @return array
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }
    
    /**
     * Get chunk count
     * 
     * @return int
     */
    public function getChunkCount(): int
    {
        return count($this->chunks);
    }
    
    /**
     * Check if this record has chunks
     * 
     * @return bool
     */
    public function hasChunks(): bool
    {
        return !empty($this->chunks);
    }
    
    /**
     * Factory method to create the appropriate RequestRecord type based on request
     * 
     * @param HttpRequest $request The HTTP request
     * @param HttpResponse $response The HTTP response
     * @return RequestRecord Either a StreamedRequestRecord or standard RequestRecord
     */
    public static function createAppropriateRecord(
        HttpRequest  $request,
        HttpResponse $response
    ): RequestRecord {
        if ($request->isStreamed()) {
            return self::fromStreamedInteraction($request, $response);
        }
        
        return RequestRecord::fromInteraction($request, $response);
    }
    
    /**
     * Get request data (for internal use)
     * 
     * @return array
     */
    protected function getRequestData(): array
    {
        // Access request data via reflection or other mechanism
        // This is a simplified implementation
        return [
            'url' => $this->getUrl(),
            'method' => $this->getMethod(),
            'body' => $this->getRequestBody(),
            // Add other request data as needed
        ];
    }
    
    /**
     * Get response data (for internal use)
     * 
     * @return array
     */
    protected function getResponseData(): array
    {
        // Access response data via reflection or other mechanism
        // This is a simplified implementation
        return [
            'statusCode' => $this->getStatusCode(),
            'body' => $this->getResponseBody(),
            'headers' => $this->getResponseHeaders(),
        ];
    }
    
    /**
     * Get the response headers
     * 
     * @return array
     */
    public function getResponseHeaders(): array
    {
        // This method would need to be implemented in the parent class as well
        // This is a simplified implementation
        return [];
    }
}
