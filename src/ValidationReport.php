<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung;

/**
 * Result of a validation report call: the printable PDF report and the verdict. The API
 * answers with a report for valid and invalid documents alike, so check $valid.
 */
final class ValidationReport
{
    /**
     * @param File      $file  The PDF report.
     * @param bool|null $valid True when the document passed every rule, false when it did not, null when the response carried no verdict.
     */
    public function __construct(
        public readonly File $file,
        public readonly ?bool $valid,
    ) {
    }
}
