<?php

namespace Cognesy\Instructor\Extras\Prompt;

use Cognesy\Instructor\Extras\Prompt\Contracts\CanHandleTemplate;
use Cognesy\Instructor\Extras\Prompt\Data\PromptEngineConfig;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Str;
use Cognesy\Instructor\Utils\Xml;
use InvalidArgumentException;

class Prompt
{
    const DSN_SEPARATOR = ':';

    private PromptLibrary $library;
    private PromptInfo $promptInfo;

    private string $templateContent;
    private array $variableValues;
    private string $rendered;

    public function __construct(
        string             $path = '',
        string             $library = '',
        PromptEngineConfig $config = null,
        CanHandleTemplate  $driver = null,
    ) {
        $this->library = new PromptLibrary($library, $config, $driver);
        $this->templateContent = $path ? $this->library->loadTemplate($path) : '';
    }

    public static function twig() : self {
        return new self(config: PromptEngineConfig::twig());
    }

    public static function blade() : self {
        return new self(config: PromptEngineConfig::blade());
    }

    public static function make(string $pathOrDsn) : Prompt {
        return match(true) {
            Str::contains($pathOrDsn, self::DSN_SEPARATOR) => self::fromDsn($pathOrDsn),
            default => new self(path: $pathOrDsn),
        };
    }

    public static function using(string $library) : Prompt {
        return new self(library: $library);
    }

    public static function text(string $pathOrDsn, array $variables) : string {
        return self::make($pathOrDsn)->withValues($variables)->toText();
    }

    public static function messages(string $pathOrDsn, array $variables) : Messages {
        return self::make($pathOrDsn)->withValues($variables)->toMessages();
    }

    public static function fromDsn(string $dsn) : Prompt {
        if (!Str::contains($dsn, self::DSN_SEPARATOR)) {
            throw new InvalidArgumentException("Invalid DSN: $dsn - missing separator");
        }
        $parts = explode(self::DSN_SEPARATOR, $dsn, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Invalid DSN: `$dsn` - failed to parse");
        }
        return new self(path: $parts[1], library: $parts[0]);
    }

    public function withLibrary(string $library) : self {
        $this->library->get($library);
        return $this;
    }

    public function withConfig(PromptEngineConfig $config) : self {
        $this->library->withConfig($config);
        return $this;
    }

    public function withDriver(CanHandleTemplate $driver) : self {
        $this->library->withDriver($driver);
        return $this;
    }

    public function get(string $path) : self {
        return $this->withTemplate($path);
    }

    public function withTemplate(string $path) : self {
        $this->templateContent = $this->library->loadTemplate($path);
        $this->promptInfo = new PromptInfo($this->templateContent, $this->library->config());
        return $this;
    }

    public function withTemplateContent(string $content) : self {
        $this->templateContent = $content;
        $this->promptInfo = new PromptInfo($this->templateContent, $this->library->config());
        return $this;
    }

    public function with(array $values) : self {
        return $this->withValues($values);
    }

    public function from(string $string) : self {
        $this->withTemplateContent($string);
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
        return $this->library->config();
    }

    public function params() : array {
        return $this->variableValues;
    }

    public function template() : string {
        return $this->templateContent;
    }

    public function variables() : array {
        return $this->library->getVariableNames($this->templateContent);
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
            $rendered = $this->library->renderString($this->templateContent, $this->variableValues);
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

    private function hasChatRoles(string $text) : bool {
        $roleStrings = [
            '<chat>', '<user>', '<assistant>', '<system>', '<section>', '<message>'
        ];
        if (Str::containsAny($text, $roleStrings)) {
            return true;
        }
        return false;
    }

    private function containsXml(string $text) : bool {
        return preg_match('/<[^>]+>/', $text) === 1;
    }

    private function makeMessagesFromXml(string $text) : Messages {
        $messages = new Messages();
        $xml = match(Str::contains($text, '<chat>')) {
            true => Xml::from($text)->toArray(),
            default => Xml::from($text)->wrapped('chat')->toArray(),
        };
        // TODO: validate
        foreach ($xml as $key => $message) {
            $messages->appendMessage(Message::make($key, $message));
        }
        return $messages;
    }
}