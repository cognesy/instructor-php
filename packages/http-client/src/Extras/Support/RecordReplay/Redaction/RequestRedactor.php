<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Redaction;

/**
 * Masks sensitive material in captured request data before it is written to disk.
 *
 * The volatile decision hidden here is *what must never be persisted* — provider
 * API keys and auth tokens today (see {@see DefaultRequestRedactor}), possibly
 * known sensitive body fields later. Applied at the persistence boundary
 * (RequestRecords::save), so no recording can carry a live credential to disk or
 * into git.
 */
interface RequestRedactor
{
    /**
     * Return a copy of the captured request data with sensitive values masked.
     *
     * @param array{url?: string, method?: string, headers?: array<string, mixed>, body?: string, options?: array<string, mixed>} $requestData
     * @return array<string, mixed>
     */
    public function redact(array $requestData): array;
}
