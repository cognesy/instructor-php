<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;

/**
 * Maps curl error codes to domain-specific HTTP exceptions
 *
 * Centralizes the logic for translating curl errno values into
 * appropriate exception types. Shared between CurlDriver and
 * CurlPool to ensure consistent error semantics.
 */
final class CurlErrorMapper
{
    /**
     * Map a curl error to an appropriate HTTP exception
     *
     * @param int $errorCode The curl error code from curl_errno()
     * @param string $errorMessage The curl error message from curl_error()
     * @param HttpRequest $request The original request that failed
     * @return HttpRequestException The appropriate domain exception
     */
    public function mapError(
        int $errorCode,
        string $errorMessage,
        HttpRequest $request,
    ): HttpRequestException {
        return match (true) {
            $this->isTimeout($errorCode)
                => new TimeoutException($errorMessage, $request, null),
            $this->isConnectionError($errorCode)
                => new ConnectionException($errorMessage, $request, null),
            default
                => new NetworkException($errorMessage, $request, null, null),
        };
    }

    /**
     * Check if error code represents a timeout
     */
    private function isTimeout(int $errorCode): bool {
        return in_array($errorCode, [
            CURLE_OPERATION_TIMEDOUT,
            CURLE_OPERATION_TIMEOUTED,
        ], strict: true);
    }

    /**
     * Check if error code represents a connection error
     */
    private function isConnectionError(int $errorCode): bool {
        return in_array($errorCode, [
            CURLE_COULDNT_CONNECT,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_RESOLVE_PROXY,
            CURLE_SSL_CONNECT_ERROR,
        ], strict: true);
    }
}
