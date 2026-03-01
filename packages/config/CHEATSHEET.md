# Config Cheat Sheet

## Main Building Blocks

- `ConfigResolver`: resolve config values across provider chain
- `ConfigPresets`: read named presets from config data
- `Settings`: load `group.php` config files
- `Dsn`: parse DSN-like key/value strings
- `Env`: load/get environment values
- `BasePath`: detect or set project base path

## `ConfigResolver`

Create resolver:

```php
use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Providers\ArrayConfigProvider;

$resolver = ConfigResolver::using(new ArrayConfigProvider([
    'app' => ['name' => 'demo'],
]));
```

Public API:

- `ConfigResolver::default(): ConfigResolver`
- `ConfigResolver::using(?CanProvideConfig $provider): ConfigResolver`
- `then(callable|CanProvideConfig|null $provider): static`
- `withSuppressedProviderErrors(bool $suppress = true): static`
- `get(string $path, mixed $default = null): mixed`
- `has(string $path): bool`

Behavior notes:

- Provider order is first-match-wins.
- `using($provider)` includes `SettingsConfigProvider` as fallback.
- Existing `null` values are treated as found values (no fallback/default override).
- `withSuppressedProviderErrors()` returns a new resolver instance.

## `ConfigPresets`

Typical config shape:

```php
return [
    'http' => [
        'defaultPreset' => 'default',
        'presets' => [
            'default' => ['timeout' => 30],
            'fast' => ['timeout' => 10],
        ],
    ],
];
```

Usage:

```php
use Cognesy\Config\ConfigPresets;
use Cognesy\Config\Providers\ArrayConfigProvider;

$presets = ConfigPresets::using(new ArrayConfigProvider($config))->for('http');
$current = $presets->getOrDefault('fast');
```

Public API:

- `ConfigPresets::using(?CanProvideConfig $config): ConfigPresets`
- `for(string $group): ConfigPresets`
- `withConfigProvider(CanProvideConfig $config): ConfigPresets`
- `get(?string $preset = null): array`
- `getOrDefault(?string $preset = null): array`
- `hasPreset(string $preset): bool`
- `default(): array`
- `presets(): array`

## `Settings` (File-Based Config)

Reads files like `config/http.php`, `config/llm.php`.

Public API:

- `Settings::setPath(string $dir): void`
- `Settings::flush(): void`
- `Settings::has(string $group, ?string $key = null): bool`
- `Settings::get(string $group, string $key, mixed $default = null): mixed`
- `Settings::getGroup(string $group): mixed`
- `Settings::hasGroup(string $group): bool`

Path precedence:

1. `Settings::setPath(...)`
2. `INSTRUCTOR_CONFIG_PATHS` / `INSTRUCTOR_CONFIG_PATH`
3. internal defaults

## Providers (`CanProvideConfig`)

Contract:

```php
interface CanProvideConfig {
    public function get(string $path, mixed $default = null): mixed;
    public function has(string $path): bool;
}
```

Included providers:

- `ArrayConfigProvider`
- `SettingsConfigProvider`
- `LaravelConfigProvider`
- `SymfonyConfigProvider`
- `SymfonyParameterBagProvider`

## `Dsn`

Usage:

```php
use Cognesy\Config\Dsn;

$dsn = Dsn::fromString('provider=openai,metadata.apiVersion=2024-01-01,stream=true');
```

Public API:

- `Dsn::fromArray(array $params): Dsn`
- `Dsn::fromString(?string $dsn): Dsn`
- `Dsn::isDsn(string $dsn): bool`
- `Dsn::ifValid(string $dsn): ?Dsn`
- `without(string|array $excluded): Dsn`
- `hasParam(string $key): bool`
- `params(): array`
- `param(string $key, mixed $default = null): mixed`
- `intParam(string $key, int $default = 0): int`
- `stringParam(string $key, string $default = ''): string`
- `boolParam(string $key, bool $default = false): bool`
- `floatParam(string $key, float $default = 0.0): float`
- `toArray(): array`

Notes:

- Dot notation is supported (`metadata.apiVersion`).
- `{ENV_VAR}` placeholders are replaced from current environment.

## `Env`

Public API:

- `Env::set(string|array $paths, string|array $names = ''): void`
- `Env::get(mixed $key, mixed $default = null): mixed`
- `Env::load(): void`

## `BasePath`

Public API:

- `BasePath::get(string $path = ''): string`
- `BasePath::set(string $path): void`
- `BasePath::withDetectionMethods(array $methods): void`

Detection methods (default order):

- `getBasePathFromEnv`
- `getBasePathFromCwd`
- `getBasePathFromComposer`
- `getBasePathFromServerVars`
- `getBasePathFromReflection`
- `getBasePathFromFrameworkPatterns`

## Exceptions

- `ConfigurationException`
- `ConfigPresetNotFoundException`
- `NoSettingsFileException`
- `MissingSettingException`
