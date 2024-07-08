<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Code;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Code\Enums\Language;

class GuessLanguageFromExt extends Module
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