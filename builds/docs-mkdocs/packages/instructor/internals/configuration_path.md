---
title: 'Configuration Path'
description: 'How to locate Instructor YAML config at the application edge'
---

Instructor does not use global config-path state in core code.

Pass explicit base paths to `Config` / `ConfigLoader` at your application edge and map raw arrays to typed config objects.

<Info>
To publish configuration files to your project see [Setup](/setup).
</Info>

### Recommended Pattern

```php
<?php
use Cognesy\Config\Config;
use Cognesy\Config\ConfigLoader;

$llmData = Config::fromPaths(__DIR__ . '/config')
    ->load('llm/presets/openai.yaml')
    ->toArray();

$loader = ConfigLoader::fromPaths(
    __DIR__ . '/config',
);

$llmEntry = $loader->load('llm/presets/openai.yaml');
$llmDataFromLoader = $llmEntry->toArray();
// @doctest id="2010"
```

## Resolution Rule

Configuration path resolution is an edge concern:
- your bootstrap chooses where config lives,
- `Config` / `ConfigLoader` reads raw YAML/PHP arrays,
- `XxxConfig::fromArray()` performs typing/validation.

Core runtime classes should receive typed config objects and should not discover config files themselves.
