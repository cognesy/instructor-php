<?php
namespace Cognesy\Instructor\Extras\Module\Core\Traits\Predictor;

use Cognesy\Instructor\Instructor;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
trait HandlesInstructor {
    public function withInstructor(Instructor $instructor) : static {
        $this->instructor = $instructor;
        return $this;
    }
}
