<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung;

/**
 * Thrown when the API answers with an error status. The body is an RFC 7807 problem
 * document. When a create or embed request is refused because the invoice would not
 * be compliant, $errors lists the violated rules.
 */
class ApiException extends \RuntimeException
{
    /**
     * @param int|null             $errorCode Stable numeric code, see https://www.invoicexml.com/docs/error-handling
     * @param list<Finding>        $errors    Violated rules or invalid request fields.
     * @param list<Finding>        $warnings  Advisory findings.
     * @param array<string, mixed> $body      The complete decoded problem document.
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $title,
        public readonly ?string $detail,
        public readonly ?int $errorCode,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly array $body = [],
    ) {
        parent::__construct(self::buildMessage($statusCode, $title, $detail), $statusCode);
    }

    private static function buildMessage(int $statusCode, ?string $title, ?string $detail): string
    {
        $message = 'InvoiceXML API error ' . $statusCode;
        if ($title !== null && $title !== '') {
            $message .= ': ' . $title;
        }
        if ($detail !== null && $detail !== '' && $detail !== $title) {
            $message .= ' (' . $detail . ')';
        }

        return $message;
    }
}
