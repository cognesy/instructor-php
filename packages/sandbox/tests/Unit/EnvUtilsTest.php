<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Utils\EnvUtils;

describe('EnvUtils', function () {
    it('returns explicit env vars from policy', function () {
        $policy = ExecutionPolicy::default()->withEnv(['APP_ENV' => 'test', 'DEBUG' => '1']);
        $env = EnvUtils::build($policy, []);

        expect($env)->toBe(['APP_ENV' => 'test', 'DEBUG' => '1']);
    });

    it('filters out forbidden patterns', function () {
        $policy = ExecutionPolicy::default()->withEnv([
            'APP_ENV' => 'test',
            'LD_PRELOAD' => '/evil.so',
            'AWS_ACCESS_KEY_ID' => 'secret',
        ]);
        $env = EnvUtils::build($policy, EnvUtils::forbiddenEnvVars());

        expect($env)->toHaveKey('APP_ENV');
        expect($env)->not->toHaveKey('LD_PRELOAD');
        expect($env)->not->toHaveKey('AWS_ACCESS_KEY_ID');
    });

    it('filters using wildcard patterns', function () {
        $policy = ExecutionPolicy::default()->withEnv([
            'NPM_TOKEN' => 'x',
            'NPM_CONFIG' => 'y',
            'SAFE_VAR' => 'z',
        ]);
        $env = EnvUtils::build($policy, ['NPM_*']);

        expect($env)->toBe(['SAFE_VAR' => 'z']);
    });

    it('returns empty array when policy has no env and no inheritance', function () {
        $policy = ExecutionPolicy::default();
        $env = EnvUtils::build($policy, []);

        expect($env)->toBe([]);
    });
});
