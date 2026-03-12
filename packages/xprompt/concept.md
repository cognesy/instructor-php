# xprompt for PHP — Concept

Prompts-as-code micro-library for PHP 8.2+. Port of the Python xprompt design
philosophy to PHP, adapted to idiomatic PHP patterns.

## Problem

Inline prompts embedded in application code are:
- Invisible (buried in service classes)
- Unversioned (changing means changing PHP code)
- Cannot be A/B tested without code changes
- Coupled (prompt authors must edit PHP files)
- Not composable (copy-paste between prompts)

## Design Philosophy

Each prompt is a PHP class. This gives you:
- **Identity** — named, navigable in IDE, refactorable
- **Composition** — return trees of Prompt objects, recursively flattened
- **Variants** — subclass and swap via config, zero code changes
- **Templates** — long-form text lives in external `.md` files (clean diffs)
- **Metadata** — class attributes + YAML front matter

## Core API

### Prompt Base Class

```php
use Cognesy\Xprompt\Prompt;

class Persona extends Prompt
{
    public function body(mixed ...$ctx): string|array|null
    {
        return "You are a senior technical reviewer specializing in {$ctx['domain']}.";
    }
}
```

`Prompt` implements `Stringable`. All public APIs across the monorepo
(`withSystem()`, `withPrompt()`, `Message::asSystem()`, etc.) accept
`string|Stringable`, so xprompt objects are passed directly — no casting needed:

```php
// Passed directly to any API accepting string|Stringable
$inference->withSystem(Persona::with(domain: 'security'));

// String concatenation works naturally
$text = "Context: " . Persona::with(domain: 'security');

// Echo, interpolation, etc.
echo new Persona();
```

### Static Constructors

```php
// Bare instance
Persona::make()

// Instance with bound context
Persona::with(domain: 'security', tone: 'formal')

// Render directly
$text = Persona::with(domain: 'security')->render();

// Or rely on Stringable
$text = (string) Persona::with(domain: 'security');
```

### Composition via body()

`body()` returns a string, an array of renderables, or null (skipped).
Renderables are: strings, Prompt instances, arrays (nested), or null.

```php
#[AsPrompt("reviewer.review")]
class Review extends Prompt
{
    public string $model = 'opus';

    public function body(mixed ...$ctx): array
    {
        return [
            Persona::make(),
            ScoringRubric::with(format: 'detailed'),
            "## Document\n\n" . $ctx['content'],
            OutputFormat::make(),
            $ctx['strict'] ?? false ? Constraints::make() : null,
        ];
    }
}

// Usage
echo Review::with(content: $doc, strict: true);
```

### Context Propagation

Parent context automatically flows to children. `::with()` overrides specific keys.

```php
// lang: 'en' propagates to Persona, ScoringRubric, OutputFormat, etc.
echo Review::with(content: $doc, lang: 'en');
```

Children receive merged context: `[...parent_ctx, ...child_own_ctx, ...render_ctx]`

```php
// ScoringRubric gets: {content: $doc, lang: 'en', format: 'detailed'}
ScoringRubric::with(format: 'detailed')  // 'format' is own, rest inherited
```

### Template Files

For long-form text, use external Markdown files with optional YAML front matter.
Template rendering is delegated to `packages/templates` (`Template` class), which
supports multiple engines: Twig (default), Blade, and Arrowpipe (`<|var|>`).

```php
class Analyze extends Prompt
{
    public string $model = 'sonnet';
    public string $templateFile = 'analyze.md';
    public string $templateDir = __DIR__;  // colocate with class
}
```

**`analyze.md` (Twig syntax — default):**
```markdown
---
description: Analyze document against evaluation criteria
model: sonnet
---
Analyze {{ content }} against {{ criteria_text }}.

For each criterion where you find evidence, extract an observation.
```

**Same template in Blade syntax:**
```markdown
---
description: Analyze document against evaluation criteria
model: sonnet
---
Analyze {{ $content }} against {{ $criteria_text }}.

For each criterion where you find evidence, extract an observation.
```

**Same template in Arrowpipe syntax (zero dependencies):**
```markdown
---
description: Analyze document against evaluation criteria
model: sonnet
---
Analyze <|content|> against <|criteria_text|>.

For each criterion where you find evidence, extract an observation.
```

The engine is selected via class property or config:

```php
use Cognesy\Templates\Enums\TemplateEngineType;

class Analyze extends Prompt
{
    public string $templateFile = 'analyze.md';
    public TemplateEngineType $templateEngine = TemplateEngineType::Blade;
}
```

### Block References in Templates

Prompts can declare blocks that are available in templates. Blocks are rendered
by xprompt and injected as variables before passing to `packages/templates`:

```php
class Review extends Prompt
{
    public array $blocks = [ScoringRubric::class, Constraints::class];
    public string $templateFile = 'review.md';
}
```

**`review.md`:**
```markdown
{{ blocks.ScoringRubric }}

## Document
{{ content }}

{{ blocks.Constraints }}
```

Blocks are instantiated and rendered on-demand with the current context.
The rendered block strings are passed to the template engine as regular variables.

### Blocks (Hidden Prompts)

Mark prompts as blocks to hide them from registry listing:

```php
class ScoringRubric extends Prompt
{
    public bool $isBlock = true;

    public function body(mixed ...$ctx): string
    {
        return <<<'PROMPT'
            ## Scoring Rubric
            - L0: No evidence
            - L1: Weak evidence
            - L2: Strong evidence
            - L3: Conclusive evidence
            PROMPT;
    }
}
```

## Variants & A/B Testing

### Define Variants as Subclasses

```php
class Analyze extends Prompt
{
    public string $model = 'sonnet';
    public string $templateFile = 'analyze.md';
}

class AnalyzeCoT extends Analyze
{
    // Override only what changes, inherit the rest
    public string $templateFile = 'analyze_cot.md';
}
```

### Swap via Config (Zero Code Changes)

```php
$registry = new PromptRegistry(
    overrides: [
        'reviewer.analyze' => AnalyzeCoT::class,
    ],
);

$prompt = $registry->get('reviewer.analyze'); // returns AnalyzeCoT instance
```

### Variant Workflow

1. Author writes `AnalyzeCoT` (new file, doesn't touch `Analyze`)
2. Config: set override `'reviewer.analyze' => AnalyzeCoT::class`
3. Run, compare results
4. If winning: promote new to default
5. If losing: delete variant, remove config

No code changes at any step except adding/removing the variant class.

## Registry

### Registration via Attribute

```php
#[AsPrompt("reviewer.analyze")]
class Analyze extends Prompt { ... }

#[AsPrompt("reviewer.analyze")]  // same name = registered as variant
class AnalyzeCoT extends Analyze { ... }
```

### Manual Registration

```php
$registry = new PromptRegistry();
$registry->register('reviewer.analyze', Analyze::class);
$registry->register('reviewer.analyze', AnalyzeCoT::class); // variant
```

### Usage

```php
$prompt = $registry->get('reviewer.analyze');
$text = $prompt->render(content: $doc);

// Introspection
$registry->names();                          // ['reviewer.analyze', ...]
$registry->all();                            // iterate all registered prompts
$registry->all(includeBlocks: true);         // include blocks
```

### Config-Driven Overrides

```php
$registry = new PromptRegistry(
    overrides: [
        'reviewer.analyze' => AnalyzeCoT::class,
        '_blocks.eval_framework' => FiveLenses::class,
    ],
);
```

## NodeSet — Structured Data as Prompts

For rendering structured data (criteria lists, rubrics, taxonomies):

```php
class EvalCriteria extends NodeSet
{
    public string $dataFile = 'criteria.yml';
    public string $sortKey = 'priority';
}
```

**`criteria.yml`:**
```yaml
- id: clarity
  label: Clarity
  content: Writing is clear and unambiguous
  priority: 1
- id: accuracy
  label: Accuracy
  content: Claims are factually correct
  priority: 2
  children:
    - id: sources
      content: Sources are cited
```

**Rendered output:**
```
1. **Clarity** -- Writing is clear and unambiguous

2. **Accuracy** -- Claims are factually correct
   - Sources are cited
```

### Custom Node Rendering

Override `renderNode()` for custom formatting:

```php
class NumberedCriteria extends NodeSet
{
    public string $dataFile = 'criteria.yml';

    public function renderNode(int $index, array $node, mixed ...$ctx): string
    {
        return "Criterion {$index}: [{$node['label']}] {$node['content']}";
    }
}
```

### Inline Data

```php
class QuickList extends NodeSet
{
    public array $items = [
        ['id' => 'a', 'label' => 'First', 'content' => 'Do this first'],
        ['id' => 'b', 'label' => 'Second', 'content' => 'Then this'],
    ];
}
```

### Dynamic Data via Override

```php
class DynamicCriteria extends NodeSet
{
    public function nodes(mixed ...$ctx): array
    {
        // Fetch, filter, transform — any logic
        return $ctx['criteria'] ?? [];
    }
}
```

## Metadata

### Class Attributes

```php
class Analyze extends Prompt
{
    public string $model = 'sonnet';      // suggested LLM model
    public bool $isBlock = false;          // hidden from registry listing
    public string $templateFile = '';      // external template path
    public ?string $templateDir = null;    // colocate templates with class
    public array $blocks = [];             // block classes for template composition
}
```

### Front Matter (YAML in Template Files)

```markdown
---
description: Analyze document against criteria
model: sonnet
version: v2
custom_field: any value
---
Template content here...
```

Accessed via:

```php
$prompt->meta(); // ['description' => '...', 'model' => 'sonnet', ...]
```

## Auto-Discovery

Optional addon that scans Composer autoload namespaces for prompt classes:

```php
use Cognesy\Xprompt\Discovery\PromptDiscovery;

$registry = new PromptRegistry();
PromptDiscovery::register($registry, namespaces: ['App\\Prompts']);
```

### Discovery Rules

1. `#[AsPrompt("name")]` attribute — explicit name
2. `$promptName` property on class — explicit name
3. Convention: `Namespace\SubNamespace\ClassName` -> `sub_namespace.class_name`

### Composer Classmap

Discovery uses Composer's generated classmap to find classes without filesystem
scanning. Classes must extend `Prompt` and not be abstract.

## Flattening — The Renderer

The entire rendering engine is one recursive function:

```php
function flatten(mixed $node, array $ctx = []): string
{
    return match(true) {
        $node === null            => '',
        is_string($node)          => $node,
        $node instanceof Prompt   => $node->render(...$ctx),
        is_array($node)           => implode("\n\n", array_filter(
            array_map(fn($n) => flatten($n, $ctx), $node)
        )),
        $node instanceof Stringable => (string) $node,
        default                   => (string) $node,
    };
}
```

- `null` is skipped (enables conditional composition)
- Strings pass through
- Prompt instances are rendered with propagated context
- Arrays are recursively flattened and joined with `\n\n`
- Stringable objects are coerced

## Template Rendering — Integration with packages/templates

Template file rendering is delegated entirely to `packages/templates`. xprompt's
role is to:

1. Resolve the template file path
2. Pre-render blocks (Prompt instances) into strings
3. Merge block renders + context into template variables
4. Hand off to `Template` for engine-specific rendering

```php
use Cognesy\Templates\Template;

// Inside Prompt::renderTemplate()
private function renderTemplate(mixed ...$ctx): string
{
    // 1. Pre-render blocks into strings
    $blockRenders = [];
    foreach ($this->blocks as $blockClass) {
        $instance = new $blockClass();
        $blockRenders[$this->shortName($blockClass)] = $instance->render(...$ctx);
    }

    // 2. Merge context: user ctx + rendered blocks
    $variables = [...$ctx, 'blocks' => $blockRenders];

    // 3. Resolve template path
    $path = $this->resolveTemplatePath();

    // 4. Delegate to packages/templates
    return Template::make($path)
        ->withValues($variables)
        ->toText();
}
```

This means any template engine supported by `packages/templates` works
transparently — Twig, Blade, or Arrowpipe. The engine is determined by:

1. Explicit `$templateEngine` property on the Prompt class
2. File extension (`.twig`, `.blade.php`, `.tpl`)
3. Registry-level default configuration

### Why delegate to packages/templates?

- **No duplicated rendering logic** — one place for engine management, caching,
  variable extraction, front matter parsing
- **Engine choice per prompt** — a team can use Twig for complex prompts and
  Arrowpipe for simple ones, in the same project
- **Validation** — `Template::validationErrors()` catches missing/extra variables
  at dev time, before LLM calls
- **Existing infrastructure** — `TemplateInfo` already parses YAML/JSON/TOML
  front matter; `TemplateEngineConfig` handles cache paths and extensions
- **Blade for Laravel users** — Laravel teams get native Blade syntax in prompt
  files without xprompt needing to know about Blade

### Template introspection via packages/templates

```php
$prompt = new Analyze();

// Variable names defined in template (via packages/templates driver)
$prompt->variables();  // ['content', 'criteria_text']

// Validation: check context matches template expectations
$prompt->validationErrors(content: $doc);
// -> ['Missing variable: criteria_text']

// Front matter metadata (parsed by TemplateInfo)
$prompt->meta();
// -> ['description' => 'Analyze document...', 'model' => 'sonnet']
```

## Base Class Implementation Sketch

```php
namespace Cognesy\Xprompt;

use Stringable;

abstract class Prompt implements Stringable
{
    public string $model = '';
    public bool $isBlock = false;
    public string $templateFile = '';
    public ?string $templateDir = null;
    public array $blocks = [];

    protected array $ctx = [];

    // -- Static constructors ------------------------------------------

    public static function make(): static
    {
        return new static();
    }

    public static function with(mixed ...$ctx): static
    {
        $instance = new static();
        $instance->ctx = $ctx;
        return $instance;
    }

    // -- Rendering ----------------------------------------------------

    public function render(mixed ...$ctx): string
    {
        $merged = [...$this->ctx, ...$ctx];
        $content = $this->body(...$merged);
        return flatten($content, $merged);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    // -- Override point ------------------------------------------------

    public function body(mixed ...$ctx): string|array|null
    {
        if ($this->templateFile !== '') {
            return $this->renderTemplate(...$ctx);
        }
        return null;
    }

    // -- Metadata -----------------------------------------------------

    public function meta(): array
    {
        // Parse YAML front matter from templateFile
    }

    // -- Template rendering (private) ---------------------------------

    private function renderTemplate(mixed ...$ctx): string
    {
        // Pre-render blocks into strings
        $blockRenders = [];
        foreach ($this->blocks as $blockClass) {
            $instance = new $blockClass();
            $blockRenders[$this->shortName($blockClass)] = $instance->render(...$ctx);
        }

        // Delegate to packages/templates
        return Template::make($this->resolveTemplatePath())
            ->withValues([...$ctx, 'blocks' => $blockRenders])
            ->toText();
    }

    private function resolveTemplatePath(): string
    {
        $dir = $this->templateDir ?? self::$promptsRoot ?? '';
        return rtrim($dir, '/') . '/' . $this->templateFile;
    }

    private function shortName(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }
}
```

## Testing

Prompts are just classes — test them like any other code:

```php
it('renders review prompt', function () {
    $text = Review::with(content: 'test doc', strict: true)->render();
    expect($text)->toContain('## Scoring Rubric');
    expect($text)->toContain('test doc');
    expect($text)->not->toContain('{{ ');  // no unresolved variables
});

it('renders persona block', function () {
    $text = Persona::with(domain: 'security')->render();
    expect($text)->toContain('security');
});

it('skips null conditionals', function () {
    $text = Review::with(content: 'doc', strict: false)->render();
    expect($text)->not->toContain('Constraints');
});

it('propagates context to children', function () {
    $text = Review::with(content: 'doc', lang: 'en')->render();
    // child prompts receive lang: 'en'
});
```

## File Structure

```
packages/xprompt/
  src/
    Prompt.php                  # Base class + Stringable
    NodeSet.php                 # Structured data prompt
    PromptRegistry.php          # Name -> class registry + overrides
    Attributes/
      AsPrompt.php              # #[AsPrompt("name")] attribute
    Discovery/
      PromptDiscovery.php       # Auto-discovery via Composer classmap
    functions.php               # flatten()
  tests/
    PromptTest.php
    NodeSetTest.php
    RegistryTest.php
    CompositionTest.php
  composer.json
```

## Dependencies

- **Required**: `cognesy/templates` — template rendering (Twig, Blade, or Arrowpipe)
- **Suggested**: `symfony/yaml` — for NodeSet YAML data files

`packages/templates` ships with Arrowpipe built-in (zero external deps), so the
simplest setup requires no additional packages. Twig and Blade are optional — install
them only if you want those engines.

## Relationship to packages/templates

`packages/templates` is a multi-engine template rendering abstraction. It handles:
- Engine management (Twig, Blade, Arrowpipe)
- File loading and caching
- Variable extraction and validation
- Front matter parsing (`TemplateInfo`)
- Template-to-Messages conversion (for LLM APIs)

`packages/xprompt` builds on top of it, adding prompt-specific concerns:
- Prompt identity (classes, registry, attributes)
- Composition (body() trees, recursive flattening)
- Variants (subclassing, config-driven swapping)
- Block pre-rendering (Prompt instances -> strings -> template variables)
- Context propagation (parent ctx flows to children)

The boundary is clean:
- **xprompt** owns the prompt graph — what to render and how to compose it
- **templates** owns the rendering — how to turn a template + variables into text

```
┌─────────────────────────────────────────────┐
│  xprompt                                    │
│  Prompt classes, composition, registry,     │
│  variants, context propagation              │
│                                             │
│  body() -> [Prompt, string, null, ...]      │
│       │                                     │
│       ▼ flatten()                           │
│  Pre-render blocks -> string variables      │
│       │                                     │
│       ▼ delegate                            │
├─────────────────────────────────────────────┤
│  packages/templates                         │
│  Template::make($path)                      │
│      ->withValues($variables)               │
│      ->toText()                             │
│                                             │
│  Engine: Twig | Blade | Arrowpipe           │
│  + front matter, validation, caching        │
└─────────────────────────────────────────────┘
```

## Integration with Instructor Packages

All public APIs across the monorepo accept `string|Stringable` for prompt
parameters. Since `Prompt` implements `Stringable`, xprompt objects are passed
directly — no casting, no `->render()` at the call site.

### With packages/polyglot (Inference)

```php
use Cognesy\Polyglot\Inference\Inference;

$system = ReviewSystem::with(domain: 'security', strict: true);

// Prompt object passed directly — Stringable coercion at the boundary
$response = (new Inference)
    ->withSystem($system)
    ->withPrompt("Review this document:\n\n{$document}")
    ->withModel($system->model)
    ->create()
    ->toText();
```

### With packages/agents (AgentBuilder)

```php
use Cognesy\Agents\AgentBuilder;

$system = ReviewSystem::with(domain: 'security', strict: true);

$agent = (new AgentBuilder)
    ->withSystemPrompt($system)          // accepts string|Stringable
    ->withModel($system->model)
    ->withTools([...])
    ->create();

$result = $agent->run("Review this document:\n\n{$document}");
```

### With packages/instructor (Structured Output)

```php
use Cognesy\Instructor\StructuredOutputRuntime;

$system = ReviewSystem::with(domain: 'security', strict: true);

$result = (new StructuredOutputRuntime)
    ->withSystem($system)                // accepts string|Stringable
    ->withPrompt("Review this document:\n\n{$document}")
    ->withResponseModel(ReviewResult::class)
    ->create()
    ->get();
```

### With Messages directly

```php
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

$system = ReviewSystem::with(domain: 'security');
$analyze = AnalyzePrompt::with(content: $doc);

$messages = Messages::fromMessages([
    Message::asSystem($system),          // accepts string|Stringable
    Message::asUser($analyze),           // accepts string|Stringable
]);
```

### Prompt as inline composition in Messages

```php
$messages = Messages::fromMessages([
    Message::asSystem(ReviewSystem::with(domain: 'security')),
    Message::asUser(
        "Review this:\n\n" . AnalyzePrompt::with(content: $doc)
    ),
]);
```

### Full Example: Prompt Definition to Agent Execution

**Step 1 — Define prompt classes with editable template files:**

```
src/Prompts/Reviewer/
    Persona.php
    ScoringRubric.php
    scoring_rubric.md          <-- editable by non-developers
    OutputFormat.php
    output_format.md           <-- editable by non-developers
    Constraints.php
    ReviewSystem.php
```

```php
// Persona.php — inline body
class Persona extends Prompt
{
    public bool $isBlock = true;

    public function body(mixed ...$ctx): string
    {
        return "You are a senior {$ctx['domain']} reviewer with 15 years of experience.";
    }
}

// ScoringRubric.php — template-backed, editable Markdown
class ScoringRubric extends Prompt
{
    public bool $isBlock = true;
    public string $templateFile = 'scoring_rubric.md';
    public string $templateDir = __DIR__;
}

// ReviewSystem.php — composed system prompt
#[AsPrompt('reviewer.system')]
class ReviewSystem extends Prompt
{
    public string $model = 'opus';

    public function body(mixed ...$ctx): array
    {
        return [
            Persona::make(),
            ScoringRubric::make(),
            $ctx['strict'] ?? false ? Constraints::make() : null,
            OutputFormat::make(),
        ];
    }
}
```

**`scoring_rubric.md`** (rendered via packages/templates):
```markdown
---
description: Scoring rubric for document review
---
## Scoring Rubric

Evaluate each criterion on this scale:
- **L0**: No evidence found
- **L1**: Weak evidence — mentioned but not substantiated
- **L2**: Strong evidence — supported with specifics
- **L3**: Conclusive — irrefutable, well-documented

{% if detailed %}
For each score, provide:
1. The exact quote or reference
2. Your confidence level (low/medium/high)
3. Suggested improvement if score < L2
{% endif %}
```

**Step 2 — Use in application code:**

```php
// In a service, controller, or agent tool — one line
$response = (new Inference)
    ->withSystem(ReviewSystem::with(domain: 'security', strict: true))
    ->withPrompt("Review this document:\n\n{$document}")
    ->create()
    ->toText();
```

**Step 3 — A/B test a variant (zero changes to step 2):**

```php
// New class, new template file — nothing else touched
class ScoringRubricCoT extends ScoringRubric
{
    public string $templateFile = 'scoring_rubric_cot.md';
}

// Config swap
$registry = new PromptRegistry(
    overrides: ['_blocks.scoring_rubric' => ScoringRubricCoT::class],
);
```

### Rendering Flow

```
Application code:
  ->withSystem(ReviewSystem::with(domain: 'security', strict: true))
       │
       ▼ Stringable triggers __toString() -> render()
       │
ReviewSystem::render(domain: 'security', strict: true)
  │
  ▼ body() returns:
  [Persona, ScoringRubric, Constraints, OutputFormat]
  │
  ▼ flatten() recurses each element:
  │
  ├─ Persona::render(domain: 'security', strict: true)
  │    └─ body() returns inline string
  │         "You are a senior security reviewer..."
  │
  ├─ ScoringRubric::render(domain: 'security', strict: true)
  │    └─ body() delegates to packages/templates:
  │         Template::make('.../scoring_rubric.md')
  │             ->withValues([domain: 'security', strict: true, ...])
  │             ->toText()
  │         └─ Twig renders .md → string
  │
  ├─ Constraints::render(...)
  │
  └─ OutputFormat::render(...)
       └─ Template::make('.../output_format.md') -> toText()
  │
  ▼ implode("\n\n", [...rendered strings...])
  │
  ▼ Final string arrives at Inference/Agent/Instructor
```

## Comparison to Python xprompt

| Feature | Python xprompt | PHP xprompt |
|---------|---------------|-------------|
| Prompt class | `class Foo(Prompt)` | `class Foo extends Prompt` |
| Decorator/Attribute | `@prompt("name")` | `#[AsPrompt("name")]` |
| Context passing | `**kwargs` | `mixed ...$ctx` (named args) |
| String coercion | `__str__` | `Stringable` / `__toString()` |
| Static constructor | `Foo()` (Python classes are callable) | `Foo::make()` / `Foo::with()` |
| Templates | Jinja2 | Twig (same lineage) |
| Discovery | Module walking | Composer classmap + reflection |
| NodeSet | YAML + `render_node()` | Same pattern |
| Registry | `PromptRegistry` | `PromptRegistry` |
| Variants | Subclass + config override | Same |
| ~LOC | ~200 | ~250 estimated |
