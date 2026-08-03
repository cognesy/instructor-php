# instructor-config (lean)

Minimal configuration infrastructure for Instructor.

Scope:

- load YAML or PHP config files as raw arrays,
- resolve relative config file names against multiple base paths,
- derive deterministic dot-keys from file paths,
- optional YAML/PHP parse cache compiled to PHP,
- parse DSN strings into raw arrays,
- accept application-owned secret resolvers for `${VARIABLE}` interpolation,
- no presets, provider-specific chains, secret persistence, or global settings.

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

Layered secret resolution is explicit and deterministic. The first source that
contains a value wins; provenance is safe to inspect, while the value remains
private and is never returned by `ResolvedSecret::toArray()`:

```php
use Cognesy\Config\Config;
use Cognesy\Config\EnvTemplate;
use Cognesy\Config\Secrets\DotenvFileSecretSource;
use Cognesy\Config\Secrets\EnvironmentSecretSource;
use Cognesy\Config\Secrets\SecretResolver;

$secrets = new SecretResolver(
    new EnvironmentSecretSource('process-environment'),
    DotenvFileSecretSource::optional(__DIR__.'/.env', 'workspace-env'),
    DotenvFileSecretSource::optional('/app/private/credentials.env', 'app-credentials'),
);

$entry = Config::fromPaths(__DIR__.'/config')
    ->withTemplate(new EnvTemplate($secrets))
    ->load('llm.yaml');

$source = $secrets->resolve('OPENAI_API_KEY')?->source;
```

Applications own source ordering and persistence policy. Config resolves values
without mutating global `Env` state; it deliberately does not write secrets.

DSN parsing:

```php
use Cognesy\Config\Dsn;

$raw = Dsn::fromString('driver=openai,metadata.region=us-east-1')->toArray();
```
