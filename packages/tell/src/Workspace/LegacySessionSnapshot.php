<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Tell\Canonical\CanonicalHash;

/**
 * A read-only legacy FileSessionStore document and its exact source digest.
 */
final readonly class LegacySessionSnapshot
{
    public function __construct(
        public AgentSession $session,
        public string $bytes,
        public CanonicalHash $fingerprint,
    ) {}
}
