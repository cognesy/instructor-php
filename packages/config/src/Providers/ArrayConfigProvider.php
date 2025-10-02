<?php declare(strict_types=1);

namespace Cognesy\Config\Providers;

use Adbar\Dot;
use Cognesy\Config\Contracts\CanProvideConfig;

class ArrayConfigProvider implements CanProvideConfig
{
    private Dot $dot;

    public function __construct(array $config = [])
    {
        $this->dot = new Dot($config);
    }

    #[\Override]
    public function get(string $path, mixed $default = null): mixed
    {
        return $this->dot->get($path, $default);
    }

    #[\Override]
    public function has(string $path): bool
    {
        return $this->dot->has($path);
    }

    public function set(string $path, mixed $value): void
    {
        $this->dot->set($path, $value);
    }

    public function all(): array
    {
        return $this->dot->all();
    }
}