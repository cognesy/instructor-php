<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use Cognesy\Polyglot\BatchInference\Transport\BatchTransportUrl;
use InvalidArgumentException;

final readonly class FireworksBatchSettings
{
    public function __construct(
        private string $accountId,
        private string $apiBaseUrl = 'https://api.fireworks.ai/v1',
    ) {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $accountId) !== 1) {
            throw new InvalidArgumentException('A Fireworks account ID is required for batch inference.');
        }
        BatchTransportUrl::assertSecure($apiBaseUrl);
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function apiBaseUrl(): string
    {
        return rtrim($this->apiBaseUrl, '/');
    }

    public function scope(): string
    {
        return 'fireworks|' . $this->apiBaseUrl() . '|' . $this->accountId;
    }
}
