<?php
namespace Cognesy\Instructor\Clients\Anyscale\JsonCompletion;

use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;

class PartialJsonCompletionResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        $decoded = json_decode($partialData, true);
        $decoded = (empty($decoded)) ? [] : $decoded;
        $delta = $decoded['choices'][0]['delta']['content'] ?? '';
        return new self($delta, $decoded, '');
    }
}