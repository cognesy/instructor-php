# Config Cheatsheet

```php
use Cognesy\Config\Config;
use Cognesy\Config\ConfigLoader;

$data = (new Config(__DIR__ . '/config/polyglot/llm/connections/openai.yaml'))
    ->withCache(__DIR__ . '/var/cache/openai-config.php')
    ->load()
    ->toArray();

$configs = ConfigLoader::fromPaths(
    __DIR__ . '/config/polyglot/llm/connections/openai.yaml',
    __DIR__ . '/config/http-client/http/profiles/curl.yaml',
)->withCache(__DIR__ . '/var/cache/instructor-config.php');

$llm = $configs->load('polyglot.llm.connections.openai')->toArray();
$http = $configs->load('http-client.http.profiles.curl')->toArray();
```
