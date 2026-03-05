<?php declare(strict_types=1);

namespace Cognesy\Config\Tests\Feature;

use Cognesy\Config\Config;
use Cognesy\Config\ConfigLoader;
use Cognesy\Config\Env;
use InvalidArgumentException;

it('loads yaml file as raw array', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );

    $entry = Config::fromPaths(dirname($path))->load(basename($path));

    expect($entry->key())->toBe('polyglot.llm.connections.openai');
    expect($entry->toArray())->toBe([
        'driver' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);
});

it('loads php file as raw array', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.php',
        "<?php\nreturn ['driver' => 'openai', 'model' => 'gpt-4o-mini'];\n",
    );

    $entry = Config::fromPaths(dirname($path))->load(basename($path));

    expect($entry->key())->toBe('polyglot.llm.connections.openai');
    expect($entry->toArray())->toBe([
        'driver' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);
});

it('uses single-file cache and refreshes on source change', function () {
    $tmp = makeTmpConfigDir();
    $source = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );
    $cache = $tmp . '/var/cache/openai-config.php';

    $first = Config::fromPaths(dirname($source))
        ->withCache($cache)
        ->load(basename($source))
        ->toArray();

    expect($first['model'])->toBe('gpt-4o-mini');
    expect(is_file($cache))->toBeTrue();

    usleep(1100000);
    writeFile($source, "driver: openai\nmodel: gpt-5\n");

    $second = Config::fromPaths(dirname($source))
        ->withCache($cache)
        ->load(basename($source))
        ->toArray();

    expect($second['model'])->toBe('gpt-5');
});

it('loads relative config files from configured base paths', function () {
    $tmp = makeTmpConfigDir();
    $polyglotBase = $tmp . '/packages/polyglot/resources/config';
    $httpBase = $tmp . '/packages/http-client/resources/config';

    writeFile($polyglotBase . '/llm/openai.yml', "driver: openai\nmodel: gpt-4o-mini\n");
    writeFile($httpBase . '/http/guzzle.yml', "driver: guzzle\nrequest_timeout: 30\n");

    $config = Config::fromPaths($polyglotBase, $httpBase);

    $llm = $config->load('llm/openai.yml')->toArray();
    $http = $config->load('http/guzzle.yml')->toArray();

    expect($llm['driver'])->toBe('openai');
    expect($http['driver'])->toBe('guzzle');
});

it('uses first matching base path when file exists in multiple roots', function () {
    $tmp = makeTmpConfigDir();
    $firstBase = $tmp . '/packages/first/resources/config';
    $secondBase = $tmp . '/packages/second/resources/config';

    writeFile($firstBase . '/llm/openai.yml', "driver: openai\nmodel: first-model\n");
    writeFile($secondBase . '/llm/openai.yml', "driver: openai\nmodel: second-model\n");

    $entry = Config::fromPaths($firstBase, $secondBase)->load('llm/openai.yml');

    expect($entry->toArray()['model'])->toBe('first-model');
    expect($entry->sourcePath())->toContain('/packages/first/resources/config/llm/openai.yml');
});

it('requires relative path when configured with base directory', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/packages/polyglot/resources/config';
    writeFile($basePath . '/llm/openai.yml', "driver: openai\n");

    expect(fn () => Config::fromPaths($basePath)->load(''))
        ->toThrow(InvalidArgumentException::class, 'Config::load() requires a relative config file path');
});

it('rejects absolute config paths', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/packages/polyglot/resources/config';
    writeFile($basePath . '/llm/openai.yml', "driver: openai\n");

    expect(fn () => Config::fromPaths($basePath)->load('/llm/openai.yml'))
        ->toThrow(InvalidArgumentException::class, 'Config::load() accepts relative paths only');
});

it('rejects path traversal segments', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/packages/polyglot/resources/config';
    writeFile($basePath . '/llm/openai.yml', "driver: openai\n");

    expect(fn () => Config::fromPaths($basePath)->load('../outside.yaml'))
        ->toThrow(InvalidArgumentException::class, 'Config path traversal is not allowed');
});

it('rejects empty constructor paths', function () {
    expect(fn () => new Config([]))
        ->toThrow(InvalidArgumentException::class, 'Config requires at least one existing base path');
});

it('ignores unresolved base paths when at least one base path exists', function () {
    $tmp = makeTmpConfigDir();
    $existingBasePath = $tmp . '/packages/polyglot/resources/config';
    writeFile($existingBasePath . '/llm/openai.yml', "driver: openai\n");

    $entry = Config::fromPaths(
        $tmp . '/packages/missing/resources/config',
        $existingBasePath,
    )->load('llm/openai.yml');

    expect($entry->toArray()['driver'])->toBe('openai');
});

it('throws when no provided base path exists', function () {
    $tmp = makeTmpConfigDir();

    expect(fn () => Config::fromPaths(
        $tmp . '/packages/missing-a/resources/config',
        $tmp . '/packages/missing-b/resources/config',
    ))
        ->toThrow(InvalidArgumentException::class, 'Config requires at least one existing base path');
});

it('loads one config via ConfigLoader', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/config';

    writeFile(
        $basePath . '/polyglot/llm/connections/openai.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );
    $loader = ConfigLoader::fromPaths($basePath);
    $llm = $loader->load('polyglot/llm/connections/openai.yaml')->toArray();
    expect($llm['driver'])->toBe('openai');
    expect($llm['model'])->toBe('gpt-4o-mini');
});

it('loads multiple configs via ConfigLoader::loadAll', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/config';

    writeFile(
        $basePath . '/polyglot/llm/connections/openai.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );
    writeFile(
        $basePath . '/http-client/http/profiles/curl.yaml',
        "driver: curl\nrequest_timeout: 30\n",
    );

    $loaded = ConfigLoader::fromPaths($basePath)->loadAll(
        'polyglot/llm/connections/openai.yaml',
        'http-client/http/profiles/curl.yaml',
    );

    expect(array_keys($loaded))->toBe([
        'polyglot/llm/connections/openai.yaml',
        'http-client/http/profiles/curl.yaml',
    ]);
    expect($loaded['polyglot/llm/connections/openai.yaml']->toArray()['model'])->toBe('gpt-4o-mini');
    expect($loaded['http-client/http/profiles/curl.yaml']->toArray()['driver'])->toBe('curl');
});

it('uses ConfigLoader cache and refreshes when source file changes', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/config';
    $sourcePath = writeFile(
        $basePath . '/polyglot/llm/connections/openai.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );
    $cachePath = $tmp . '/var/cache/instructor-config.php';

    $first = ConfigLoader::fromPaths($basePath)
        ->withCache($cachePath)
        ->load('polyglot/llm/connections/openai.yaml')
        ->toArray();

    expect($first['model'])->toBe('gpt-4o-mini');
    expect(is_file($cachePath))->toBeTrue();

    usleep(1100000);
    writeFile($sourcePath, "driver: openai\nmodel: gpt-5\n");

    $second = ConfigLoader::fromPaths($basePath)
        ->withCache($cachePath)
        ->load('polyglot/llm/connections/openai.yaml')
        ->toArray();

    expect($second['model'])->toBe('gpt-5');
});

it('stores multiple loaded files in one cache payload', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/config';
    writeFile($basePath . '/polyglot/llm/connections/openai.yaml', "driver: openai\nmodel: gpt-4o-mini\n");
    writeFile($basePath . '/http-client/http/profiles/curl.yaml', "driver: curl\nrequest_timeout: 30\n");
    $cachePath = $tmp . '/var/cache/instructor-config.php';

    $config = Config::fromPaths($basePath)->withCache($cachePath);
    $config->load('polyglot/llm/connections/openai.yaml');
    $config->load('http-client/http/profiles/curl.yaml');

    /** @var array<string, mixed> $payload */
    $payload = require $cachePath;
    $entries = $payload['entries'] ?? [];
    expect($entries)->toHaveCount(2);
});

it('throws when config file does not exist in any base path', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/config';
    writeFile($basePath . '/polyglot/llm/connections/openai.yaml', "driver: openai\n");

    $loader = ConfigLoader::fromPaths($basePath);

    expect(fn () => $loader->load('polyglot/llm/connections/anthropic.yaml'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when ConfigLoader::loadAll has no config paths', function () {
    $tmp = makeTmpConfigDir();
    $basePath = $tmp . '/config';
    writeFile($basePath . '/polyglot/llm/connections/openai.yaml', "driver: openai\n");

    $loader = ConfigLoader::fromPaths($basePath);

    expect(fn () => $loader->loadAll())
        ->toThrow(InvalidArgumentException::class, 'ConfigLoader::loadAll() requires at least one config path');
});

it('interpolates simple env placeholder values', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "apiKey: \${TEST_OPENAI_KEY}\n",
    );

    withEnvVar('TEST_OPENAI_KEY', 'secret-key', function () use ($path): void {
        $entry = Config::fromPaths(dirname($path))->load(basename($path));
        expect($entry->toArray()['apiKey'])->toBe('secret-key');
    });
});

it('interpolates values from configured .env paths', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "apiKey: \${CFG_DOTENV_KEY}\n",
    );
    writeFile(
        $tmp . '/.env',
        "CFG_DOTENV_KEY=dotenv-key\n",
    );

    withEnvVarUnset('CFG_DOTENV_KEY', function () use ($tmp, $path): void {
        withDotenvPaths([$tmp], ['.env'], function () use ($path): void {
            $entry = Config::fromPaths(dirname($path))->load(basename($path));
            expect($entry->toArray()['apiKey'])->toBe('dotenv-key');
        });
    });
});

it('keeps default env filename when only dotenv paths are set', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "apiKey: \${CFG_PATHS_ONLY_KEY}\n",
    );
    writeFile(
        $tmp . '/.env',
        "CFG_PATHS_ONLY_KEY=paths-only-key\n",
    );

    withEnvVarUnset('CFG_PATHS_ONLY_KEY', function () use ($tmp, $path): void {
        Env::set([$tmp]);
        try {
            $entry = Config::fromPaths(dirname($path))->load(basename($path));
            expect($entry->toArray()['apiKey'])->toBe('paths-only-key');
        } finally {
            Env::set(['.'], ['.env']);
        }
    });
});

it('interpolates values from server variables when env is not exported', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "apiKey: \${CFG_SERVER_ONLY_KEY}\n",
    );

    withEnvVarUnset('CFG_SERVER_ONLY_KEY', function () use ($path): void {
        withServerVar('CFG_SERVER_ONLY_KEY', 'server-key', function () use ($path): void {
            $entry = Config::fromPaths(dirname($path))->load(basename($path));
            expect($entry->toArray()['apiKey'])->toBe('server-key');
        });
    });
});

it('interpolates placeholders in the middle of a string', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "url: \"https://api.\${CFG_HOST}/v1/\${CFG_PATH}/chat\"\nheader: \"Bearer \${CFG_TOKEN}\"\nlabel: \"pre-\${CFG_MIDDLE}-post\"\n",
    );

    withEnvVars([
        'CFG_HOST' => 'example.test',
        'CFG_PATH' => 'tenant-a',
        'CFG_TOKEN' => 'abc123',
        'CFG_MIDDLE' => 'core',
    ], function () use ($path): void {
        $data = Config::fromPaths(dirname($path))->load(basename($path))->toArray();
        expect($data['url'])->toBe('https://api.example.test/v1/tenant-a/chat');
        expect($data['header'])->toBe('Bearer abc123');
        expect($data['label'])->toBe('pre-core-post');
    });
});

it('uses default value for missing env placeholders', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "region: \${CFG_REGION:-us-east-1}\n",
    );

    withEnvVarUnset('CFG_REGION', function () use ($path): void {
        $data = Config::fromPaths(dirname($path))->load(basename($path))->toArray();
        expect($data['region'])->toBe('us-east-1');
    });
});

it('throws for required placeholders when env value is missing', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "apiKey: \${CFG_REQUIRED?}\n",
    );

    withEnvVarUnset('CFG_REQUIRED', function () use ($path): void {
        expect(fn () => Config::fromPaths(dirname($path))->load(basename($path)))
            ->toThrow(InvalidArgumentException::class, "Required environment variable 'CFG_REQUIRED' is missing or empty");
    });
});

it('throws for required placeholders with custom error message', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "apiKey: \${CFG_CUSTOM_REQ:?Set CFG_CUSTOM_REQ in your environment}\n",
    );

    withEnvVarUnset('CFG_CUSTOM_REQ', function () use ($path): void {
        expect(fn () => Config::fromPaths(dirname($path))->load(basename($path)))
            ->toThrow(InvalidArgumentException::class, 'Set CFG_CUSTOM_REQ in your environment');
    });
});

it('applies interpolation after cache read so env changes are picked up', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "apiKey: \${CFG_CACHE_KEY}\n",
    );
    $cache = $tmp . '/var/cache/openai-config.php';

    withEnvVar('CFG_CACHE_KEY', 'first', function () use ($path, $cache): void {
        $first = Config::fromPaths(dirname($path))
            ->withCache($cache)
            ->load(basename($path))
            ->toArray();

        expect($first['apiKey'])->toBe('first');
        expect(is_file($cache))->toBeTrue();
    });

    withEnvVar('CFG_CACHE_KEY', 'second', function () use ($path, $cache): void {
        $second = Config::fromPaths(dirname($path))
            ->withCache($cache)
            ->load(basename($path))
            ->toArray();

        expect($second['apiKey'])->toBe('second');
    });
});

/** @return string */
function makeTmpConfigDir(): string {
    $dir = sys_get_temp_dir() . '/instructor-config-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    register_shutdown_function(static function () use ($dir): void {
        deleteDirRecursive($dir);
    });
    return $dir;
}

/** @return string */
function writeFile(string $path, string $content): string {
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($path, $content);
    return $path;
}

function deleteDirRecursive(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            deleteDirRecursive($path);
            continue;
        }

        unlink($path);
    }

    rmdir($dir);
}

function withEnvVar(string $name, ?string $value, callable $callback): void {
    $previousValue = getenv($name);
    $hadPrevious = $previousValue !== false || array_key_exists($name, $_ENV);
    $previousEnv = $_ENV[$name] ?? null;

    if ($value === null) {
        putenv($name);
        unset($_ENV[$name]);
    } else {
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }

    try {
        $callback();
    } finally {
        if ($hadPrevious && $previousValue !== false) {
            putenv("{$name}={$previousValue}");
            $_ENV[$name] = (string) $previousValue;
            return;
        }

        if ($hadPrevious && is_string($previousEnv)) {
            putenv("{$name}={$previousEnv}");
            $_ENV[$name] = $previousEnv;
            return;
        }

        putenv($name);
        unset($_ENV[$name]);
    }
}

function withEnvVarUnset(string $name, callable $callback): void {
    withEnvVar($name, null, $callback);
}

function withServerVar(string $name, ?string $value, callable $callback): void {
    $hadPrevious = array_key_exists($name, $_SERVER);
    $previous = $_SERVER[$name] ?? null;

    if ($value === null) {
        unset($_SERVER[$name]);
    } else {
        $_SERVER[$name] = $value;
    }

    try {
        $callback();
    } finally {
        if ($hadPrevious) {
            $_SERVER[$name] = $previous;
            return;
        }

        unset($_SERVER[$name]);
    }
}

/** @param array<int, string> $paths @param array<int, string> $names */
function withDotenvPaths(array $paths, array $names, callable $callback): void {
    Env::set($paths, $names);
    try {
        $callback();
    } finally {
        Env::set(['.'], ['.env']);
    }
}

/** @param array<string, string> $vars */
function withEnvVars(array $vars, callable $callback): void {
    if ($vars === []) {
        $callback();
        return;
    }

    $keys = array_keys($vars);
    $firstKey = array_shift($keys);
    if ($firstKey === null) {
        $callback();
        return;
    }

    withEnvVar($firstKey, $vars[$firstKey], function () use ($keys, $vars, $callback): void {
        withEnvVarsRecursive($keys, $vars, $callback);
    });
}

/** @param array<int, string> $keys @param array<string, string> $vars */
function withEnvVarsRecursive(array $keys, array $vars, callable $callback): void {
    if ($keys === []) {
        $callback();
        return;
    }

    $firstKey = array_shift($keys);
    if ($firstKey === null) {
        $callback();
        return;
    }

    withEnvVar($firstKey, $vars[$firstKey], function () use ($keys, $vars, $callback): void {
        withEnvVarsRecursive($keys, $vars, $callback);
    });
}
