<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung;

/**
 * Thrown on HTTP 429 once the client's automatic retries are used up. A request
 * answered with 429 was not processed and not billed, so it is always safe to resend.
 */
final class TooManyRequestsException extends ApiException
{
    /**
     * @param int                  $retryAfter Seconds to wait, from the Retry-After header.
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly int $retryAfter,
        ?string $title,
        ?string $detail,
        ?int $errorCode,
        array $body = [],
    ) {
        parent::__construct(429, $title, $detail, $errorCode, [], [], $body);
    }
}
