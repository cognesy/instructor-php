<?php

use Cognesy\Config\Exceptions\MissingSettingException;
use Cognesy\Config\Exceptions\NoSettingsFileException;
use Cognesy\Config\Settings;

function tempConfigDir(string $group, array $data): string {
    $dir = sys_get_temp_dir() . '/settings_' . uniqid('', true);
    mkdir($dir, 0777, true);
    file_put_contents(
        "$dir/$group.php",
        '<?php return ' . var_export($data, true) . ';'
    );
    return $dir;
}

beforeEach(function () {
    $this->group = 'app';
    $this->data  = [
        'name'  => 'cognesy',
        'debug' => true,
        'db'    => ['host' => 'localhost', 'port' => 3306],
    ];
    $this->dir   = tempConfigDir($this->group, $this->data);
});

afterEach(function () {
    Settings::flush(); // clear static cache
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $f) {
        $f->isDir() ? rmdir($f) : unlink($f);
    }
    rmdir($this->dir);
    unset($_ENV['INSTRUCTOR_CONFIG_PATHS'], $_ENV['INSTRUCTOR_CONFIG_PATH']);
});

it('reads from explicit path override', function () {
    Settings::setPath($this->dir);
    expect(Settings::get($this->group, 'db.host'))->toBe('localhost');
});

it('prefers override over env', function () {
    $_ENV['INSTRUCTOR_CONFIG_PATHS'] = '/nowhere';
    Settings::setPath($this->dir);
    expect(Settings::get($this->group, 'name'))->toBe('cognesy');
});

it('reads from env when no override set', function () {
    $_ENV['INSTRUCTOR_CONFIG_PATHS'] = $this->dir;
    putenv("INSTRUCTOR_CONFIG_PATHS={$this->dir}");
    Settings::flush(); // Clear cache and custom paths
    expect(Settings::get($this->group, 'debug'))->toBeTrue();
});

it('returns default when key missing', function () {
    Settings::setPath($this->dir);
    expect(Settings::get($this->group, 'missing', 'foo'))->toBe('foo');
});

it('throws when key missing without default', function () {
    Settings::setPath($this->dir);
    expect(fn() => Settings::get($this->group, 'missing'))
        ->toThrow(MissingSettingException::class);
});

it('throws when group file absent', function () {
    Settings::setPath($this->dir);
    expect(fn() => Settings::get('absent', 'x'))
        ->toThrow(NoSettingsFileException::class);
});

it('has() detects group and key presence', function () {
    Settings::setPath($this->dir);
    expect(Settings::has($this->group))->toBeTrue()
        ->and(Settings::has($this->group, 'db.port'))->toBeTrue()
        ->and(Settings::has($this->group, 'nope'))->toBeFalse()
        ->and(Settings::has('nope'))->toBeFalse();
});

it('flush() reloads changed file', function () {
    Settings::setPath($this->dir);
    expect(Settings::get($this->group, 'db.port'))->toBe(3306);

    // mutate file on disk
    $new = $this->data; $new['db']['port'] = 5432;
    file_put_contents("$this->dir/{$this->group}.php", '<?php return ' . var_export($new, true) . ';');

    // still cached
    expect(Settings::get($this->group, 'db.port'))->toBe(3306);

    Settings::flush();
    Settings::setPath($this->dir); // Restore path after flush
    expect(Settings::get($this->group, 'db.port'))->toBe(5432);
});