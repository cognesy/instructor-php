# Templates Package Cheatsheet

## Template Class

### Factory Methods
```php
Template::make(string $pathOrDsn)          // Create from path or DSN
Template::using(string $preset)            // Use configuration preset
Template::twig()                           // Twig engine
Template::blade()                          // Blade engine  
Template::arrowpipe()                      // Arrowpipe engine
Template::fromDsn(string $dsn)            // Create from DSN
```

### Static Shortcuts
```php
Template::text(string $pathOrDsn, array $variables): string
Template::messages(string $pathOrDsn, array $variables): Messages
```

### Configuration
```php
withConfig(TemplateEngineConfig $config): self
withDriver(CanHandleTemplate $driver): self
withPreset(string $preset): self
```

### Template Content
```php
get(string $path): self                    // Alias for withTemplate
withTemplate(string $path): self           // Load from file
withTemplateContent(string $content): self // Set content directly
from(string $content): self                // Alias for withTemplateContent
```

### Variables
```php
with(array $values): self                  // Alias for withValues
withValues(array $values): self            // Set template variables
params(): array                            // Get current variables
variables(): array                         // Get template variable names
```

### Output
```php
toText(): string                           // Render as string
toMessages(): Messages                     // Convert to Messages
toMessageStore(): MessageStore             // Convert to MessageStore
toArray(): array                           // Convert to array
```

### Validation & Info
```php
validationErrors(): array                  // Get validation errors
info(): TemplateInfo                       // Get template metadata
config(): TemplateEngineConfig            // Get current config
template(): string                         // Get raw template content
```

### Message Rendering
```php
renderMessage(Message $message): Message
renderMessages(Messages $messages): Messages
```

## TemplateProvider Class

### Core Methods
```php
get(string $preset): self                  // Switch to preset
withConfig(TemplateEngineConfig $config): self
withDriver(CanHandleTemplate $driver): self
config(): TemplateEngineConfig
driver(): CanHandleTemplate
```

### Template Operations
```php
loadTemplate(string $path): string        // Load template content
renderString(string $content, array $variables): string
renderFile(string $path, array $variables): string
getVariableNames(string $content): array
```

## Message Renderers

### ArrowpipeMessagesRenderer
```php
renderMessages(Messages $messages, array $parameters = []): Messages
```

### MessageToRoleStringRenderer  
```php
renderMessages(Messages $messages, array $parameters = []): Messages
```

## StringTemplate Class

### Static Usage
```php
StringTemplate::render(string $template, array $parameters, bool $clearUnknownParams = true): string
```

### Instance Usage
```php
new StringTemplate(array $parameters = [], bool $clearUnknownParams = true)
renderString(string $template): string
renderArray(array $rows, string $field = 'content'): array
renderMessage(array|Message $message): array
renderMessages(array|Messages $messages): array
getVariableNames(string $content): array
getParameters(): array
```

## TemplateEngineConfig Class

### Factory Methods
```php
TemplateEngineConfig::twig(string $resourcePath = '', string $cachePath = ''): self
TemplateEngineConfig::blade(string $resourcePath = '', string $cachePath = ''): self  
TemplateEngineConfig::arrowpipe(string $resourcePath = '', string $cachePath = ''): self
TemplateEngineConfig::fromArray(array $config): self
```

### Properties
```php
$templateEngine: TemplateEngineType        // Engine type
$resourcePath: string                      // Template directory
$cachePath: string                         // Cache directory
$extension: string                         // File extension
$frontMatterTags: array                    // YAML front matter tags
$frontMatterFormat: FrontMatterFormat      // Front matter format
$metadata: array                           // Additional metadata
```

### Methods
```php
toArray(): array
withOverrides(array $values): self
group(): string                            // Returns 'prompt'
```

## TemplateInfo Class

### Access Methods
```php
field(string $name): mixed                 // Get front matter field
hasField(string $name): bool               // Check field exists
data(): array                              // All front matter data
content(): string                          // Template content
```

### Variable Methods
```php
variables(): array                         // Variable definitions
variableNames(): array                     // Variable names only
hasVariables(): bool                       // Has variables defined
```

### Schema Methods
```php
schema(): array                            // JSON schema
hasSchema(): bool                          // Has schema defined
```

## Common Usage Patterns

### Quick Text Rendering
```php
$result = Template::text('preset:template', ['var' => 'value']);
```

### Message Generation
```php
$messages = Template::using('demo-twig')
    ->get('chat')
    ->with(['user' => 'Alice'])
    ->toMessages();
```

### DSN Format
```php
'engine:template_name'                     // preset:template format
'demo-twig:hello'                          // Example DSN
```

### XML Chat Markup
```php
'<chat>
  <message role="system">System prompt</message>
  <message role="user">User: {{ input }}</message>
</chat>'
```

### String Template Variables
```php
'Hello <|name|>, welcome to <|app|>!'     // Variable syntax
```

### Message Renderers Usage
```php
// Render messages with Arrowpipe template syntax
$renderer = new ArrowpipeMessagesRenderer();
$rendered = $renderer->renderMessages($messages, ['key' => 'value']);

// Convert messages to role:content format
$roleRenderer = new MessageToRoleStringRenderer();  
$formatted = $roleRenderer->renderMessages($messages);
```

## Template Engines

### Supported Engines
- **Twig**: Full-featured template engine with inheritance and filters
- **Blade**: Laravel's template engine with directives and components  
- **Arrowpipe**: Built-in simple variable substitution using `<|var|>` syntax

### Engine Requirements
- **Twig**: `composer require twig/twig`
- **Blade**: `composer require eftec/bladeone`  
- **Arrowpipe**: Built-in (no dependencies)

## Driver Classes

### ArrowpipeDriver
```php
renderFile(string $path, array $parameters = []): string
renderString(string $content, array $parameters = []): string  
getTemplateContent(string $path): string
getVariableNames(string $content): array
```

### CanHandleTemplate Interface
All template drivers must implement:
```php
renderFile(string $path, array $parameters = []): string
renderString(string $content, array $parameters = []): string
getTemplateContent(string $path): string
getVariableNames(string $content): array
```
