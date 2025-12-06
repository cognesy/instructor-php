<?php

declare(strict_types=1);

namespace Cognesy\Logging\Contracts;

/**
 * Provides contextual data for log enrichment
 */
interface ContextProvider
{
    /**
     * Get context data to add to logs
     *
     * @return array Context data array
     */
    public function getContext(): array;
}