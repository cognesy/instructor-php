<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Step\Contracts;

use Cognesy\Messages\Messages;

interface HasStepMessages
{
    public function inputMessages(): Messages;
    public function outputMessages(): Messages;
}