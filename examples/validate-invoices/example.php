<?php

declare(strict_types=1);

// Validates incoming invoices: a ZUGFeRD PDF and an XRechnung XML.
// A failed validation is a normal result, not an exception: you get the verdict and every
// finding with the rule, the message and the field it concerns. The example also saves the
// printable validation report PDF for the ZUGFeRD invoice.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;
use InvoiceXml\ZugferdXrechnung\File;
use InvoiceXml\ZugferdXrechnung\ValidationResult;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const ZUGFERD_PDF_PATH = 'invoices/incoming-zugferd.pdf';
const XRECHNUNG_XML_PATH = 'invoices/incoming-xrechnung.xml';
const REPORT_PATH = 'incoming-zugferd-report.pdf';

// Read both files first, so a wrong path stops the example before any API call.
foreach (['ZUGFERD_PDF_PATH' => ZUGFERD_PDF_PATH, 'XRECHNUNG_XML_PATH' => XRECHNUNG_XML_PATH] as $name => $path) {
    if (!is_file($path) || !is_readable($path)) {
        echo "Could not read {$path}.\n";
        echo "Set {$name} at the top of example.php to a file on your machine.\n";
        exit(1);
    }
}
$zugferdPdf = File::fromPath(ZUGFERD_PDF_PATH);
$xrechnungXml = File::fromPath(XRECHNUNG_XML_PATH);

$client = new Client(API_KEY);

function printResult(string $label, ValidationResult $result): void
{
    echo $label . ': ' . ($result->valid ? 'valid' : 'not valid') . ". {$result->detail}\n";

    foreach ($result->errors as $error) {
        echo "  error   [{$error->rule}] {$error->message} (" . implode(', ', $error->fields) . ")\n";
    }

    // Warnings never make an invoice invalid, but are worth reviewing.
    foreach ($result->warnings as $warning) {
        echo "  warning [{$warning->rule}] {$warning->message} (" . implode(', ', $warning->fields) . ")\n";
    }
}

try {
    // 1. Validate a ZUGFeRD PDF against the rules of the profile it declares.
    printResult('ZUGFeRD PDF', $client->validateZugferd($zugferdPdf));

    // 2. Validate an XRechnung XML, UBL or CII, against EN 16931 and the German XRechnung rules.
    printResult('XRechnung XML', $client->validateXrechnung($xrechnungXml));

    // 3. The same check as a printable PDF report, for example to send back to a supplier.
    $report = $client->validateZugferdReport($zugferdPdf, validationLanguage: 'de');
    $report->file->save(REPORT_PATH);
    echo 'Saved the validation report to ' . REPORT_PATH . ' (valid: ' . var_export($report->valid, true) . ").\n";
} catch (ApiException $e) {
    // For example an invalid API key, or a file that is not an invoice at all.
    echo "The API refused the request: {$e->getMessage()}\n";
    exit(1);
}
