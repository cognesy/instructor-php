<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\DecoratedPipeline\JsonMode;

use Cognesy\Utils\Json\Json;

final class PartialJson
{
    public function __construct(
        private string $raw,
        private string $normalized
    ) {}

    // CONSTRUCTORS ////////////////////////////////////////////////////////////////

    public static function initial(): self {
        return new self('', '');
    }

    // MUTATORS ////////////////////////////////////////////////////////////////////

    public function assemble(string $delta): self {
        if (trim($delta) === '') {
            return $this;
        }
        return $this->appendChunk($delta);
    }

    // ACCESSORS ///////////////////////////////////////////////////////////////////

    public function raw(): string {
        return $this->raw;
    }

    public function normalized(): string {
        return $this->normalized;
    }

    public function toArray(): array {
        return Json::fromPartial($this->normalized)->toArray();
    }

    public function isEmpty(): bool {
        return $this->normalized === '';
    }

    public function equals(PartialJson $partialJson) : bool {
        return $this->normalized === $partialJson->normalized;
    }

//    public function finalized(): string {
//        return Json::fromString($this->raw)->toString();
//    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    private function appendChunk(string $delta): self {
        $raw = $this->raw . $delta;
        $normalized = Json::fromPartial($raw)->toString();
        return new self($raw, $normalized);
    }
}

