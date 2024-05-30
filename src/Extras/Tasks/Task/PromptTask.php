<?php
namespace Cognesy\Instructor\Extras\Tasks\Task;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Template;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class PromptTask extends ExecutableTask
{
    private HasSignature|string $requestedSignature;
    private Instructor $instructor;
    private string $model;
    private array $options;

    public function __construct(
        string|HasSignature $signature,
        Instructor          $instructor,
        string              $model = '',
        array               $options = [],
    ) {
        $this->requestedSignature = $signature;
        $this->instructor = $instructor;
        $this->model = $model;
        $this->options = $options;
        parent::__construct();
    }

    public function signature(): string|HasSignature {
        return $this->requestedSignature;
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
