<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Traits;

use Cognesy\Messages\Messages;

trait HandlesStepMessages
{
    protected readonly ?Messages $inputMessages;
    protected readonly ?Messages $outputMessages;

    public function inputMessages(): Messages {
        return $this->inputMessages ?? Messages::empty();
    }

    public function outputMessages(): Messages {
        return $this->outputMessages ?? Messages::empty();
    }

//    public function messages(): Messages {
//        $messages = $this->inputMessages ?? Messages::empty();
//        if ($this->outputMessage) {
//            $messages = $messages->appendMessage($this->outputMessage);
//        }
//        return $messages;
//    }
}