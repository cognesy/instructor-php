<?php

namespace Cognesy\Instructor\Experimental\Module\Modules\Code;

use Cognesy\Instructor\Experimental\Module\Core\Module;
use Cognesy\Instructor\Experimental\Module\Modules\Code\Enums\Language;

class GuessProgrammingLanguageFromExt extends Module
{
    public function for(string $filePath) : string {
        return ($this)(filePath: $filePath)->get('language');
    }

    protected function forward(...$callArgs): array {
        $filePath = $callArgs['filePath'];
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return [
            'language' => Language::fromExtension($fileExtension)
        ];
    }
}