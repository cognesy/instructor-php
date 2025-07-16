<?php declare(strict_types=1);

namespace Cognesy\Template\Data;

use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Template\Enums\FrontMatterFormat;
use InvalidArgumentException;
use Webuni\FrontMatter\FrontMatter;
use Webuni\FrontMatter\Processor\JsonProcessor;
use Webuni\FrontMatter\Processor\TomlProcessor;
use Webuni\FrontMatter\Processor\YamlProcessor;

class TemplateInfo
{
    private FrontMatter $engine;
    private array $templateData;
    private string $templateContent;

    public function __construct(
        string $content,
        private ?TemplateEngineConfig $config = null,
    ) {
        $startTag = $this->config->frontMatterTags[0] ?? '---';
        $endTag = $this->config->frontMatterTags[1] ?? '---';
        $format = $this->config->frontMatterFormat;
        $this->engine = $this->makeEngine($format, $startTag, $endTag);

        $document = $this->engine->parse($content);
        $this->templateData = $document->getData();
        $this->templateContent = $document->getContent();
    }

    public function field(string $name) : mixed {
        return $this->templateData[$name] ?? null;
    }

    public function hasField(string $name) : bool {
        return array_key_exists($name, $this->templateData);
    }

    public function data() : array {
        return $this->templateData;
    }

    public function content() : string {
        return $this->templateContent;
    }

    public function variables() : array {
        return $this->field('variables') ?? [];
    }

    public function variableNames() : array {
        return array_keys($this->variables());
    }

    public function hasVariables() : bool {
        return $this->hasField('variables');
    }

    public function schema() : array {
        return $this->field('schema') ?? [];
    }

    public function hasSchema() : bool {
        return $this->hasField('schema');
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeEngine(FrontMatterFormat $format, string $startTag, string $endTag) : FrontMatter {
        return match($format) {
            FrontMatterFormat::Yaml => new FrontMatter(new YamlProcessor(), $startTag, $endTag),
            FrontMatterFormat::Json => new FrontMatter(new JsonProcessor(), $startTag, $endTag),
            FrontMatterFormat::Toml => new FrontMatter(new TomlProcessor(), $startTag, $endTag),
            default => throw new InvalidArgumentException("Unknown front matter format: $format->value"),
        };
    }
}
