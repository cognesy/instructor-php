<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\DriverCapabilities;

/**
 * Optional capability of inference drivers: describe what the underlying
 * provider/model supports. Split from CanProcessInferenceRequest (2.5) —
 * request processing does not require capability introspection, and only
 * capability-aware consumers (e.g. evals) should depend on it.
 */
interface CanDescribeCapabilities
{
    /**
     * Get driver capabilities, optionally for a specific model.
     *
     * The model parameter allows model-specific capability checks, e.g.,
     * deepseek-reasoner has different capabilities than deepseek-chat.
     *
     * If model is null, capabilities for the configured default model are returned.
     */
    public function capabilities(?string $model = null): DriverCapabilities;
}
