<?php

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanProvideDebugConfig;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Utils\Config\Settings;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

class SettingsDebugConfigProvider implements CanProvideDebugConfig
{
    public function getConfig(?string $preset = ''): DebugConfig {
        if (empty($preset)) {
            $result = Result::try(fn() => Settings::get('debug', 'defaultPreset', ''));
            $preset = $result->isSuccess() ? $result->unwrap() : '';
            if (empty($preset)) {
                return new DebugConfig();
            }
        }

        if (!Settings::has('debug', "presets.$preset")) {
            throw new InvalidArgumentException("Unknown debug config preset: $preset");
        }

        return new DebugConfig(
            Settings::get('debug', "presets.$preset.http_enabled", false),
            Settings::get('debug', "presets.$preset.http_trace", false),
            Settings::get('debug', "presets.$preset.http_requestUrl", true),
            Settings::get('debug', "presets.$preset.http_requestHeaders", true),
            Settings::get('debug', "presets.$preset.http_requestBody", true),
            Settings::get('debug', "presets.$preset.http_responseHeaders", true),
            Settings::get('debug', "presets.$preset.http_responseBody", true),
            Settings::get('debug', "presets.$preset.http_responseStream", true),
            Settings::get('debug', "presets.$preset.http_responseStreamByLine", true),
        );
    }
}