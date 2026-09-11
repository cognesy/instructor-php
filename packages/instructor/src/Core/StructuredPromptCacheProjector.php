<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Data\StructuredPromptPlan;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;

final class StructuredPromptCacheProjector
{
    public function project(StructuredPromptPlan $plan): CachedInferenceContext
    {
        return $this->projectMessages($plan->toCachedMessages());
    }

    public function projectMessages(Messages $messages, ?string $ttl = null): CachedInferenceContext
    {
        if ($messages->isEmpty()) {
            return new CachedInferenceContext(ttl: $ttl);
        }

        return new CachedInferenceContext(messages: $messages, ttl: $ttl);
    }
}
