<?php

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanProvideHttpClientConfig;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Utils\Config\Settings;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

class SettingsHttpClientConfigProvider implements CanProvideHttpClientConfig
{
    public function getConfig(?string $preset = ''): HttpClientConfig {
        if (empty($preset)) {
            $result = Result::try(fn() => Settings::get('http', 'defaultPreset', ''));
            $preset = $result->isSuccess() ? $result->unwrap() : '';
            if (empty($preset)) {
                return new HttpClientConfig();
            }
        }

        if (!Settings::has('http', "presets.$preset")) {
            throw new InvalidArgumentException("Unknown HTTP client preset: $preset");
        }

        return new HttpClientConfig(
            driver: Settings::get('http', "presets.$preset.httpClientDriver", 'guzzle'),
            connectTimeout: Settings::get(group: "http", key: "presets.$preset.connectTimeout", default: 30),
            requestTimeout: Settings::get("http", "presets.$preset.requestTimeout", 3),
            idleTimeout: Settings::get(group: "http", key: "presets.$preset.idleTimeout", default: 0),
            maxConcurrent: Settings::get("http", "presets.$preset.maxConcurrent", 5),
            poolTimeout: Settings::get("http", "presets.$preset.poolTimeout", 120),
            failOnError: Settings::get("http", "presets.$preset.failOnError", false),
        );
    }
}