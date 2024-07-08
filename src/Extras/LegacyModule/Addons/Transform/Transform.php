<?php
namespace Cognesy\Instructor\Extras\Module\Addons\Transform;

use Cognesy\Instructor\Extras\Module\Addons\InstructorModule\InstructorModule;
use Cognesy\Instructor\Extras\Structure\Structure;

class Transform extends InstructorModule {
    public function forward(mixed ...$args) : mixed {
        return $this->toResult(
            $this->useInstructor(...$args)
        );
    }

    public function toResult(mixed $response) : mixed {
        if ($response instanceof Structure) {
            return $response->toArray();
        }
        return $response;
    }
}
