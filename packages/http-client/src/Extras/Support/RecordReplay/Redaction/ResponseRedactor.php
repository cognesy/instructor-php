<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Redaction;

/**
 * Redacts response material before a recording is persisted.
 *
 * The optional interface keeps existing request-only redactors compatible while
 * allowing the default policy to protect response headers and bodies as well.
 */
interface ResponseRedactor
{
    /** @param array<string, mixed> $responseData */
    public function redactResponse(array $responseData): array;
}
