<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung;

/**
 * One validation finding, as returned in the "errors" and "warnings" arrays of a
 * validation result and of a rejected create or embed request.
 */
final class Finding
{
    /**
     * @param string|null  $rule    Rule identifier, for example "BR-DE-15" or "BR-CO-15". Null for request field errors.
     * @param string|null  $layer   Rule layer that produced the finding: "xsd", "en16931" or "cius".
     * @param int|null     $line    Line in the XML document, when known.
     * @param string       $message Plain-language explanation of the finding.
     * @param list<string> $btCodes EN 16931 business terms involved, for example ["BT-10"].
     * @param list<string> $fields  JSON paths of the affected invoice fields, for example ["buyerReference"].
     * @param string|null  $raw     The verbatim validator output.
     */
    public function __construct(
        public readonly ?string $rule,
        public readonly ?string $layer,
        public readonly ?int $line,
        public readonly string $message,
        public readonly array $btCodes = [],
        public readonly array $fields = [],
        public readonly ?string $raw = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['rule']) ? (string) $data['rule'] : null,
            isset($data['layer']) ? (string) $data['layer'] : null,
            isset($data['line']) ? (int) $data['line'] : null,
            (string) ($data['message'] ?? ''),
            array_values(array_map('strval', (array) ($data['btCodes'] ?? []))),
            array_values(array_map('strval', (array) ($data['fields'] ?? []))),
            isset($data['raw']) ? (string) $data['raw'] : null,
        );
    }

    /** @return list<self> */
    public static function listFrom(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): self => self::fromArray(is_array($item) ? $item : ['message' => (string) $item]),
            $items,
        ));
    }
}
