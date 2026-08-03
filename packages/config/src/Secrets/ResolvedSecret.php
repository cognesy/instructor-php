<?php

declare(strict_types=1);

namespace Cognesy\Config\Secrets;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class ResolvedSecret
{
    public function __construct(
        public string $name,
        #[SensitiveParameter]
        private string $value,
        public string $source,
    ) {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->name)) {
            throw new InvalidArgumentException("Invalid secret name: {$this->name}");
        }
        if ($this->source === '') {
            throw new InvalidArgumentException('Secret source cannot be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array{name: string, configured: true, source: string} */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /** @return array{name: string, configured: true, source: string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'configured' => true,
            'source' => $this->source,
        ];
    }
}
