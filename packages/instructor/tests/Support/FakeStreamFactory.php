<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Support;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

final class FakeStreamFactory
{
    /**
     * @return PartialInferenceResponse[]
     */
    public static function from(PartialInferenceResponse ...$responses): array {
        $snapshot = PartialInferenceResponse::empty();
        $result = [];

        foreach ($responses as $response) {
            $snapshot = match (self::isAccumulated($response)) {
                true => $response,
                default => $response->withAccumulatedContent($snapshot),
            };
            $result[] = $snapshot;
        }

        return $result;
    }

    private static function isAccumulated(PartialInferenceResponse $response): bool {
        return $response->hasContent()
            || $response->hasReasoningContent()
            || $response->toolCalls()->hasAny();
    }
}
