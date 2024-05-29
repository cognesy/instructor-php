<?php

namespace Cognesy\Instructor\Extras\Tasks\Task\Traits;

use Cognesy\Instructor\Extras\Tasks\Events\TaskCompleted;
use Cognesy\Instructor\Extras\Tasks\Task\Enums\TaskStatus;
use DateTime;

trait HandlesTaskStatus
{
    private TaskStatus $status;

    // HANDLERS
    private array $onSuccess = [];
    private array $onFailure = [];

    public function status(): TaskStatus {
        return $this->status;
    }

    public function isReady() : bool {
        return $this->status === TaskStatus::Ready;
    }

    public function isRunning() : bool {
        return $this->status === TaskStatus::InProgress;
    }

    public function isFailed() : bool {
        return $this->status === TaskStatus::Failed;
    }

    public function isCompleted() : bool {
        return $this->status === TaskStatus::Completed;
    }

    protected function changeStatus(TaskStatus $status): void {
        $this->status = $status;
        $this->updatedAt = new DateTime();
        if ($status === TaskStatus::Completed) {
            foreach ($this->onSuccess as $callback) {
                $callback($this);
            }
        }
        if ($status === TaskStatus::Failed) {
            foreach ($this->onFailure as $callback) {
                $callback($this);
            }
        }
    }

    public function onSuccess(callable $callback): static {
        $this->onSuccess[] = $callback;
        return $this;
    }

    public function onFailure(callable $callback): static {
        $this->onFailure[] = $callback;
        return $this;
    }
}