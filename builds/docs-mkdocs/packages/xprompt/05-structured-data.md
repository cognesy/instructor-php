---
title: 'Structured Data'
description: 'Render criteria, rubrics, and taxonomies from YAML or inline data using NodeSet'
---

# Structured Data

`NodeSet` is a specialized prompt for rendering structured lists — scoring rubrics, evaluation criteria, taxonomies, classification labels. Instead of hardcoding numbered lists in strings, you define the data and let NodeSet handle formatting.

## Inline Data

The simplest approach is inline items:

```php
use Cognesy\Xprompt\NodeSet;

class ScoringCriteria extends NodeSet
{
    public array $items = [
        ['label' => 'Clarity', 'content' => 'Writing is clear and unambiguous'],
        ['label' => 'Evidence', 'content' => 'Claims are supported by sources'],
        ['label' => 'Structure', 'content' => 'Argument flows logically'],
    ];
}

echo ScoringCriteria::make();
// @doctest id="95e7"
```

Output:

```
1. **Clarity** -- Writing is clear and unambiguous

2. **Evidence** -- Claims are supported by sources

3. **Structure** -- Argument flows logically
// @doctest id="654d"
```

## YAML Data Files

For larger datasets, load from a YAML file:

```php
class ReviewCriteria extends NodeSet
{
    public string $dataFile = 'criteria.yml';
    public ?string $templateDir = __DIR__ . '/data';
    public string $sortKey = 'priority';
}
// @doctest id="d3b0"
```

The file `data/criteria.yml`:

```yaml
- id: clarity
  label: Clarity
  content: Writing is clear and unambiguous
  priority: 1
- id: evidence
  label: Evidence
  content: Claims are supported by sources
  priority: 2
  children:
    - id: citations
      content: All sources are properly cited
# @doctest id="2363"
```

Items are sorted by `priority` and children render as indented sub-items:

```
1. **Clarity** -- Writing is clear and unambiguous

2. **Evidence** -- Claims are supported by sources
   - All sources are properly cited
// @doctest id="b172"
```

> **Note:** YAML data files require `symfony/yaml`. Install it with `composer require symfony/yaml`.

## Custom Formatting

Override `renderNode()` to change how each item appears:

```php
class NumberedLabels extends NodeSet
{
    public array $items = [
        ['label' => 'Bug', 'content' => 'Functional defect'],
        ['label' => 'Style', 'content' => 'Code style issue'],
    ];

    public function renderNode(int $index, array $node, mixed ...$ctx): string
    {
        return "- [{$node['label']}] {$node['content']}";
    }
}
// @doctest id="4763"
```

Output:

```
- [Bug] Functional defect

- [Style] Code style issue
// @doctest id="0d8e"
```

## Dynamic Data

Override `nodes()` to generate items at runtime:

```php
class DynamicLabels extends NodeSet
{
    public function nodes(mixed ...$ctx): array
    {
        return array_map(
            fn(string $label) => ['label' => $label, 'content' => ''],
            $ctx['labels'] ?? [],
        );
    }
}

echo DynamicLabels::with(labels: ['Bug', 'Feature', 'Chore']);
// @doctest id="29f4"
```

## Using in Composition

NodeSet is a regular Prompt, so it composes naturally:

```php
class ReviewSystem extends Prompt
{
    public function body(mixed ...$ctx): array
    {
        return [
            Persona::with(role: 'reviewer'),
            "## Scoring Criteria",
            ScoringCriteria::make(),
            "## Document\n\n" . $ctx['content'],
        ];
    }
}
// @doctest id="c04d"
```

## Next Steps

- [Variants & Registry](06-variants-and-registry.md) — register and swap prompt implementations
- [Configuration](07-configuration.md) — configure template paths and engine settings
