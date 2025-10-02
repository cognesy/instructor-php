<?php

namespace Cognesy\Experimental\Modules\Code;

use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Modules\Code\Enums\Language;

class GuessProgrammingLanguageFromExt extends Module
{
    public function for(string $filePath) : string {
        return ($this)(filePath: $filePath)->get('language');
    }

    #[\Override]
    protected function forward(...$callArgs): array {
        $filePath = $callArgs['filePath'];
        $language = $this->getLanguage($filePath);
        return [
            'language' => $language
        ];
    }

    public function getLanguage(string $filePath): string {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return Language::fromExtension($fileExtension)->value;
    }
}