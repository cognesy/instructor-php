# instructor-config (lean)

Minimal configuration infrastructure for Instructor.

Scope:
- load YAML or PHP config files as raw arrays,
- resolve relative config file names against multiple base paths,
- derive deterministic dot-keys from file paths,
- optional YAML/PHP parse cache compiled to PHP,
- parse DSN strings into raw arrays,
- no presets, no provider chains, no global settings.

Usage:

```php
use Cognesy\Config\Config;
use Cognesy\Config\ConfigLoader;

$single = Config::fromPaths(
    __DIR__ . '/packages/polyglot/resources/config',
    __DIR__ . '/packages/http-client/resources/config',
)->load('llm/presets/openai.yaml')->toArray();

$configs = ConfigLoader::fromPaths(
    __DIR__ . '/packages/polyglot/resources/config',
    __DIR__ . '/packages/http-client/resources/config',
)->withCache(__DIR__ . '/var/cache/instructor-config.php');

$one = $configs->load('llm/presets/openai.yaml')->toArray();
$many = $configs->loadAll(
    'llm/presets/openai.yaml',
    'http/profiles/curl.yaml',
);
```

DSN parsing:

```php
use Cognesy\Config\Dsn;

$raw = Dsn::fromString('driver=openai,metadata.region=us-east-1')->toArray();
```
