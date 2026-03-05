# Templates Package Internals

The Templates package is a small engine-agnostic rendering layer used to turn template files or inline template strings into:
- text
- `Messages`
- `MessageStore`

## Runtime Boundary

The runtime API is typed and explicit:
- choose engine with `Template::twig()`, `Template::blade()`, `Template::arrowpipe()`, or `Template::forEngine(string $engine)`
- or pass an explicit `TemplateEngineConfig` to `new Template(config: ...)`

No preset lookup is performed in core runtime.

## Core Flow

1. `Template` receives a `TemplateEngineConfig` and optional explicit driver.
2. `TemplateProvider` resolves the concrete driver from engine type.
3. Driver loads and renders template content with provided variables.
4. `Template` converts rendered output to text/messages/message store.

## Entry Points

- `Template::make(string $pathOrDsn)` accepts a file path or `engine:path` DSN
- `Template::fromDsn(string $dsn)` parses `engine:path`
- `Template::text(...)` and `Template::messages(...)` are convenience static helpers

## TemplateProvider Responsibilities

- hold active `TemplateEngineConfig`
- create driver from config
- delegate load/render/variable extraction to driver

## Driver Selection

`TemplateProvider` uses `TemplateEngineType`:
- `Twig` -> `TwigDriver`
- `Blade` -> `BladeDriver`
- `Arrowpipe` -> `ArrowpipeDriver`

If Twig/Blade dependencies are missing, provider throws clear runtime guidance.

## Output Conversion

`Template` can parse rendered XML-like chat blocks into:
- ordered `Messages`
- section-aware `MessageStore`

Supported content blocks:
- text
- image (`image_url`)
- audio (`input_audio`)

