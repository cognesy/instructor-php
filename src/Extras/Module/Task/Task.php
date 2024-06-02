<?php
namespace Cognesy\Instructor\Extras\Module\Task;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Task\Contracts\CanBeProcessed;
use Cognesy\Instructor\Extras\Module\Task\Contracts\HasErrorData;
use Cognesy\Instructor\Extras\Module\Task\Enums\TaskStatus;
use Cognesy\Instructor\Extras\Module\TaskData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use DateTimeImmutable;
use Exception;

// Tasks are used to track execution of modules

class Task implements CanBeProcessed, HasErrorData
{
    use Traits\HandlesContext;
    use Traits\HandlesErrors;
    use Traits\HandlesTaskDataAccess;
    use Traits\HandlesTaskInfo;
    use Traits\HandlesTaskStatus;

    protected HasInputOutputData $data;

    public function __construct(HasInputOutputData $data) {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTime();
        $this->status = TaskStatus::Created;
        $this->context = [];
        $this->data = $data;
    }

    public function data(): HasInputOutputData {
        if (!isset($this->data)) {
            throw new Exception('Task data is not set');
        }
        return $this->data;
    }

    public function signature() : Signature {
        return $this->data->signature();
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'createdAt' => $this->createdAt->format(DateTime::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTime::ATOM),
            'signature' => $this->signature()->toShortSignature(),
            'signatureDescription' => $this->signature()->getDescription(),
            'signatureDetails' => $this->signature()->toSignatureString(),
            'inputs' => Json::encode($this->inputs()),
            'outputs' => Json::encode($this->outputs()),
            'context' => Json::encode($this->context),
        ];
    }
}
