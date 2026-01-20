<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction;

use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use JsonException;

final class ResponseContent
{
    public static function fromResponse(InferenceResponse $response, OutputMode $mode): string
    {
        return match ($mode) {
            OutputMode::Tools => self::fromToolCalls($response),
            default => $response->content(),
        };
    }

    private static function fromToolCalls(InferenceResponse $response): string
    {
        $toolCalls = $response->toolCalls();
        if ($toolCalls->isEmpty()) {
            return $response->content();
        }

        try {
            if ($toolCalls->hasSingle()) {
                return json_encode($toolCalls->first()?->args() ?? [], JSON_THROW_ON_ERROR);
            }

            return json_encode($toolCalls->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ExtractionException('Tool call arguments could not be encoded', $e);
        }
    }
}
