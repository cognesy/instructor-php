<?php

namespace Cognesy\Instructor\ApiClient;

use Closure;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\FlysystemDriver;
use Saloon\Http\PendingRequest;
use Saloon\Enums\Method;

class CacheConfig
{
    protected Filesystem $filesystem;

    public function __construct(
        private bool $enabled = true,
        private int $expiryInSeconds = 25200,
        private string $cachePath = '/tmp/instructor/cache',
        private array $cacheableMethods = [],
        private ?Closure $makeCacheKey = null,
    ) {
        $this->makeCacheDir($this->cachePath);
        $adapter = new LocalFilesystemAdapter($this->cachePath);
        $this->filesystem = new Filesystem($adapter);
    }

    public function setEnabled(bool $enabled = true) : void {
        $this->enabled = $enabled;
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

    public function cacheableMethods(): array {
        return $this->cacheableMethods ?: [
            Method::GET,
            Method::OPTIONS,
            Method::POST
        ];
    }

    public function cacheKey(PendingRequest $pendingRequest): string {
        if ($this->makeCacheKey) {
            return ($this->makeCacheKey)($pendingRequest);
        }
        return $this->defaultCacheKey($pendingRequest);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////////////////

    private function makeCacheDir(string $cachePath) : void {
        if (!is_dir($cachePath)) {
            if (!mkdir($cachePath, 0777, true) && !is_dir($cachePath)) {
                throw new \RuntimeException(sprintf('Cache directory "%s" was not created', $cachePath));
            }
        }
    }

    private function defaultCacheKey(PendingRequest $pendingRequest): string {
        $keyBase = implode('|', [
            get_class($pendingRequest->getConnector()),
            $pendingRequest->getUrl(),
            json_encode($pendingRequest->body()),
        ]);
        return md5($keyBase);
    }
}