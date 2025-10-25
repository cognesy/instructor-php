<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Utils;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

final class EnvUtils
{
    /**
     * Build environment according to policy and forbidden patterns.
     *
     * @param list<string> $forbiddenPatterns fnmatch() patterns
     */
    public static function build(ExecutionPolicy $policy, array $forbiddenPatterns): array {
        $env = $policy->inheritEnv() ? self::readSystemEnv() : [];
        foreach ($policy->env() as $k => $v) {
            $env[(string)$k] = (string)$v;
        }
        if (empty($env)) {
            return [];
        }
        $out = [];
        foreach ($env as $k => $v) {
            $key = strtoupper((string)$k);
            $blocked = false;
            foreach ($forbiddenPatterns as $pat) {
                if (fnmatch($pat, $key)) {
                    $blocked = true;
                    break;
                }
            }
            if (!$blocked) {
                $out[(string)$k] = (string)$v;
            }
        }
        return $out;
    }

    public static function forbiddenEnvVars() : array {
        return [
            'LD_PRELOAD', 'LD_LIBRARY_PATH', 'LD_AUDIT',
            'DYLD_INSERT_LIBRARIES', 'DYLD_LIBRARY_PATH', 'DYLD_FRAMEWORK_PATH',
            'PHP_INI_SCAN_DIR', 'PHPRC',
            'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_SESSION_TOKEN',
            'GOOGLE_APPLICATION_CREDENTIALS', 'GCP_*',
            'AZURE_CLIENT_ID', 'AZURE_CLIENT_SECRET',
            'GEM_HOME', 'GEM_PATH', 'RUBY*',
            'NODE_OPTIONS', 'NPM_*',
            'PYTHON*', 'PIP_*',
        ];
    }

    private static function readSystemEnv(): array {
        $out = [];
        foreach ($_ENV as $k => $v) {
            $out[$k] = is_string($v) ? $v : (string)$v;
        }
        foreach ($_SERVER as $k => $v) {
            if (is_string($v) && !array_key_exists($k, $out)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}

