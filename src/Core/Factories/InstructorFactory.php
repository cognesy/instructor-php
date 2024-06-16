<?php
namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\Data\InstructorInfo;
use Cognesy\Instructor\Instructor;

class InstructorFactory
{
    private InstructorInfo $data;

    public function __construct(InstructorInfo $data) {
        $this->data = $data;
    }

    public static function makeWith(InstructorInfo $data) : Instructor {
        return (new static($data))->make();
    }

    public function make() : Instructor {
        $instructor = new Instructor(
            events: $this->data->events,
            config: $this->data->config,
        );
        $instructor->withClient($this->data->client);
        $instructor->withDebug($this->data->debug ?? false, $this->data->stopOnDebug ?? false);
        $instructor->withCache($this->data->cache ?? false);
        return $instructor;
    }
}
