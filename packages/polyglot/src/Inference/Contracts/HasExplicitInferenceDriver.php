<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

/**
 * Contract for resolvers/providers that can supply an explicit inference driver.
 * Facades consult this to bypass factory construction when a custom driver
 * is provided for advanced use cases.
 */
interface HasExplicitInferenceDriver
{
    /**
     * Returns an explicit inference driver or null if none set.
     */
    public function explicitInferenceDriver(): ?CanHandleInference;
}
