<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Session;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Arena\Record\SessionMetadata;

/**
 * A deterministic, opaque arena ref for a user-facing session selector.
 */
final readonly class SessionRef
{
    private const string REF_DOMAIN = "tell-session-ref-v1\0";

    public function __construct(
        private SessionId $session,
    ) {}

    public function session(): SessionId {
        return $this->session;
    }

    public function refName(): string {
        return 'sessions/' . hash(self::hashAlgorithm(), self::REF_DOMAIN . $this->session->toString());
    }

    public function metadata(?ObjectHash $sourceFingerprint = null): SessionMetadata {
        return new SessionMetadata(
            name: $this->session->toString(),
            sourceFingerprint: $sourceFingerprint,
        );
    }

    private static function hashAlgorithm(): string {
        return ObjectHash::ALGORITHM;
    }
}
