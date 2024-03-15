<?php
namespace Cognesy\InstructorHub\Data;

class ErrorEvent {
    public function __construct(
        public string $file,
        public string $output,
    ) {}
}