<?php

namespace Cognesy\Instructor\Hub;

class ErrorEvent {
    public function __construct(
        public string $file,
        public string $output,
    ) {}
}