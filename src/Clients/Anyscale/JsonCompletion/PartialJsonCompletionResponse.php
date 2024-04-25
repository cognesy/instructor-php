<?php
namespace Cognesy\Instructor\Clients\Anyscale\JsonCompletion;

use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;

class PartialJsonCompletionResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        $decoded = Json::parse($partialData, default: []);
        $delta = $decoded['choices'][0]['delta']['content'] ?? '';
        return new self($delta, $decoded);
    }
}