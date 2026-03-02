# Instructor Config

Small configuration toolkit used across Instructor packages.

It provides:

- provider-based config resolution (`ConfigResolver`)
- preset selection (`ConfigPresets`)
- DSN parsing (`Dsn`)

```php
use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Providers\ArrayConfigProvider;

$config = ConfigResolver::using(new ArrayConfigProvider([
    'llm' => ['model' => 'gpt-4.1-mini'],
]));

echo $config->get('llm.model');
```

See [CHEATSHEET.md](CHEATSHEET.md) for API details.
