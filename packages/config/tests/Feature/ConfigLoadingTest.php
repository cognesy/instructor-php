<?php declare(strict_types=1);

use Cognesy\Config\Config;
use Cognesy\Config\ConfigLoader;

it('loads yaml file as raw array', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );

    $entry = (new Config($path))->load();

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

    $entry = (new Config($path))->load();

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

    $first = (new Config($source))
        ->withCache($cache)
        ->load()
        ->toArray();

    expect($first['model'])->toBe('gpt-4o-mini');
    expect(is_file($cache))->toBeTrue();

    usleep(1100000);
    writeFile($source, "driver: openai\nmodel: gpt-5\n");

    $second = (new Config($source))
        ->withCache($cache)
        ->load()
        ->toArray();

    expect($second['model'])->toBe('gpt-5');
});

it('loads by dot key from multiple paths', function () {
    $tmp = makeTmpConfigDir();

    $llmPath = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );

    $httpPath = writeFile(
        $tmp . '/config/http-client/http/profiles/curl.yaml',
        "driver: curl\nrequest_timeout: 30\n",
    );

    $loader = ConfigLoader::fromPaths($llmPath, $httpPath);

    expect($loader->has('polyglot.llm.connections.openai'))->toBeTrue();
    expect($loader->has('http-client.http.profiles.curl'))->toBeTrue();
    expect($loader->keys())->toBe([
        'http-client.http.profiles.curl',
        'polyglot.llm.connections.openai',
    ]);

    $llm = $loader->load('polyglot.llm.connections.openai')->toArray();
    expect($llm['driver'])->toBe('openai');
    expect($llm['model'])->toBe('gpt-4o-mini');
});

it('uses loader cache and refreshes when source file changes', function () {
    $tmp = makeTmpConfigDir();

    $llmPath = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "driver: openai\nmodel: gpt-4o-mini\n",
    );
    $httpPath = writeFile(
        $tmp . '/config/http-client/http/profiles/curl.yaml',
        "driver: curl\nrequest_timeout: 30\n",
    );

    $cachePath = $tmp . '/var/cache/instructor-config.php';

    $firstLoader = ConfigLoader::fromPaths($llmPath, $httpPath)->withCache($cachePath);
    $first = $firstLoader->load('polyglot.llm.connections.openai')->toArray();

    expect($first['model'])->toBe('gpt-4o-mini');
    expect(is_file($cachePath))->toBeTrue();

    usleep(1100000);
    writeFile($llmPath, "driver: openai\nmodel: gpt-5\n");

    $secondLoader = ConfigLoader::fromPaths($llmPath, $httpPath)->withCache($cachePath);
    $second = $secondLoader->load('polyglot.llm.connections.openai')->toArray();

    expect($second['model'])->toBe('gpt-5');
});

it('throws when key does not exist', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "driver: openai\n",
    );

    $loader = ConfigLoader::fromPaths($path);

    expect(fn () => $loader->load('polyglot.llm.connections.anthropic'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws on duplicate key in paths', function () {
    $tmp = makeTmpConfigDir();

    $yaml = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "driver: openai\n",
    );

    $php = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.php',
        "<?php\nreturn ['driver' => 'openai'];\n",
    );

    $loader = ConfigLoader::fromPaths($yaml, $php);

    expect(fn () => $loader->keys())
        ->toThrow(LogicException::class);
});

it('interpolates simple env placeholder values', function () {
    $tmp = makeTmpConfigDir();
    $path = writeFile(
        $tmp . '/config/polyglot/llm/connections/openai.yaml',
        "apiKey: \${TEST_OPENAI_KEY}\n",
    );

    withEnvVar('TEST_OPENAI_KEY', 'secret-key', function () use ($path): void {
        $entry = (new Config($path))->load();
        expect($entry->toArray()['apiKey'])->toBe('secret-key');
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
        $data = (new Config($path))->load()->toArray();
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
        $data = (new Config($path))->load()->toArray();
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
        expect(fn () => (new Config($path))->load())
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
        expect(fn () => (new Config($path))->load())
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
        $first = (new Config($path))
            ->withCache($cache)
            ->load()
            ->toArray();

        expect($first['apiKey'])->toBe('first');
        expect(is_file($cache))->toBeTrue();
    });

    withEnvVar('CFG_CACHE_KEY', 'second', function () use ($path, $cache): void {
        $second = (new Config($path))
            ->withCache($cache)
            ->load()
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
