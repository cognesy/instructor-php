<?php
namespace Cognesy\Instructor\Extras\Task;

use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Template;

class PromptTask extends ExecutableTask
{
    private Instructor $instructor;
    private string $model;
    private array $options;

    public function __construct(
        string|Signature $signature,
        Instructor $instructor,
        string $model = '',
        array $options = [],
    ) {
        parent::__construct($signature);
        $this->instructor = $instructor;
        $this->model = $model;
        $this->options = $options;
    }

    protected function forward(string $input, array $context = []): mixed {
        $messages = match(true) {
            empty($input) => throw new \Exception('Empty input'),
            is_string($input) => [['role' => 'user', 'content' => Template::render($input, $context)]],
            default => throw new \Exception('Invalid input type'),
        };
        return $this->instructor->client()->chatCompletion(
            messages: $messages,
            model: $this->model,
            options: $this->options,
        )->get();
    }
}
