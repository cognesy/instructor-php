# Templates Package Overview

The Templates package provides a flexible templating system for the Instructor-PHP framework, enabling dynamic content generation with multiple template engine support and advanced message/script composition capabilities.

## Core Capabilities

### Multi-Engine Template Support
- **Twig**: Professional template engine with extensive features
- **Blade**: Laravel's elegant templating syntax  
- **Arrowpipe**: Lightweight custom template engine

### Template Processing
- Load templates from files or use inline content
- Variable substitution with validation
- Front matter metadata support (YAML format)
- Template variable extraction and validation
- DSN-based template loading (`engine:template_name`)

### Message & MessageStore Generation
- Convert templates to `Messages` objects for LLM interaction
- Generate structured `MessageStore` objects with multiple sections
- Support for XML-based chat markup with roles (system, user, assistant)
- Multi-modal content support (text, images, audio)

### Advanced Features
- Template presets and configuration management
- String-based templating with `<|variable|>` syntax
- Section-based script organization
- Parameter validation and error reporting
- Cache control for ephemeral content

## Key Classes

### Template
Main template class providing fluent interface for template operations.

**Core Methods:**
- `Template::make(string $pathOrDsn)` - Create from path or DSN
- `Template::using(string $preset)` - Use configuration preset
- `withTemplate(string $path)` / `withTemplateContent(string $content)` - Set template source
- `withValues(array $values)` - Set template variables
- `toText()` / `toMessages()` / `toMessageStore()` - Output conversions

**Static Shortcuts:**
- `Template::text(string $pathOrDsn, array $variables)` - Direct text rendering
- `Template::messages(string $pathOrDsn, array $variables)` - Direct message conversion

### TemplateProvider
Manages template engine configuration and provides rendering services.

**Key Methods:**
- `loadTemplate(string $path)` - Load template content
- `renderString(string $content, array $variables)` - Render with variables
- `getVariableNames(string $content)` - Extract variable names

### MessageStore
Advanced message sequence management for complex LLM interactions.

**Features:**
- Multiple named sections for organized message flow
- Section selection and reordering
- Parameter-based message rendering
- Conversion to Messages or array formats

### StringTemplate
Lightweight string templating utility using `<|variable|>` syntax.

**Methods:**
- `StringTemplate::render(string $template, array $parameters)` - Static rendering
- `renderString(string $template)` - Instance-based rendering
- `renderMessages(array|Messages $messages)` - Message collection rendering

## Usage Patterns

### Basic Template Rendering
```php
// Using preset configuration
$text = Template::using('demo-twig')
    ->get('hello')
    ->with(['name' => 'World'])
    ->toText();

// Using DSN syntax
$messages = Template::messages('demo-twig:hello', ['name' => 'World']);

// Direct text rendering
$text = Template::text('demo-blade:greeting', ['user' => 'Alice']);
```

### XML Chat Markup
```php
$template = '<chat>
    <message role="system">You are helpful assistant.</message>
    <message role="user">Hello, {{ name }}</message>
</chat>';

$messages = Template::blade()
    ->withTemplateContent($template)
    ->withValues(['name' => 'assistant'])
    ->toMessages();
```

### MessageStore-Based Organization
```php

$store = new MessageStore(
    new Section('system'),
    new Section('conversation')
);

$store = $store->withSection('system')
    ->appendMessageToSection('system', [
        'role' => 'system', 
        'content' => 'You are helpful'
    ]);

$store = $store->withSection('conversation')
    ->appendMessageToSection('conversation', [
        'role' => 'user', 
        'content' => 'Hello <|name|>'
    ]);

$store->withParams(['name' => 'World']);
$messages = $store->toArray();
```

### Configuration & Engine Selection
```php
// Engine-specific factory methods
$template = Template::twig();
$template = Template::blade(); 
$template = Template::arrowpipe();

// Custom configuration
$config = TemplateEngineConfig::twig('/path/to/templates', '/tmp/cache');
$template = (new Template())->withConfig($config);
```

## Template Engine Features

### Twig
- Full Twig syntax support
- File-based template loading
- Template inheritance and blocks
- Requires: `composer require twig/twig`

### Blade  
- Laravel Blade syntax
- Directive support (@if, @foreach, etc.)
- Template compilation and caching
- Requires: `composer require eftec/bladeone`

### Arrowpipe
- Custom lightweight syntax
- Built-in engine (no external dependencies)
- Simple variable substitution

## Architecture

The package follows a clean architecture with:
- **Template**: User-facing API with fluent interface
- **TemplateProvider**: Engine abstraction and configuration management  
- **Drivers**: Engine-specific implementations (TwigDriver, BladeDriver, ArrowpipeDriver)
- **Config**: Configuration management with preset support
- **Utils**: Helper utilities for string templating and message conversion

The system supports both simple string templating and complex multi-section script generation, making it suitable for everything from basic variable substitution to sophisticated LLM conversation management.
