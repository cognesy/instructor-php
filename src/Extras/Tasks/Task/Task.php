<?php
namespace Cognesy\Instructor\Extras\Tasks\Task;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Task\Enums\TaskStatus;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use DateTimeImmutable;

abstract class Task implements Contracts\CanProcessInput
{
    use Traits\HandlesTaskInfo;
    use Traits\HandlesTaskStatus;
    use Traits\HandlesTaskDataAccess;
    use Traits\HandlesSignature;
    use Traits\HandlesContext;

    public function __construct() {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTime();
        $this->status = TaskStatus::Created;
        $this->context = [];
    }

    abstract public function signature() : string|Signature;
}
