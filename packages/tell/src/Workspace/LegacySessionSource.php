<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Runtime\TellPaths;
use JsonException;
use Throwable;

/**
 * Reads an existing FileSessionStore document without constructing its writer.
 *
 * In particular, checking for migration never creates ~/.tell/runtime/sessions.
 */
final readonly class LegacySessionSource
{
    public function __construct(
        private TellPaths $paths,
    ) {}

    public function snapshot(SessionId $sessionId): ?LegacySessionSnapshot
    {
        $bytes = $this->bytes($sessionId);
        if ($bytes === null) {
            return null;
        }

        try {
            $data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data) || array_is_list($data)) {
                throw new WorkspaceSessionException('Tell legacy session cannot be migrated; it was left unchanged.');
            }
            $session = AgentSession::fromArray($data);
        } catch (WorkspaceSessionException $exception) {
            throw $exception;
        } catch (JsonException|Throwable $exception) {
            throw new WorkspaceSessionException(
                'Tell legacy session cannot be migrated; it was left unchanged.',
                previous: $exception,
            );
        }

        if (! $session->sessionId()->equals($sessionId)) {
            throw new WorkspaceSessionException('Tell legacy session ID does not match its selected name.');
        }

        return new LegacySessionSnapshot(
            session: $session,
            bytes: $bytes,
            fingerprint: CanonicalHash::fromBytes($bytes),
        );
    }

    public function sourceFingerprint(SessionId $sessionId): ?CanonicalHash
    {
        $bytes = $this->bytes($sessionId);

        return $bytes === null ? null : CanonicalHash::fromBytes($bytes);
    }

    private function bytes(SessionId $sessionId): ?string
    {
        $path = $this->paths->sessions.DIRECTORY_SEPARATOR.$sessionId->toString().'.json';
        if (! file_exists($path) && ! is_link($path)) {
            return null;
        }
        if (! is_file($path) || is_link($path)) {
            throw new WorkspaceSessionException('Tell legacy session cannot be read safely for migration.');
        }
        $bytes = @file_get_contents($path);
        if (! is_string($bytes)) {
            throw new WorkspaceSessionException('Tell legacy session cannot be read for migration.');
        }

        return $bytes;
    }
}
