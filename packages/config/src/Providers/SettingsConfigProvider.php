<?php

namespace Cognesy\Config\Providers;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\MissingSettingException;
use Cognesy\Config\Exceptions\NoSettingsFileException;
use Cognesy\Config\Settings;

class SettingsConfigProvider implements CanProvideConfig
{
    public function __construct(?string $configPath = null) {
        if ($configPath !== null) {
            Settings::setPath($configPath);
        }
    }

    public function get(string $path, mixed $default = null): mixed {
        [$group, $key] = $this->parsePath($path);

        try {
            // If no key specified, get the entire group
            if (empty($key)) {
                $groupData = Settings::getGroup($group);
                // Convert dot object back to array if needed
                return is_object($groupData) && method_exists($groupData, 'all')
                    ? $groupData->all()
                    : $groupData;
            }

            // Get specific key from group
            return Settings::get($group, $key, $default);

        } catch (NoSettingsFileException | MissingSettingException $e) {
            return $default;
        }
    }

    public function has(string $path): bool {
        [$group, $key] = $this->parsePath($path);

        try {
            // If no key specified, check if group exists
            if (empty($key)) {
                return Settings::hasGroup($group);
            }

            // Check if specific key exists in group
            return Settings::has($group, $key);

        } catch (NoSettingsFileException $e) {
            return false;
        }
    }

    private function parsePath(string $path): array {
        $parts = explode('.', $path, 2);
        $group = $parts[0];
        $key = $parts[1] ?? '';

        return [$group, $key];
    }
}