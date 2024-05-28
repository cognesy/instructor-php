<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ClassSignature implements Signature
{
    protected string $signatureString;
    protected string $description;
    protected ResponseModel $inputs;
    protected ResponseModel $outputs;

    public function getDescription(): string {
        return $this->description;
    }
}