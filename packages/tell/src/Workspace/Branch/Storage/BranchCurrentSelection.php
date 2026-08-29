<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch\Storage;

use Cognesy\Tell\Workspace\Arena\Exception\ArenaIntegrityException;
use Cognesy\Tell\Workspace\Branch\BranchName;
use JsonException;

/** A versioned symbolic selector, intentionally separate from every branch ref. */
final readonly class BranchCurrentSelection
{
    private const int SCHEMA_VERSION = 1;

    public function __construct(public string $branch) {}

    public static function main(): self {
        return new self('main');
    }

    public static function fromBytes(string $bytes): self {
        try {
            $data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ArenaIntegrityException('Tell current branch selector is not valid JSON.', previous: $exception);
        }
        if (!is_array($data) || array_is_list($data) || array_keys($data) !== ['branch', 'schema']) {
            throw new ArenaIntegrityException('Tell current branch selector has an unsupported shape.');
        }
        if (($data['schema'] ?? null) !== self::SCHEMA_VERSION || !is_string($data['branch'])) {
            throw new ArenaIntegrityException('Tell current branch selector is invalid.');
        }
        if ($data['branch'] !== 'main') {
            BranchName::from($data['branch']);
        }
        $selector = new self($data['branch']);
        if (!hash_equals($bytes, $selector->toBytes())) {
            throw new ArenaIntegrityException('Tell current branch selector is not in the required stable representation.');
        }

        return $selector;
    }

    public function toBytes(): string {
        return json_encode(['branch' => $this->branch, 'schema' => self::SCHEMA_VERSION], JSON_THROW_ON_ERROR) . "\n";
    }
}
