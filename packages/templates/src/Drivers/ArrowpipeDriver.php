<?php

namespace Cognesy\Template\Drivers;

use Cognesy\Config\BasePath;
use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Template\Contracts\CanHandleTemplate;
use Cognesy\Template\Utils\StringTemplate;

class ArrowpipeDriver implements CanHandleTemplate
{
    private string $baseDir;
    private string $extension;
    private $clearUnknownParams = false;

    public function __construct(
        private TemplateEngineConfig $config,
    ) {
        $this->baseDir = BasePath::get($this->config->resourcePath);
        $this->extension = $this->config->extension;
    }

    public function renderFile(string $path, array $parameters = []): string {
        $content = $this->getTemplateContent($path);
        return $this->renderString($content, $parameters);
    }

    public function renderString(string $content, array $parameters = []): string {
        $template = new StringTemplate(
            parameters: $parameters,
            clearUnknownParams: $this->clearUnknownParams,
        );
        return $template->renderString($content);
    }

    public function getTemplateContent(string $path): string {
        $filePath = $this->baseDir . $path . $this->extension;
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Template file not found: $filePath");
        }
        return file_get_contents($filePath);
    }

    public function getVariableNames(string $content): array {
        $template = new StringTemplate(
            parameters: [],
            clearUnknownParams: false,
        );
        return $template->getVariableNames($content);
    }
}
