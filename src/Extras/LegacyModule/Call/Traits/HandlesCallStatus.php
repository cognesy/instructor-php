<?php
namespace Cognesy\Instructor\Extras\Module\Call\Traits;

use Cognesy\Instructor\Extras\Module\Call\Enums\CallStatus;
use DateTime;

trait HandlesCallStatus
{
    private CallStatus $status;

    // HANDLERS
    private array $onSuccess = [];
    private array $onFailure = [];

    public function status(): CallStatus {
        return $this->status;
    }

    public function isReady() : bool {
        return $this->status === CallStatus::Ready;
    }

    public function isRunning() : bool {
        return $this->status === CallStatus::InProgress;
    }

    public function isFailed() : bool {
        return $this->status === CallStatus::Failed;
    }

    public function isCompleted() : bool {
        return $this->status === CallStatus::Completed;
    }

    public function changeStatus(CallStatus $status): void {
        $this->status = $status;
        $this->updatedAt = new DateTime();
        if ($status === CallStatus::Completed) {
            foreach ($this->onSuccess as $callback) {
                $callback($this);
            }
        }
        if ($status === CallStatus::Failed) {
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