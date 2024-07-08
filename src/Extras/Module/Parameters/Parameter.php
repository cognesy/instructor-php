<?php
namespace Cognesy\Instructor\Extras\Module\Parameters;

use Closure;
use Cognesy\Instructor\Extras\Module\Core\Feedback;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated(reason: 'Not used currently - to be removed')]
class Parameter
{
    private mixed $value;
    private array $feedback = [];
    private ?Closure $feedbackFn = null;
    private array $predecessors = [];
    private bool $requiresFeedback;
    private string $roleDescription;

    public function __construct(
        mixed  $value,
        bool   $requiresFeedback = true,
        string $roleDescription = '',
        array  $predecessors = []
    ) {
        $this->value = $value;
        $this->requiresFeedback = $requiresFeedback;
        $this->roleDescription = $roleDescription;
        $this->predecessors = $predecessors;
    }

    public function getValue(): mixed {
        return $this->value;
    }

    public function setValue(mixed $value): void {
        $this->value = $value;
    }

    public function addFeedback(Feedback $feedback): void {
        if ($this->requiresFeedback) {
            $this->feedback[] = $feedback;
        }
    }

    public function getFeedback(): array {
        return $this->feedback;
    }

    public function setFeedbackFn(Closure $feedbackFn): void {
        $this->feedbackFn = $feedbackFn;
    }

    public function backward(Feedback $feedback = null): void {
        if (!$this->requiresFeedback) {
            return;
        }

        if ($feedback) {
            $this->addFeedback($feedback);
        }

        if ($this->feedbackFn) {
            ($this->feedbackFn)($this);
        }

        foreach ($this->predecessors as $predecessor) {
            $predecessor->backward();
        }
    }

    public function getRoleDescription(): string {
        return $this->roleDescription;
    }

    public function clearFeedback(): void {
        $this->feedback = [];
    }
}
