<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Http\Config\HttpClientConfig;

class HttpClientConfigFactory
{
    private ConfigPresets $presets;

    public function __construct(
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->presets = ConfigPresets::using($configProvider)->for(HttpClientConfig::group());
    }

    public function default(): HttpClientConfig {
        $data = $this->presets->default();
        return HttpClientConfig::fromArray($data);
    }

    public function forPreset(string $preset): HttpClientConfig {
        $data = $this->presets->get($preset);
        return HttpClientConfig::fromArray($data);
    }
}