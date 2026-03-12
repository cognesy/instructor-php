---
title: Templates
description: Template engine abstraction — Twig, Blade, Plates, and DSN-based template resolution
package: templates
---

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

## TemplateEngineConfig

```php
TemplateEngineConfig::fromPreset(string $preset, ?string $basePath = null): self
TemplateEngineConfig::fromArray(array $config): self
TemplateEngineConfig::twig(string $resourcePath = '', string $cachePath = ''): self
TemplateEngineConfig::blade(string $resourcePath = '', string $cachePath = ''): self
TemplateEngineConfig::arrowpipe(string $resourcePath = '', string $cachePath = ''): self
```

## Minimal Examples

```php
$text = Template::forEngine('twig')
    ->get('prompts/demo-twig/hello')
    ->with(['name' => 'World'])
    ->toText();

$messages = Template::messages('twig:prompts/demo-twig/hello', ['name' => 'World']);
```

