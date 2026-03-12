---
title: Config
description: Configuration loading — config files, base paths, entries, and environment resolution
package: config
---

# Config Cheatsheet

## Config — load individual config files

```php
use Cognesy\Config\Config;
use Cognesy\Config\ConfigEntry;

// Create from base paths (directories containing config files)
$config = new Config(
    paths: [__DIR__ . '/config/polyglot/llm/connections'],
    cachePath: __DIR__ . '/var/cache/openai-config.php', // optional
    template: null, // optional EnvTemplate
);

// Or use the static factory
$config = Config::fromPaths(
    __DIR__ . '/packages/polyglot/resources/config',
    __DIR__ . '/packages/http-client/resources/config',
);

// Add caching (returns new instance)
$config = $config->withCache(__DIR__ . '/var/cache/openai-config.php');

// Load a config file (returns ConfigEntry)
$entry = $config->load('llm/presets/openai.yaml');
$data = $entry->toArray();
$key = $entry->key();
$source = $entry->sourcePath();
```

## ConfigLoader — convenience wrapper for loading multiple configs

```php
use Cognesy\Config\ConfigLoader;

$configs = ConfigLoader::fromPaths(
    __DIR__ . '/packages/polyglot/resources/config',
    __DIR__ . '/packages/http-client/resources/config',
)->withCache(__DIR__ . '/var/cache/instructor-config.php');

// Load a single config
$entry = $configs->load('llm/presets/openai.yaml');

// Load multiple configs at once (returns array<string, ConfigEntry>)
$loaded = $configs->loadAll('llm/presets/openai.yaml', 'http/profiles/curl.yaml');
```

## ConfigEntry — loaded config data

```php
use Cognesy\Config\ConfigEntry;

// Returned by Config::load() and ConfigLoader::load()
$entry->key();        // string — dot-notation key derived from file path
$entry->sourcePath(); // string — absolute path to the source file
$entry->toArray();    // array  — the resolved config data
```

## Dsn — parse DSN strings

```php
use Cognesy\Config\Dsn;

// Parse from string (comma-separated key=value pairs, dot-notation for nesting)
$dsn = Dsn::fromString('driver=openai,metadata.region=us-east-1');

// Create from array
$dsn = Dsn::fromArray(['driver' => 'openai', 'metadata' => ['region' => 'us-east-1']]);

// Check if a string looks like a DSN
Dsn::isDsn('driver=openai');  // true
Dsn::isDsn('just-a-string');  // false

// Parse only if valid, otherwise null
$dsn = Dsn::ifValid('driver=openai'); // Dsn|null

// Access params (supports dot-notation)
$dsn->params();                           // array<string, mixed>
$dsn->param('metadata.region');           // mixed (null if missing)
$dsn->param('metadata.region', 'default'); // mixed (with default)
$dsn->hasParam('driver');                  // bool

// Typed accessors
$dsn->stringParam('driver', '');       // string
$dsn->intParam('timeout', 30);         // int
$dsn->floatParam('temperature', 0.7);  // float
$dsn->boolParam('stream', false);      // bool

// Remove keys (returns new Dsn)
$dsn = $dsn->without('driver');
$dsn = $dsn->without(['driver', 'metadata']);

// Convert to array
$data = $dsn->toArray();
```

## Env — environment variable access

```php
use Cognesy\Config\Env;

// Get an environment variable (checks getenv, $_ENV, $_SERVER)
$value = Env::get('API_KEY');
$value = Env::get('API_KEY', 'default-value');

// Configure .env file paths and names
Env::set('/path/to/project');
Env::set(['/path1', '/path2'], ['.env', '.env.local']);

// Force reload
Env::load();
```

## EnvTemplate — resolve ${VAR} placeholders in config data

```php
use Cognesy\Config\EnvTemplate;

$template = new EnvTemplate();

// Resolve placeholders in nested arrays
$resolved = $template->resolveData([
    'key' => '${API_KEY}',
    'url' => '${BASE_URL:-https://default.example.com}',
]);

// Resolve a single string
$resolved = $template->resolveString('Bearer ${TOKEN}');

// Resolve a single value (string, array, or passthrough)
$resolved = $template->resolveValue($value);

// Supported placeholder syntax:
// ${VAR}           — value of VAR, empty string if unset
// ${VAR:-default}  — value of VAR, or "default" if unset/empty
// ${VAR?}          — value of VAR, throws if unset/empty
// ${VAR:?message}  — value of VAR, throws with message if unset/empty
```

## BasePath — application root detection

```php
use Cognesy\Config\BasePath;

// Get/resolve the application base path
$base = BasePath::get();
$base = BasePath::get('config/app.yaml');  // appends relative path
$base = BasePath::resolve('config/app.yaml'); // get() is an alias for resolve()

// Resolve multiple paths, returning only those that exist
$paths = BasePath::all('config', 'resources/config');          // alias for resolveExisting()
$paths = BasePath::resolveExisting('config', 'resources/config');

// Resolve the first existing path (throws if none exist)
$path = BasePath::resolveFirst('config', 'resources/config');

// Manually set the base path
BasePath::set('/absolute/path/to/project');

// Customize detection method order
BasePath::setDetectionMethods([
    'getBasePathFromEnv',
    'getBasePathFromCwd',
    'getBasePathFromComposer',
    'getBasePathFromServerVars',
    'getBasePathFromReflection',
    'getBasePathFromFrameworkPatterns',
]);
```

## ConfigKey — derive dot-notation keys from file paths

```php
use Cognesy\Config\ConfigKey;

// Convert a file path to a dot-notation config key
// Strips config/ prefix and file extension
$key = ConfigKey::fromPath('/app/config/llm/presets/openai.yaml'); // "llm.presets.openai"
```

## ConfigFileSet — ordered collection of config files

```php
use Cognesy\Config\ConfigFileSet;

// Create from file paths (keys auto-derived via ConfigKey)
$fileSet = ConfigFileSet::fromFiles('/path/to/config/a.yaml', '/path/to/config/b.yaml');

// Create with explicit keys
$fileSet = ConfigFileSet::fromKeyedFiles([
    'llm.openai' => '/path/to/openai.yaml',
    'http.curl'  => '/path/to/curl.yaml',
]);

$fileSet->all();                    // array<int, string> — file paths
$fileSet->keys();                   // array<int, string> — config keys
$fileSet->keyFor('/path/to/file');  // string — key for a specific file
$fileSet->count();                  // int
$fileSet->isEmpty();                // bool
$fileSet->filesHash();              // string — SHA-256 content hash
```

## ConfigBootstrap — build nested config graph from a file set

```php
use Cognesy\Config\ConfigBootstrap;
use Cognesy\Config\ConfigFileSet;

$bootstrap = new ConfigBootstrap();
$graph = $bootstrap->bootstrap($fileSet); // array<string, mixed>
```

## ConfigCacheCompiler — compile config graph to a PHP cache file

```php
use Cognesy\Config\ConfigCacheCompiler;

$compiler = new ConfigCacheCompiler();
$compiler->compile(
    cachePath: '/path/to/cache.php',
    fileSet: $fileSet,
    config: $graph,
    env: ['APP_ENV' => 'production'],  // optional
    schemaVersion: 1,                  // optional
    generatedAt: null,                 // optional, defaults to current time
);
```

## ConfigValidator — validate config arrays

```php
use Cognesy\Config\ConfigValidator;

// No validation (passthrough)
$validator = new ConfigValidator();

// With Symfony ConfigurationInterface
$validator = new ConfigValidator($configurationInstance);

// With callable
$validator = new ConfigValidator(fn(array $config) => $config);

$validated = $validator->validate($configArray);
```

## CanProvideConfig — interface for config providers

```php
use Cognesy\Config\Contracts\CanProvideConfig;

// Interface with two methods:
// get(string $path, mixed $default = null): mixed
// has(string $path): bool
```
