<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

trait HandlesTransformation
{
    /**
     * @return array<string, string|array>
     */
    public function toArray() : array {
        $result = [];
        foreach ($this->messages as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            $result[] = $message->toArray();
        }
        return $result;
    }

    public function toString(string $separator = "\n") : string {
        $result = '';
        foreach ($this->messages as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            $result .= $message->toString() . $separator;
        }
        return $result;
    }
}
