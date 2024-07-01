<?php

namespace Cognesy\Instructor\Extras\Module\Call\Traits;

use DateTime;
use DateTimeImmutable;

trait HandlesCallInfo
{
    private string $id;
    private DateTimeImmutable $createdAt;
    private DateTime $updatedAt;

    public function id(): string {
        return $this->id;
    }

    public function name(): string {
        return $this->className();
        //$signature = $this->signature()->toShortSignature();
        //return "{$className}({$signature})";
    }

    public function createdAt(): DateTimeImmutable {
        return $this->createdAt;
    }

    public function updatedAt(): DateTime {
        return $this->updatedAt;
    }

    private function className() : string {
        $parts = explode('\\', static::class);
        return $parts[count($parts) - 1];
    }
}
