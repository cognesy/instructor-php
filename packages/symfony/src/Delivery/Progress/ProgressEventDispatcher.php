<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Progress;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates;

final class ProgressEventDispatcher extends EventDispatcher implements CanHandleProgressUpdates
{
    public function __construct()
    {
        parent::__construct('instructor.symfony.progress');
    }
}
