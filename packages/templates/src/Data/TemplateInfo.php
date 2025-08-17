<?php declare(strict_types=1);

namespace Cognesy\Template\Data;

use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Utils\Markdown\FrontMatter;

class TemplateInfo
{
    private array $templateData;
    private string $templateContent;

    public function __construct(
        string $content,
        private ?TemplateEngineConfig $config = null,
    ) {
        $document = FrontMatter::parse($content);
        $this->templateData = $document->data();
        $this->templateContent = $document->document();
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
}
