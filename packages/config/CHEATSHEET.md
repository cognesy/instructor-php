# Config Package - Deep Reference

## Core Architecture

### Configuration Contract
```php
interface CanProvideConfig {
    public function get(string $path, mixed $default = null): mixed;
    public function has(string $path): bool;
}
```

### Configuration Resolution Chain
```php
// Basic resolver with default provider
$config = ConfigResolver::default();

// Chain multiple providers (first match wins)
$config = ConfigResolver::using($primary)->then($secondary)->then($fallback);

// Custom provider chain
$config = new ConfigResolver([
    new LaravelConfigProvider($app['config']),
    new SettingsConfigProvider(),
    new ArrayConfigProvider(['fallback' => 'value'])
], suppressProviderErrors: false);
```

## Settings Class - Static File-Based Config

### Path Resolution Priority (First Match)
1. `Settings::setPath('/custom/path')` - Manual override (highest)
2. `INSTRUCTOR_CONFIG_PATHS` env var (comma-separated)
3. `INSTRUCTOR_CONFIG_PATH` env var  
4. Default paths:
   ```
   config/
   vendor/cognesy/instructor-php/config/
   vendor/cognesy/instructor-struct/config/
   vendor/cognesy/instructor-polyglot/config/
   vendor/cognesy/instructor-config/config/
   ```

### Usage Patterns
```php
// Basic retrieval with dot notation
$dsn = Settings::get('database', 'dsn');
$timeout = Settings::get('api', 'connection.timeout', 30);

// Existence checks
if (Settings::has('cache', 'redis.host')) { /* */ }
if (Settings::has('logging')) { /* group exists */ }

// Path override
Settings::setPath(__DIR__ . '/config');
Settings::flush(); // Clear cache + custom paths

// Internal caching: groups cached as Dot objects in static::$cache
```

## Base Path Detection

### Detection Methods (Tried in Order)
```php
BasePath::withDetectionMethods([
    'getBasePathFromEnv',      // APP_BASE_PATH, APP_ROOT, PROJECT_ROOT, BASE_PATH
    'getBasePathFromCwd',      // getcwd() + composer.json check
    'getBasePathFromComposer', // ClassLoader reflection
    'getBasePathFromServerVars', // DOCUMENT_ROOT, SCRIPT_FILENAME, PWD
    'getBasePathFromReflection', // Walk up from class file
    'getBasePathFromFrameworkPatterns' // public/index.php, web/index.php patterns
]);

BasePath::set('/custom/root'); // Manual override
$path = BasePath::get('config/app.php'); // Append to root
```

## Provider Implementations

### SettingsConfigProvider
```php
// Bridges Settings class to CanProvideConfig
$provider = new SettingsConfigProvider('/custom/config/path');
$value = $provider->get('database.connection'); // calls Settings::get('database', 'connection')
$hasGroup = $provider->has('database'); // entire group check
```

### ArrayConfigProvider  
```php
$provider = new ArrayConfigProvider([
    'database' => ['host' => 'localhost'],
    'nested.key' => 'value'
]);
$host = $provider->get('database.host');
$provider->set('new.key', 'value'); // Mutable
```

### Framework Adapters
```php
// Laravel
$provider = new LaravelConfigProvider(app('config'));

// Symfony Container
$provider = new SymfonyConfigProvider($container);

// Symfony ParameterBag
$provider = new SymfonyParameterBagProvider($parameterBag);
```

## DSN Parsing

### DSN Structure
```
key1=value1,key2=value2,nested.key=value,bool_flag=true
```

### Usage Patterns
```php
// Parse DSN string
$dsn = Dsn::fromString('host=localhost,port=3306,ssl=true');
$host = $dsn->stringParam('host');
$port = $dsn->intParam('port', 3306);
$ssl = $dsn->boolParam('ssl', false);

// Template variable replacement  
$dsn = Dsn::fromString('host={DB_HOST},port={DB_PORT}');
// Uses getenv() for {DB_HOST}, {DB_PORT} replacement

// Type-safe parameter extraction
$dsn->param('key', 'default');
$dsn->intParam('timeout', 30);
$dsn->floatParam('ratio', 1.0);
$dsn->boolParam('enabled', false);

// DSN validation & filtering
if (Dsn::isDsn($string)) { /* contains = */ }
$safeDsn = $dsn->without(['password', 'secret']);
$params = $dsn->params(); // All as array
```

## Configuration Presets

### Preset Structure
```php
// config/database.php
return [
    'defaultPreset' => 'production',
    'presets' => [
        'development' => ['host' => 'localhost', 'debug' => true],
        'production' => ['host' => 'prod.db', 'debug' => false],
        'testing' => ['host' => 'test.db', 'memory' => true]
    ]
];
```

### Usage Patterns
```php
$presets = new ConfigPresets('database');
$config = $presets->get('production'); // Specific preset
$config = $presets->default(); // Uses defaultPreset value
$config = $presets->getOrDefault('missing'); // Fallback to default

// Check preset existence
if ($presets->hasPreset('staging')) { /* */ }
$available = $presets->presets(); // ['development', 'production', 'testing']

// Custom provider
$presets = ConfigPresets::using($customProvider)->for('api');
```

## Environment Variable Loading

### Path and File Configuration
```php
// Default: searches . and ./.env for .env file
Env::set(['.', '/custom/path'], ['.env', '.env.local']);
$value = Env::get('DATABASE_URL', 'sqlite://memory');

// BasePath resolution applied to relative paths
// Uses Dotenv library with safeLoad() - no overwrite
```

## Event System

```php
// Event hierarchy
abstract class ConfigEvent extends \Cognesy\Events\Event {}
final class ConfigResolved extends ConfigEvent {}
class ConfigResolutionFailed extends ConfigEvent {}
```

## Exception Hierarchy

```php
// File/group not found
class NoSettingsFileException extends \Exception {}

// Key missing in existing group  
class MissingSettingException extends \Exception {}

// General configuration issues
class ConfigurationException extends \Exception {}

// Preset not found
class ConfigPresetNotFoundException extends \Exception {}
```

## Advanced Patterns

### Deferred Provider Loading
```php
// ConfigResolver uses Deferred internally
$resolver = ConfigResolver::using(null)
    ->then(fn() => new ExpensiveProvider()) // Lazy evaluation
    ->then(new Deferred(fn() => $heavyComputation()));
```

### Provider Error Handling
```php
$resolver = ConfigResolver::using($unstableProvider)
    ->withSuppressedProviderErrors(false); // Throw on provider failures

// Default: suppresses errors, continues to next provider
```

### Caching Strategy
```php
// Settings: Static cache per group (Dot objects)
Settings::flush(); // Clear all cached groups

// ConfigResolver: CachedMap for get/has operations  
// ArrayConfigProvider: Direct Dot object manipulation
```

### Complex Path Resolution
```php
// Dot notation support across all providers
$config->get('database.connections.mysql.host');
$config->get('app.providers.0.class'); // Array index access
$config->has('feature.flags.new_ui'); // Deep existence check
```