<?php

namespace Middleware\MiddlewareResponseDecorator;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;
use Generator;

class JsonStreamDecorator extends BaseResponseDecorator
{
    private string $buffer = '';

    public function __construct(
        HttpRequest $request,
        HttpResponse $response
    ) {
        parent::__construct($request, $response);
    }

    public function stream(int $chunkSize = 1): Generator
    {
        foreach ($this->response->stream($chunkSize) as $chunk) {
            // Add the chunk to our buffer
            $this->buffer .= $chunk;

            // Process complete JSON objects
            $result = $this->processBuffer();

            // Yield the original chunk (or modified if needed)
            yield $chunk;
        }
    }

    private function processBuffer(): void
    {
        // Keep processing until we can't find any more complete JSON objects
        while (($jsonEnd = strpos($this->buffer, '}')) !== false) {
            // Try to find the start of the JSON object
            $jsonStart = strpos($this->buffer, '{');

            if ($jsonStart === false || $jsonStart > $jsonEnd) {
                // Invalid JSON, discard up to the end
                $this->buffer = substr($this->buffer, $jsonEnd + 1);
                continue;
            }

            // Extract the potential JSON string
            $jsonString = substr($this->buffer, $jsonStart, $jsonEnd - $jsonStart + 1);

            // Try to decode it
            $data = json_decode($jsonString, true);

            if ($data !== null) {
                // We found a valid JSON object!
                // You could process it here or dispatch an event

                // Remove the processed part from the buffer
                $this->buffer = substr($this->buffer, $jsonEnd + 1);
            } else {
                // Invalid JSON, it might be incomplete
                // Keep waiting for more data
                break;
            }
        }
    }
}
