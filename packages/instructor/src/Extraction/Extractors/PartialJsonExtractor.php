<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Utils\Json\Json;
use Throwable;

class PartialJsonExtractor implements CanExtractResponse
{
    #[\Override]
    public function extract(ExtractionInput $input): array
    {
        $trimmed = trim($input->content);
        if ($trimmed === '') {
            throw new ExtractionException('Empty content');
        }

        $hasBraces = str_contains($trimmed, '{') || str_contains($trimmed, '[');
        if (!$hasBraces) {
            throw new ExtractionException('No JSON structure found');
        }

        try {
            $parsed = Json::fromPartial($trimmed)->toArray();
        } catch (Throwable $e) {
            throw new ExtractionException("Partial JSON parsing failed: {$e->getMessage()}", $e);
        }

        if (!is_array($parsed)) {
            throw new ExtractionException('Partial JSON parsing produced scalar');
        }

        return $parsed;
    }

    #[\Override]
    public function name(): string
    {
        return 'partial_json';
    }
}
