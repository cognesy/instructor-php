<?php
namespace Cognesy\Instructor\ApiClient\Enums\Traits;

trait HandlesStreamData
{
    public function isDone(string $data): bool {
        return match($this) {
            self::Anthropic => $this->isDoneAnthropic($data),
            self::Cohere => $this->isDoneCohere($data),
            self::Gemini => $this->isDoneGemini($data),
            default => $this->isDoneOpenAI($data),
        };
    }

    public function getData(string $data): string {
        return match($this) {
            self::Anthropic => $this->getDataAnthropic($data),
            self::Cohere => $this->getDataCohere($data),
            self::Gemini => $this->getDataGemini($data),
            default => $this->getDataOpenAI($data),
        };
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    protected function isDoneOpenAI(string $data): bool {
        return $data === '[DONE]';
    }

    protected function getDataOpenAI(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        // ignore event lines
        return '';
    }

    protected function isDoneAnthropic(string $data): bool {
        return $data === 'event: message_stop';
    }

    protected function getDataAnthropic(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        return '';
    }

    protected function isDoneCohere(string $data): bool {
        return $data === '[DONE]';
    }

    protected function getDataCohere(string $data): string {
        return trim($data);
    }

    protected function isDoneGemini(string $data): bool {
        return $data === '[DONE]';
    }

    protected function getDataGemini(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        return '';
    }
}