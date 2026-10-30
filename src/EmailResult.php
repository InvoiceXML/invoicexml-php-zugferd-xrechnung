<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung;

/**
 * Result of an email call. The invoice file travels in the email, so no file comes
 * back. "accepted" means the email was handed over for delivery, not yet that the
 * recipient's mail server received it.
 */
final class EmailResult
{
    /**
     * @param string               $status    "accepted" once the email was handed over for delivery.
     * @param string               $messageId Identifies this email when you ask about a delivery later.
     * @param array<string, mixed> $body      The complete decoded response body.
     */
    public function __construct(
        public readonly string $status,
        public readonly string $messageId,
        public readonly array $body,
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function fromArray(array $body): self
    {
        return new self(
            (string) ($body['status'] ?? ''),
            (string) ($body['messageId'] ?? ''),
            $body,
        );
    }
}
