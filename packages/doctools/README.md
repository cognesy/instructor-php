# Doctools Package

Documentation automation toolkit used in InstructorPHP.

It covers:
- documentation generation (Mintlify, MkDocs, LLM docs)
- markdown code block extraction and validation (doctest flow)
- docs quality checks
- code screenshot generation via Freeze

## Example

```php
<?php

use Cognesy\Doctools\Freeze\Freeze;
use Cognesy\Doctools\Freeze\FreezeConfig;

$result = Freeze::file('examples/hello.php')
    ->output('docs/images/hello.png')
    ->theme(FreezeConfig::THEME_DRACULA)
    ->window()
    ->run();

if ($result->failed()) {
    throw new RuntimeException($result->getErrorOutput());
}
```

## Documentation

- `packages/doctools/docs/index.md`
- `packages/doctools/OVERVIEW.md`
- `packages/doctools/CHEATSHEET.md`
