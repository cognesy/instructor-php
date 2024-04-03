<?php

namespace Cognesy\Instructor\ApiClient\Data\Requests;

use Cognesy\Instructor\Utils\Json;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\FlysystemDriver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class ApiRequest extends Request implements HasBody, Cacheable
{
    use HasJsonBody, HasCaching;

    protected Method $method = Method::POST;
    protected Filesystem $filesystem;

    public function __construct(
        protected array $payload,
        protected string $endpoint,
    ) {
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
        $adapter = new LocalFilesystemAdapter(
            dirname(__DIR__, 4) . '/.cache/saloonphp'
        );
        $this->filesystem = new Filesystem($adapter);
    }

    public function getEndpoint(): string {
        return $this->endpoint;
    }

    public function isStreamed(): bool {
        return $this->options['stream'] ?? false;
    }

    public function resolveEndpoint() : string {
        return $this->endpoint;
    }

    protected function defaultBody(): array {
        return $this->payload;
    }

    public function resolveCacheDriver(): Driver {
        return new FlysystemDriver($this->filesystem);
    }

    public function cacheExpiryInSeconds(): int {
        return 1000;
    }

    protected function getCacheableMethods(): array {
        return [Method::GET, Method::OPTIONS, Method::POST];
    }

    protected function cacheKey(PendingRequest $pendingRequest): string {
        return md5(get_class($this) . Json::encode($this->defaultBody()));
    }
}