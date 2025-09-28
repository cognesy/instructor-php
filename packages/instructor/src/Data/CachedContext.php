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
     * @param string|array $messages
     * @param string $system
     * @param string $prompt
     * @param Example[] $examples
     */
    public function __construct(
        string|array $messages = [],
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) {
        $this->messages = match(true) {
            is_string($messages) => Messages::fromString($messages),
            is_array($messages) => Messages::fromArray($messages),
        };
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
            && empty($this->examples->isEmpty());
    }

    // SERIALIZATION ///////////////////////////////////////////////////////////////////

    public function toArray() : array {
        return [
            'messages' => $this->messages,
            'system' => $this->system,
            'prompt' => $this->prompt,
            'examples' => $this->examples->toArray(),
        ];
    }

    public static function fromArray(array $data) : static {
        if (empty($data)) {
            return new CachedContext();
        }

        return new CachedContext(
            messages: $data['messages'] ?? '',
            system: $data['system'] ?? '',
            prompt: $data['prompt'] ?? '',
            examples: $data['examples'] ?? [],
        );
    }
}