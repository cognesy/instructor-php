<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Instructor\Extras\Example\ExampleList;
use Cognesy\Messages\Messages;

final readonly class CachedContext
{
    private Messages $messages;
    private string $system;
    private string $prompt;
    private ExampleList $examples;

    /**
     * @param Example[] $examples
     * @param string $system
     * @param string $prompt
     */
    public function __construct(
        ?Messages $messages = null,
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) {
        $this->messages = $messages ?? Messages::empty();
        $this->system = $system;
        $this->prompt = $prompt;
        $this->examples = new ExampleList(...$examples);
    }

    // ACCESSORS ///////////////////////////////////////////////////////////////////////

    public function messages() : Messages {
        return $this->messages;
    }

    public function system() : string {
        return $this->system;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function examples() : array {
        return $this->examples->all();
    }

    public function isEmpty() : bool {
        return $this->messages->isEmpty()
            && empty($this->system)
            && empty($this->prompt)
            && $this->examples->isEmpty();
    }

    // SERIALIZATION ///////////////////////////////////////////////////////////////////

    public function toArray() : array {
        return [
            'messages' => $this->messages->toArray(),
            'system' => $this->system,
            'prompt' => $this->prompt,
            'examples' => $this->examples->toArray(),
        ];
    }

    public static function fromArray(array $data) : static {
        if (empty($data)) {
            return new CachedContext();
        }

        $rawExamples = $data['examples'] ?? [];
        $examples = match (true) {
            $rawExamples instanceof ExampleList => $rawExamples->all(),
            is_array($rawExamples) => self::examplesFromArray($rawExamples),
            default => [],
        };

        return new CachedContext(
            messages: self::messagesFromArray($data),
            system: $data['system'] ?? '',
            prompt: $data['prompt'] ?? '',
            examples: $examples,
        );
    }

    private static function messagesFromArray(array $data) : Messages {
        $messages = $data['messages'] ?? [];

        return match (true) {
            $messages instanceof Messages => $messages,
            is_array($messages) => Messages::fromAnyArray($messages),
            is_string($messages) && $messages !== '' => Messages::fromString($messages),
            default => Messages::empty(),
        };
    }

    private static function examplesFromArray(array $data) : array {
        return array_values(array_filter(array_map(
            fn(mixed $example) => match (true) {
                $example instanceof Example => $example,
                is_array($example) => Example::fromArray($example),
                is_string($example) => Example::fromJson($example),
                default => null,
            },
            $data,
        )));
    }
}
