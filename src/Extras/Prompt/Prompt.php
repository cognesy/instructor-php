<?php

namespace Cognesy\Instructor\Extras\Prompt;

use Cognesy\Instructor\Extras\Prompt\Contracts\CanHandleTemplate;
use Cognesy\Instructor\Extras\Prompt\Data\PromptEngineConfig;
use Cognesy\Instructor\Extras\Prompt\Drivers\BladeDriver;
use Cognesy\Instructor\Extras\Prompt\Drivers\TwigDriver;
use Cognesy\Instructor\Extras\Prompt\Enums\TemplateType;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Settings;
use Cognesy\Instructor\Utils\Str;
use Cognesy\Instructor\Utils\Xml;
use InvalidArgumentException;

class Prompt
{
    private CanHandleTemplate $driver;
    private PromptEngineConfig $config;
    private PromptInfo $promptInfo;

    private string $templateContent;
    private array $variableValues;
    private string $rendered;

    public function __construct(
        string              $name = '',
        string              $setting = '',
        PromptEngineConfig  $config = null,
        CanHandleTemplate   $driver = null,
    ) {
        $this->config = $config ?? PromptEngineConfig::load(
            setting: $setting ?: Settings::get('prompt', "defaultSetting")
        );
        $this->driver = $driver ?? $this->makeDriver($this->config);
        $this->templateContent = $name ? $this->load($name) : '';
    }

    public static function using(string $setting) : Prompt {
        return new self(setting: $setting);
    }

    public static function get(string $name, string $setting = '') : Prompt {
        return new self(name: $name, setting: $setting);
    }

    public static function text(string $name, array $variables, string $setting = '') : string {
        return (new self(name: $name, setting: $setting))->withValues($variables)->toText();
    }

    public static function messages(string $name, array $variables, string $setting = '') : Messages {
        return (new self(name: $name, setting: $setting))->withValues($variables)->toMessages();
    }

    public function withSetting(string $setting) : self {
        $this->config = PromptEngineConfig::load($setting);
        $this->driver = $this->makeDriver($this->config);
        return $this;
    }

    public function withConfig(PromptEngineConfig $config) : self {
        $this->config = $config;
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    public function withDriver(CanHandleTemplate $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    public function withTemplate(string $name) : self {
        $this->templateContent = $this->load($name);
        $this->promptInfo = new PromptInfo($this->templateContent, $this->config);
        return $this;
    }

    public function withTemplateContent(string $content) : self {
        $this->templateContent = $content;
        $this->promptInfo = new PromptInfo($this->templateContent, $this->config);
        return $this;
    }

    public function withValues(array $values) : self {
        $this->variableValues = $values;
        return $this;
    }

    public function toText() : string {
        return $this->rendered();
    }

    public function toMessages() : Messages {
        return $this->makeMessages($this->rendered());
    }

    public function toArray() : array {
        return $this->toMessages()->toArray();
    }

    public function config() : PromptEngineConfig {
        return $this->config;
    }

    public function params() : array {
        return $this->variableValues;
    }

    public function template() : string {
        return $this->templateContent;
    }

    public function variables() : array {
        return $this->driver->getVariableNames($this->templateContent);
    }

    public function info() : PromptInfo {
        return $this->promptInfo;
    }

    public function validationErrors() : array {
        $infoVars = $this->info()->variableNames();
        $templateVars = $this->variables();
        $valueKeys = array_keys($this->variableValues);

        $messages = [];
        foreach($infoVars as $var) {
            if (!in_array($var, $valueKeys)) {
                $messages[] = "$var: variable defined in template info, but value not provided";
            }
            if (!in_array($var, $templateVars)) {
                $messages[] = "$var: variable defined in template info, but not used";
            }
        }
        foreach($valueKeys as $var) {
            if (!in_array($var, $infoVars)) {
                $messages[] = "$var: value provided, but not defined in template info";
            }
            if (!in_array($var, $templateVars)) {
                $messages[] = "$var: value provided, but not used in template content";
            }
        }
        foreach($templateVars as $var) {
            if (!in_array($var, $infoVars)) {
                $messages[] = "$var: variable used in template, but not defined in template info";
            }
            if (!in_array($var, $valueKeys)) {
                $messages[] = "$var: variable used in template, but value not provided";
            }
        }
        return $messages;
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function rendered() : string {
        if (!isset($this->rendered)) {
            $rendered = $this->render($this->templateContent, $this->variableValues);
            $this->rendered = $rendered;
        }
        return $this->rendered;
    }

    private function makeMessages(string $text) : Messages {
        return match(true) {
            $this->containsXml($text) && $this->hasRoles() => $this->makeMessagesFromXml($text),
            default => Messages::fromString($text),
        };
    }

    private function hasRoles() : string {
        $roleStrings = [
            '<user>', '<assistant>', '<system>'
        ];
        if (Str::contains($this->rendered(), $roleStrings)) {
            return true;
        }
        return false;
    }

    private function containsXml(string $text) : bool {
        return preg_match('/<[^>]+>/', $text) === 1;
    }

    private function makeMessagesFromXml(string $text) : Messages {
        $messages = new Messages();
        $xml = Xml::from($text)->wrapped('chat')->toArray();
        // TODO: validate
        foreach ($xml as $key => $message) {
            $messages->appendMessage(Message::make($key, $message));
        }
        return $messages;
    }

    private function makeDriver(PromptEngineConfig $config) : CanHandleTemplate {
        return match($config->templateType) {
            TemplateType::Twig => new TwigDriver($config),
            TemplateType::Blade => new BladeDriver($config),
            default => throw new InvalidArgumentException("Unknown driver: $config->templateType"),
        };
    }

    private function load(string $path) : string {
        return $this->driver->getTemplateContent($path);
    }

    private function render(string $template, array $parameters = []) : string {
        return $this->driver->renderString($template, $parameters);
    }
}