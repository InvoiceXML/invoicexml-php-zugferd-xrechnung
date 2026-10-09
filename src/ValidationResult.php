<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung;

/**
 * Result of a validation call. The API answers HTTP 200 for valid and invalid
 * documents alike, so check $valid.
 *
 * @phpstan-type ReportFinding array{rule: string, message: string}
 * @phpstan-type ReportRow array{code: string, name: string, section: string, path: string, value: ?string, isValid: bool, errors: list<ReportFinding>, warnings: list<ReportFinding>}
 */
final class ValidationResult
{
    /**
     * @param bool                 $valid    True when the document passed every rule.
     * @param string|null          $detail   One-sentence summary of the verdict.
     * @param array<string, mixed> $data     Verdict details, for example schemaValid, schematronValid, conformanceLevel and profile.
     * @param list<Finding>        $errors   Rule violations. Empty when valid.
     * @param list<Finding>        $warnings Advisory findings that do not fail the document.
     * @param list<ReportRow>      $report   Field report: one row per EN 16931 business term with its value and findings.
     * @param array<string, mixed> $body     The complete decoded response body.
     */
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $detail,
        public readonly array $data,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly array $report,
        public readonly array $body,
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function fromArray(array $body): self
    {
        return new self(
            (bool) ($body['valid'] ?? false),
            isset($body['detail']) ? (string) $body['detail'] : null,
            is_array($body['data'] ?? null) ? $body['data'] : [],
            Finding::listFrom($body['errors'] ?? []),
            Finding::listFrom($body['warnings'] ?? []),
            is_array($body['report'] ?? null) ? array_values($body['report']) : [],
            $body,
        );
    }
}
