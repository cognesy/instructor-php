<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Contracts;

use Cognesy\Messages\Messages;

interface HasStepMessages
{
    public function inputMessages(): Messages;
    public function outputMessages(): Messages;
}