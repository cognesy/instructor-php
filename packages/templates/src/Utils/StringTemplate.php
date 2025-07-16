<?php declare(strict_types=1);
namespace Cognesy\Template\Utils;

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\TextRepresentation;

class StringTemplate
{
    const START_MARKER = '<|';
    const END_MARKER = '|>';
    const ESCAPED_START_MARKER = '<\|';
    const ESCAPED_END_MARKER = '\|>';
    const SECTION_MARKER = '@';

    private bool $clearUnknownParams;
    private array $parameters;

    private array $parameterValues = [];
    private array $parameterKeys = [];

    public function __construct(
        array $parameters = [],
        bool  $clearUnknownParams = true,
    ) {
        $this->clearUnknownParams = $clearUnknownParams;
        $this->parameters = $parameters;
        if (empty($parameters)) {
            return;
        }
        // remove keys starting with @ - these are used for section templates
        $filteredParameters = array_filter(
            $parameters,
            fn($key) => substr($key, 0, 1) !== self::SECTION_MARKER,
            ARRAY_FILTER_USE_KEY
        );
        $materializedParameters = $this->materializeParameters($filteredParameters);
        $this->parameterValues = array_values($materializedParameters);
        $this->parameterKeys = array_map(
            fn($key) => $this->varPattern($key),
            array_keys($materializedParameters)
        );
    }

    public function getParameters() : array {
        return $this->parameters;
    }

    public static function render(
        string $template,
        array  $parameters,
        bool   $clearUnknownParams = true,
    ) : string {
        return (new StringTemplate(
            $parameters,
            $clearUnknownParams
        ))->renderString($template);
    }

    public function renderString(string $template): string {
        // find all keys in the template
        $keys = $this->findVars($template);
        if ($this->clearUnknownParams) {
            // find keys missing from $this->keys
            $missingKeys = array_diff($keys, $this->parameterKeys);
            // remove missing key strings from the template
            $template = str_replace($missingKeys, '', $template);
        }
        // render values
        return str_replace($this->parameterKeys, $this->parameterValues, $template);
    }

    public function renderArray(
        array $rows,
        string $field = 'content'
    ): array {
        return array_map(
            fn($item) => $this->renderString($item[$field] ?? ''),
            $rows
        );
    }

    public function renderMessage(array|Message $message) : array {
        $normalized = Message::fromAny($message);

        // skip rendering if content is an array - it may contain non-text data
        if ($normalized->isComposite()) {
            return [
                'role' => $normalized->role()->value,
                'content' => $normalized->content()->toArray()
            ];
        }

        return [
            'role' => $normalized->role()->value,
            'content' => $this->renderString($normalized->content()->toString())
        ];
    }

    public function renderMessages(array|Messages $messages) : array {
        $messages = Messages::fromAny($messages);
        return array_map(
            fn($message) => $this->renderMessage($message),
            $messages->toArray()
        );
    }

    public function getVariableNames(string $content): array {
        return $this->findVars($content);
    }

    // OVERRIDEABLE //////////////////////////////////////////////////////////////

    protected function varPattern(string $key) : string {
        return self::START_MARKER . $key . self::END_MARKER;
    }

    protected function findVars(string $template) : array {
        $matches = [];
        // replace {xxx} pattern with <|xxx|> pattern match
        preg_match_all('/' . self::ESCAPED_START_MARKER . '([^|]+)' . self::ESCAPED_END_MARKER . '/', $template, $matches);
        return $matches[0];
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    protected static function cleanVarMarkers(string $template) : string {
        return str_replace([
            self::START_MARKER,
            self::END_MARKER,
        ], '', $template);
    }

    private function materializeParameters(array $parameters) : array {
        $parameterValues = [];
        foreach ($parameters as $key => $value) {
            $parameterValues[$key] = TextRepresentation::fromParameter($value, $key, $parameters);
        }
        return $parameterValues;
    }
}
