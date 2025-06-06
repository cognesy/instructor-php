<?php

namespace Cognesy\Http\Config;

use Cognesy\Utils\Config\Providers\SettingsConfigProvider;

/**
 * @extends SettingsConfigProvider<DebugConfig>
 */
class SettingsDebugConfigProvider extends SettingsConfigProvider
{
    protected function getGroup(): string {
        return 'debug';
    }

    protected function createConfig(string $preset): DebugConfig {
        return new DebugConfig(
            $this->getSetting("presets.$preset.http_enabled", false),
            $this->getSetting("presets.$preset.http_trace", false),
            $this->getSetting("presets.$preset.http_requestUrl", true),
            $this->getSetting("presets.$preset.http_requestHeaders", true),
            $this->getSetting("presets.$preset.http_requestBody", true),
            $this->getSetting("presets.$preset.http_responseHeaders", true),
            $this->getSetting("presets.$preset.http_responseBody", true),
            $this->getSetting("presets.$preset.http_responseStream", true),
            $this->getSetting("presets.$preset.http_responseStreamByLine", true),
        );
    }
}