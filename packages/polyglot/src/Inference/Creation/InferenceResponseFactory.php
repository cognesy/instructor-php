<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Creation;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

class InferenceResponseFactory
{
    public static function fromAccumulatedPartial(PartialInferenceResponse $partial): InferenceResponse {
        return new InferenceResponse(
            content: $partial->content(),
            finishReason: $partial->finishReason(),
            toolCalls: $partial->toolCalls(),
            reasoningContent: $partial->reasoningContent(),
            usage: $partial->usage(),
            responseData: $partial->responseData,
            isPartial: false,
        );
    }
}
