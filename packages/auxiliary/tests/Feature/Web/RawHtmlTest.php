<?php declare(strict_types=1);

use Cognesy\Auxiliary\Web\Html\RawHtml;

it('parses utf8 html without triggering deprecations', function () {
    $previous = set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if ($severity !== E_DEPRECATED) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    try {
        $text = RawHtml::fromContent('<html><body><p>Zażółć gęślą jaźń</p></body></html>')->asText();
    } finally {
        restore_error_handler();
    }

    expect($text)->toContain('Zażółć gęślą jaźń');
});
