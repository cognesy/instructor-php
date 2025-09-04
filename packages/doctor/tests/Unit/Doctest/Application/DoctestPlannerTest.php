<?php

use Cognesy\Doctor\Doctest\Services\DoctestPlanner;
use Cognesy\Doctor\Markdown\MarkdownFile;

function norm_path(string $p): string { return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p); }

it('plans extraction for doctest blocks including regions', function () {
    $md = <<<'MD'
---
title: Intro
doctest_case_dir: examples
doctest_included_types: [php]
---

```php id: demo
// @doctest id="demo"
echo "hi";
// @doctest-region-start name=part
echo "a";
// @doctest-region-end
```
MD;

    $markdown = MarkdownFile::fromString($md, '/docs/01-intro.md');
    $planner = new DoctestPlanner();
    $plan = $planner->planForMarkdown($markdown, null);

    expect($plan)->toHaveCount(1);
    $item = $plan[0];
    expect($item->id)->toBe('demo');
    expect($item->language)->toBe('php');
    expect($item->path)->toEndWith(DIRECTORY_SEPARATOR . norm_path('docs/examples/intro_demo.php'));

    $regions = $item->regions;
    expect($regions)->toBeArray()->and(count($regions))->toBe(1);
    expect($regions[0]->name)->toBe('part');
    expect($regions[0]->path)->toEndWith(DIRECTORY_SEPARATOR . norm_path('docs/examples/intro_demo_part.php'));
});

it('overlays target directory when provided', function () {
    $md = <<<'MD'
---
title: Intro
doctest_case_dir: examples
doctest_included_types: [php]
---

```php id: demo
// @doctest id="demo"
echo "hi";
```
MD;

    $markdown = MarkdownFile::fromString($md, '/docs/01-intro.md');
    $planner = new DoctestPlanner();
    $plan = $planner->planForMarkdown($markdown, '/tmp/out');

    expect($plan)->toHaveCount(1);
    $item = $plan[0];
    expect($item->path)->toEndWith(DIRECTORY_SEPARATOR . norm_path('tmp/out/examples/intro_demo.php'));
});
