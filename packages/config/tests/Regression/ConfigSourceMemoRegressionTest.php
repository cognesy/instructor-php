<?php declare(strict_types=1);

use Cognesy\Config\Config;
use Cognesy\Config\Contracts\CanResolveSecrets;
use Cognesy\Config\EnvTemplate;
use Cognesy\Config\Secrets\ResolvedSecret;

/**
 * Config keeps parsed config files in a process-wide memo so a per-request caller such as
 * LLMConfig::fromPreset does not re-parse the same YAML every time. These cover the two
 * ways that memo could go wrong: serving a stale file, or freezing environment values that
 * are supposed to be resolved fresh on every load.
 */

/** A secret source whose answers can change between loads. */
final class MutableSecretResolver implements CanResolveSecrets
{
    /** @param array<string, string> $values */
    public function __construct(public array $values = []) {}

    public function resolve(string $name): ?ResolvedSecret {
        return isset($this->values[$name])
            ? new ResolvedSecret($name, $this->values[$name], 'test')
            : null;
    }
}

beforeEach(function () {
    Config::flushSourceCache();
    $this->dir = sys_get_temp_dir() . '/config-memo-' . bin2hex(random_bytes(6));
    mkdir($this->dir, 0777, true);
});

afterEach(function () {
    Config::flushSourceCache();
    array_map('unlink', glob($this->dir . '/*') ?: []);
    @rmdir($this->dir);
});

it('re-resolves environment placeholders on every load instead of memoizing them', function () {
    file_put_contents($this->dir . '/thing.yaml', "apiKey: \"\${SOME_SECRET}\"\n");

    $secrets = new MutableSecretResolver(['SOME_SECRET' => 'first']);
    $config = Config::fromPaths($this->dir)->withTemplate(new EnvTemplate($secrets));

    expect($config->load('thing.yaml')->toArray()['apiKey'])->toBe('first');

    $secrets->values['SOME_SECRET'] = 'second';

    expect($config->load('thing.yaml')->toArray()['apiKey'])->toBe('second');
});

it('serves the memoized parse while the file is unchanged', function () {
    file_put_contents($this->dir . '/thing.yaml', "model: original\n");
    $config = Config::fromPaths($this->dir);

    expect($config->load('thing.yaml')->toArray()['model'])->toBe('original');

    // Rewrite the contents but restore the original mtime: the memo is keyed on mtime, so
    // this is the case it is entitled to serve from cache.
    $mtime = filemtime($this->dir . '/thing.yaml');
    file_put_contents($this->dir . '/thing.yaml', "model: rewritten\n");
    touch($this->dir . '/thing.yaml', $mtime);
    clearstatcache(true, $this->dir . '/thing.yaml');

    expect($config->load('thing.yaml')->toArray()['model'])->toBe('original');
});

it('picks up a config file edited during the process', function () {
    file_put_contents($this->dir . '/thing.yaml', "model: original\n");
    $config = Config::fromPaths($this->dir);

    expect($config->load('thing.yaml')->toArray()['model'])->toBe('original');

    file_put_contents($this->dir . '/thing.yaml', "model: edited\n");
    touch($this->dir . '/thing.yaml', time() + 5);
    clearstatcache(true, $this->dir . '/thing.yaml');

    expect($config->load('thing.yaml')->toArray()['model'])->toBe('edited');
});

it('drops memoized parses on flushSourceCache', function () {
    file_put_contents($this->dir . '/thing.yaml', "model: original\n");
    $config = Config::fromPaths($this->dir);

    expect($config->load('thing.yaml')->toArray()['model'])->toBe('original');

    $mtime = filemtime($this->dir . '/thing.yaml');
    file_put_contents($this->dir . '/thing.yaml', "model: rewritten\n");
    touch($this->dir . '/thing.yaml', $mtime);
    clearstatcache(true, $this->dir . '/thing.yaml');

    Config::flushSourceCache();

    expect($config->load('thing.yaml')->toArray()['model'])->toBe('rewritten');
});
