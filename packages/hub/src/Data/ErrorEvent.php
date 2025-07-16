<?php declare(strict_types=1);
namespace Cognesy\InstructorHub\Data;

class ErrorEvent {
    public function __construct(
        public string $file,
        public string $output,
    ) {}
}