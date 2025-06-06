<?php

namespace Cognesy\Template;

use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Template\Contracts\CanHandleTemplate;
use Cognesy\Template\Data\TemplateInfo;
use Cognesy\Template\Script\Script;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\Str;
use Cognesy\Utils\Xml\Xml;
use Cognesy\Utils\Xml\XmlElement;
use InvalidArgumentException;

class Template
{
    const DSN_SEPARATOR = ':';

    private TemplateProvider $provider;
    private TemplateInfo $templateInfo;

    private string $templateContent;
    private array $variableValues;
    private string $rendered;
    private $tags = ['chat', 'message', 'content', 'section'];

    public function __construct(
        string                $path = '',
        string                $preset = '',
        ?TemplateEngineConfig $config = null,
        ?CanHandleTemplate    $driver = null,
    ) {
        $this->provider = new TemplateProvider($preset, $config, $driver);
        $this->templateContent = $path ? $this->provider->loadTemplate($path) : '';
    }

    public static function twig() : self {
        return new self(config: TemplateEngineConfig::twig());
    }

    public static function blade() : self {
        return new self(config: TemplateEngineConfig::blade());
    }

    public static function arrowpipe() : self {
        return new self(config: TemplateEngineConfig::arrowpipe());
    }

    public static function make(string $pathOrDsn) : static {
        return match(true) {
            Str::contains($pathOrDsn, self::DSN_SEPARATOR) => self::fromDsn($pathOrDsn),
            default => new self(path: $pathOrDsn),
        };
    }

    public static function using(string $preset) : static {
        return new self(preset: $preset);
    }

    public static function text(string $pathOrDsn, array $variables) : string {
        return self::make($pathOrDsn)->withValues($variables)->toText();
    }

    public static function messages(string $pathOrDsn, array $variables) : Messages {
        return self::make($pathOrDsn)->withValues($variables)->toMessages();
    }

    public static function fromDsn(string $dsn) : static {
        if (!Str::contains($dsn, self::DSN_SEPARATOR)) {
            throw new InvalidArgumentException("Invalid DSN: $dsn - missing separator");
        }
        $parts = explode(self::DSN_SEPARATOR, $dsn, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Invalid DSN: `$dsn` - failed to parse");
        }
        return new self(path: $parts[1], preset: $parts[0]);
    }

    public function withPreset(string $preset) : self {
        $this->provider->get($preset);
        return $this;
    }

    public function withConfig(TemplateEngineConfig $config) : self {
        $this->provider->withConfig($config);
        return $this;
    }

    public function withDriver(CanHandleTemplate $driver) : self {
        $this->provider->withDriver($driver);
        return $this;
    }

    public function get(string $path) : self {
        return $this->withTemplate($path);
    }

    public function withTemplate(string $path) : self {
        $this->templateContent = $this->provider->loadTemplate($path);
        $this->templateInfo = new TemplateInfo($this->templateContent, $this->provider->config());
        return $this;
    }

    public function withTemplateContent(string $content) : self {
        $this->templateContent = $content;
        $this->templateInfo = new TemplateInfo($this->templateContent, $this->provider->config());
        return $this;
    }

    public function from(string $content) : self {
        $this->withTemplateContent($content);
        return $this;
    }

    public function with(array $values) : self {
        return $this->withValues($values);
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

    public function toScript() : Script {
        return $this->makeScript($this->rendered());
    }

    public function toArray() : array {
        return $this->toMessages()->toArray();
    }

    public function config() : TemplateEngineConfig {
        return $this->provider->config();
    }

    public function params() : array {
        return $this->variableValues;
    }

    public function template() : string {
        return $this->templateContent;
    }

    public function variables() : array {
        return $this->provider->getVariableNames($this->templateContent);
    }

    public function info() : TemplateInfo {
        return $this->templateInfo;
    }

    public function validationErrors() : array {
        $infoVars = $this->info()->variableNames();
        $templateVars = $this->variables();
        $valueKeys = array_keys($this->variableValues);
        return $this->validateVariables($infoVars, $templateVars, $valueKeys);
    }

    public function renderMessage(Message $message) : Message {
        $newMessage = $message->clone();
        $newMessage->removeContent();
        foreach($message->contentParts() as $part) {
            $newPart = $part->clone();
            if ($part->isTextPart()) {
                $renderedValue = $this->provider->renderString($part->toString(), $this->variableValues);
                $newPart->set('text', $renderedValue);
            }
            $newMessage->addContentPart($newPart);
        }
        return $newMessage;
    }

    public function renderMessages(Messages $messages) : Messages {
        $newMessages = new Messages();
        foreach ($messages->each() as $message) {
            $newMessages->appendMessage($this->renderMessage($message));
        }
        return $newMessages;
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function rendered() : string {
        if (!isset($this->rendered)) {
            $rendered = $this->provider->renderString($this->templateContent, $this->variableValues);
            $this->rendered = $rendered;
        }
        return $this->rendered;
    }

    private function makeMessages(string $text) : Messages {
        return match(true) {
            $this->containsXml($text) && $this->hasChatRoles($text) => $this->makeMessagesFromXml($text),
            default => Messages::fromString($text),
        };
    }

    private function makeScript(string $text) : Script {
        return match(true) {
            $this->containsXml($text) && $this->hasChatRoles($text) => $this->makeScriptFromXml($text),
            default => Messages::fromString($text),
        };
    }

    private function hasChatRoles(string $text) : bool {
        $roleStrings = [
            '<chat>', '<message>', '<section>'
        ];
        if (Str::containsAny($text, $roleStrings)) {
            return true;
        }
        return false;
    }

    private function containsXml(string $text) : bool {
        return preg_match('/<[^>]+>/', $text) === 1;
    }

    private function makeScriptFromXml(string $text) : Script {
        $xml = Xml::from($text)->withTags($this->tags)->toXmlElement();
        $script = new Script();
        $section = $script->section('messages');
        foreach ($xml->children() as $element) {
            if ($element->tag() === 'section') {
                $section = $script->section($element->attribute('name') ?? 'messages');
                continue;
            }
            if ($element->tag() !== 'message') {
                continue;
            }
            $section->appendMessage(Message::make(
                role: $element->attribute('role', 'user'),
                content: match(true) {
                    $element->hasChildren() => $this->getMessageContent($element),
                    default => $element->content(),
                }
            ));
        }
        return $script;
    }

    private function makeMessagesFromXml(string $text) : Messages {
        $xml = Xml::from($text)->withTags($this->tags)->toXmlElement();
        $messages = new Messages();
        foreach ($xml->children() as $element) {
            if ($element->tag() !== 'message') {
                continue;
            }
            $messages->appendMessage(Message::make(
                role: $element->attribute('role', 'user'),
                content: match(true) {
                    $element->hasChildren() => $this->getMessageContent($element),
                    default => $element->content(),
                }
            ));
        }
        return $messages;
    }

    private function getMessageContent(XmlElement $element) : array {
        $content = [];
        foreach ($element->children() as $child) {
            if ($child->tag() !== 'content') {
                continue;
            }
            $type = $child->attribute('type', 'text');
            $content[] = match($type) {
                'image' => $this->makeImageContent($child),
                'audio' => $this->makeAudioContent($child),
                default => $this->makeTextContent($child),
            };
        }
        return $content;
    }

    private function makeTextContent(XmlElement $child) : array {
        $hasCacheControl = $child->attribute('cache', false);
        return array_filter([
            'type' => 'text',
            'text' => $child->content(),
            'cache_control' => $hasCacheControl ? ['type' => 'ephemeral'] : []
        ]);
    }

    private function makeImageContent(XmlElement $child) : array {
        return [
            'type' => 'image_url',
            'image_url' => [
                'url' => $child->content()
            ]
        ];
    }

    private function makeAudioContent(XmlElement $child) : array {
        return [
            'type' => 'input_audio',
            'input_audio' => [
                'data' => $child->content(),
                'format' => $child->attribute('format', 'mp3')
            ]
        ];
    }

    private function validateVariables(array $infoVars, array $templateVars, array $valueKeys) : array {
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
}