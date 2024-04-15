<?php

namespace Cognesy\Instructor\ApiClient;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\FlysystemDriver;

class CacheConfig
{
    protected Filesystem $filesystem;

    public function __construct(
        private bool $enabled,
        private int $expiryInSeconds,
        private string $cachePath,
    ) {
        $this->makeCacheDir($this->cachePath);
        $adapter = new LocalFilesystemAdapter($this->cachePath);
        $this->filesystem = new Filesystem($adapter);
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function expiryInSeconds(): int {
        return $this->expiryInSeconds;
    }

    public function getDriver() : Driver {
        return new FlysystemDriver($this->filesystem);
    }

    private function makeCacheDir(string $cachePath) : void {
        if (!is_dir($cachePath)) {
            if (!mkdir($cachePath, 0777, true) && !is_dir($cachePath)) {
                throw new \RuntimeException(sprintf('Cache directory "%s" was not created', $cachePath));
            }
        }
    }
}