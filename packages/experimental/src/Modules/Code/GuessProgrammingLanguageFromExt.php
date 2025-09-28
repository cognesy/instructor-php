<?php

namespace Cognesy\Experimental\Modules\Code;

use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Modules\Code\Enums\Language;

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