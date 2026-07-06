<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl {
    final class CurlFactoryTransportOptionsHook
    {
        /** @var array<int,mixed> */
        public static array $options = [];

        public static bool $enabled = false;

        public static function reset(): void {
            self::$options = [];
            self::$enabled = false;
        }
    }

    function curl_setopt(\CurlHandle $handle, int $option, mixed $value): bool {
        if (CurlFactoryTransportOptionsHook::$enabled) {
            CurlFactoryTransportOptionsHook::$options[$option] = $value;
        }

        return \curl_setopt($handle, $option, $value);
    }
}

namespace {
    use Cognesy\Http\Config\HttpClientConfig;
    use Cognesy\Http\Data\HttpRequest;
    use Cognesy\Http\Drivers\Curl\CurlFactory;
    use Cognesy\Http\Drivers\Curl\CurlFactoryTransportOptionsHook;
    use PHPUnit\Framework\Assert;

    // Overrides curl functions in the Curl namespace + uses static hook state;
    // not safe under parallel scheduling — runs in the fast lane's serial pass.
    uses()->group('serial');

    it('applies shared transport options to curl handles', function () {
        if (!extension_loaded('curl')) {
            Assert::markTestSkipped('cURL extension not available');
        }

        CurlFactoryTransportOptionsHook::reset();
        CurlFactoryTransportOptionsHook::$enabled = true;

        try {
            $factory = new CurlFactory(new HttpClientConfig(
                verifyTls: false,
                followRedirects: false,
                maxRedirects: 2,
                httpVersion: '1.1',
            ));

            $handle = $factory->createHandle(new HttpRequest(
                url: 'https://example.test',
                method: 'GET',
                headers: [],
                body: '',
                options: [],
            ));
        } finally {
            CurlFactoryTransportOptionsHook::$enabled = false;
        }

        $handle->close();
        $options = CurlFactoryTransportOptionsHook::$options;

        expect($options[CURLOPT_SSL_VERIFYPEER])->toBeFalse()
            ->and($options[CURLOPT_SSL_VERIFYHOST])->toBe(0)
            ->and($options[CURLOPT_FOLLOWLOCATION])->toBeFalse()
            ->and($options[CURLOPT_MAXREDIRS])->toBe(2)
            ->and($options[CURLOPT_HTTP_VERSION])->toBe(CURL_HTTP_VERSION_1_1);
    });
}
