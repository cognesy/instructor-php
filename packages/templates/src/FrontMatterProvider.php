<?php

namespace Cognesy\Template;

use Cognesy\Template\Enums\FrontMatterFormat;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use Webuni\FrontMatter\FrontMatter;
use Webuni\FrontMatter\Processor\JsonProcessor;
use Webuni\FrontMatter\Processor\TomlProcessor;
use Webuni\FrontMatter\Processor\YamlProcessor;

#[Deprecated]
class FrontMatterProvider
{
    public function get(
        string $startTag,
        string $endTag,
        string $format,
    ) : FrontMatter {
        return match($format) {
            FrontMatterFormat::Yaml => new FrontMatter(new YamlProcessor(), $startTag, $endTag),
            FrontMatterFormat::Json => new FrontMatter(new JsonProcessor(), $startTag, $endTag),
            FrontMatterFormat::Toml => new FrontMatter(new TomlProcessor(), $startTag, $endTag),
            default => throw new InvalidArgumentException("Unknown front matter format: $format->value"),
        };
    }
}