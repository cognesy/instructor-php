# instructor-config (lean)

Minimal configuration infrastructure for Instructor.

Scope:
- load YAML or PHP config files as raw arrays,
- derive deterministic dot-keys from file paths,
- optional YAML/PHP parse cache compiled to PHP,
- no presets, no provider chains, no global settings.

Usage:

```php
use Cognesy\Config\ConfigLoader;

$configs = ConfigLoader::fromPaths(
    __DIR__ . '/config/polyglot/llm/connections/openai.yaml',
    __DIR__ . '/config/http-client/http/profiles/curl.yaml',
)->withCache(__DIR__ . '/var/cache/instructor-config.php');

$data = $configs->load('polyglot.llm.connections.openai')->toArray();
```
