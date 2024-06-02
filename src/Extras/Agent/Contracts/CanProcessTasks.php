<?php

namespace Cognesy\Instructor\Extras\Agent\Contracts;

use Cognesy\Instructor\Extras\Module\Call\Call;

interface CanProcessTasks
{
    public function process(Call $task) : Call;
}