# Templates Package Cheatsheet

## Template Creation

```php
Template::make(string $pathOrDsn)
Template::fromDsn(string $dsn) // engine:path

Template::twig()
Template::blade()
Template::arrowpipe()
Template::forEngine(string $engine)

new Template(path: '', config: ?TemplateEngineConfig, driver: ?CanHandleTemplate)
```

## Static Shortcuts

```php
Template::text(string $pathOrDsn, array $variables): string
Template::messages(string $pathOrDsn, array $variables): Messages
```

## Fluent API

```php
withConfig(TemplateEngineConfig $config): self
withDriver(CanHandleTemplate $driver): self

get(string $path): self
withTemplate(string $path): self
withTemplateContent(string $content): self
from(string $content): self

with(array $values): self
withValues(array $values): self
```

## Outputs

```php
toText(): string
toMessages(): Messages
toMessageStore(): MessageStore
toArray(): array
```

## Introspection

```php
config(): TemplateEngineConfig
template(): string
params(): array
variables(): array
info(): TemplateInfo
validationErrors(): array
```

## Minimal Examples

```php
$text = Template::forEngine('twig')
    ->get('prompts/demo-twig/hello')
    ->with(['name' => 'World'])
    ->toText();

$messages = Template::messages('twig:prompts/demo-twig/hello', ['name' => 'World']);
```

