<?php
namespace Cognesy\Instructor\Extras\Module\Core;

use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Core\Enums\TaskStatus;
use Cognesy\Instructor\Utils\Json;
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

    abstract public function signature() : string|HasSignature;

    public function toArray() : array {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'createdAt' => $this->createdAt->format(DateTime::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTime::ATOM),
            'signature' => $this->getSignature()->toShortSignature(),
            'signatureDescription' => $this->getSignature()->description(),
            'signatureDetails' => $this->getSignature()->toSignatureString(),
            'inputs' => Json::encode($this->inputs()),
            'outputs' => Json::encode($this->outputs()),
            'context' => Json::encode($this->context),
        ];
    }
}
