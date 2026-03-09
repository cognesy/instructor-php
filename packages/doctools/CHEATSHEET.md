# Doctools Package Cheatsheet

Code-verified reference for `packages/doctools`.

## Main Entry Point

`Cognesy\Doctools\Docs` is a Symfony Console `Application` with built-in commands.

```php
<?php

use Cognesy\Doctools\Docs;

$app = new Docs();
exit($app->run());
```

Registered command names:
- `gen:examples`
- `gen:packages`
- `clear`
- `gen:mintlify`
- `gen:mkdocs`
- `clear:mkdocs`
- `gen:llms`
- `mark`
- `mark-dir`
- `extract`
- `validate`
- `qa`
- `lesson:make`
- `lesson:image`

Note:
- `clear` clears Mintlify docs (`ClearMintlifyDocsCommand`).

## Command Options

Doctest commands:
- `mark`: `--source` (required), `--output` (optional)
- `mark-dir`: `--source-dir` (required), `--target-dir` (required), `--extensions` (default: `md,mdx`), `--dry-run`
- `extract`: `--source` or `--source-dir`, `--target-dir`, `--extensions` (default: `md,mdx`), `--dry-run`, `--modify-source`
- `validate`: `--source` or `--source-dir`, `--extensions` (default: `md,mdx`), `--show-all`, `--show-progress`, `--show-paths`

Quality command:
- `qa`: `--source-dir` (default: `docs`), `--repo-root`, `--profile` (`instructor|http-client|polyglot|none`, default: `instructor`), `--extensions` (default: `md`), `--rules`, `--ast-grep-bin` (default: `ast-grep`), `--format` (`text|json`), `--strict/--no-strict`

Docgen commands:
- `gen:mintlify`: `--packages-only`, `--examples-only`
- `gen:mkdocs`: `--packages-only`, `--examples-only`, `--with-llms`
- `gen:llms`: `--deploy`, `--target`, `--index-only`, `--full-only`

## Freeze API

Entry points:

```php
use Cognesy\Doctools\Freeze\Freeze;

$fromFile = Freeze::file('examples/demo.php');
$fromCommand = Freeze::execute('php -v');
```

Common `FreezeCommand` methods:
- `output(string $path)`
- `language(string $language)`
- `theme(string $theme)`
- `config(string $config)`
- `window(bool $enabled = true)`
- `showLineNumbers(bool $enabled = true)`
- `background(string $color)`
- `height(int $height)`
- `fontFamily(string $family)`
- `fontSize(int $size)`
- `lineHeight(float $height)`
- `borderRadius(int $radius)`
- `borderWidth(int $width)`
- `borderColor(string $color)`
- `padding(string $padding)`
- `margin(string $margin)`
- `lines(string $lines)`
- `setExecutor(CanExecuteCommand $executor)`
- `run(): FreezeResult`
- `buildCommandString(): string`

`FreezeResult` helpers:
- `isSuccessful()`
- `failed()`
- `getOutput()`
- `getErrorOutput()`
- `getCommand()`
- `getOutputPath()`
- `hasOutputFile()`

Theme/config/format constants live in `Cognesy\Doctools\Freeze\FreezeConfig`.

## Markdown + Doctest APIs

`MarkdownFile`:
- `MarkdownFile::fromString(string $text, string $path = '', array $metadata = [])`
- `codeBlocks(): Iterator`
- `codeBlock(string $id): CodeBlockManipulator`
- `withInlinedCodeBlocks(): self`
- `metadata(string $key, mixed $default = null): mixed`
- `withMetadata(string $key, mixed $value): self`
- `toString(MetadataStyle $metadataStyle = MetadataStyle::Comments): string`

`DoctestFile`:
- `DoctestFile::fromMarkdown(MarkdownFile $markdownFile): Iterator`
- `toFileContent(?string $region = null): string`
- `getAvailableRegions(): array`
- `hasRegions(): bool`
- `extractRegion(string $regionName): ?string`
- `getIdFromCode(): ?string`
- `getEffectiveCaseDir(MarkdownFile $markdown): string`
- `getEffectiveCasePrefix(MarkdownFile $markdown): string`
