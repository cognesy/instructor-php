<?php declare(strict_types=1);

namespace Cognesy\Config\Providers;

use Cognesy\Config\Contracts\CanProvideConfig;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class LaravelConfigProvider implements CanProvideConfig
{
    private ConfigRepository $config;

    public function __construct(?ConfigRepository $config = null)
    {
        if ($config === null) {
            throw new \InvalidArgumentException('Config repository must be provided');
        }
        $this->config = $config;
    }

    #[\Override]
    public function get(string $path, mixed $default = null): mixed
    {
        // Laravel uses dot notation natively, so we can pass the path directly
        return $this->config->get($path, $default);
    }

    #[\Override]
    public function has(string $path): bool
    {
        return $this->config->has($path);
    }
}