<?php declare(strict_types=1);

namespace Cognesy\Setup;

final class PublishSummary
{
    private int $published = 0;
    private int $skipped = 0;
    private int $errors = 0;

    public function add(PublishStatus $status): void
    {
        match ($status) {
            PublishStatus::Published => $this->published++,
            PublishStatus::Skipped => $this->skipped++,
            PublishStatus::Error => $this->errors++,
        };
    }

    public function published(): int
    {
        return $this->published;
    }

    public function skipped(): int
    {
        return $this->skipped;
    }

    public function errors(): int
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors > 0;
    }
}
