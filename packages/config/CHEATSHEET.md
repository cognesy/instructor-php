# Config Cheatsheet

```php
use Cognesy\Config\Config;
use Cognesy\Config\ConfigLoader;
use Cognesy\Config\Dsn;

$data = (new Config([__DIR__ . '/config/polyglot/llm/connections']))
    ->withCache(__DIR__ . '/var/cache/openai-config.php')
    ->load('openai.yaml')
    ->toArray();

$fromBases = Config::fromPaths(
    __DIR__ . '/packages/polyglot/resources/config',
    __DIR__ . '/packages/http-client/resources/config',
)
    ->load('llm/presets/openai.yaml')
    ->toArray();

$configs = ConfigLoader::fromPaths(
    __DIR__ . '/packages/polyglot/resources/config',
    __DIR__ . '/packages/http-client/resources/config',
)->withCache(__DIR__ . '/var/cache/instructor-config.php');

$llm = $configs->load('llm/presets/openai.yaml')->toArray();
$loaded = $configs->loadAll('llm/presets/openai.yaml', 'http/profiles/curl.yaml');

$dsn = Dsn::fromString('driver=openai,metadata.region=us-east-1')->toArray();
```
