<?php
namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\InstructorData;

class InstructorFactory
{
    private InstructorData $data;

    public function __construct(InstructorData $data) {
        $this->data = $data;
    }

    public static function makeWith(InstructorData $data) : Instructor {
        return (new static($data))->make();
    }

    public function make() : Instructor {
        $instructor = new Instructor(
            events: $this->data->events,
            config: $this->data->config,
        );
        $instructor->withClient($this->data->client);
        $instructor->withDebug($this->data->debug, $this->data->stopOnDebug);
        $instructor->withCache($this->data->cache);
        return $instructor;
    }
}
