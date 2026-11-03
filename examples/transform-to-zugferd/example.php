<?php

declare(strict_types=1);

// Turns an ordinary PDF invoice, for example one from an older billing system or a supplier,
// into a ZUGFeRD invoice. AI reads the PDF, so a person must check the result before it is used.
// The rendered PDF ends with a section of technical properties to make that check easy.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;
use InvoiceXml\ZugferdXrechnung\File;
use InvoiceXml\ZugferdXrechnung\TooManyRequestsException;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const PDF_PATH = 'invoices/RE-2026-001.pdf'; // an ordinary PDF invoice
const OUTPUT_PATH = 'RE-2026-001-zugferd.pdf';

const TOTALS_MISMATCH = 4024;

// Read the PDF first, so a wrong path stops the example before any API call.
if (!is_file(PDF_PATH) || !is_readable(PDF_PATH)) {
    echo 'Could not read ' . PDF_PATH . ".\n";
    echo "Set PDF_PATH at the top of example.php to a PDF invoice on your machine.\n";
    exit(1);
}
$pdf = File::fromPath(PDF_PATH);

$client = new Client(API_KEY);

try {
    $zugferd = $client->transformToZugferd(
        $pdf,
        // language: 'de',                    // optional: left empty, the language the invoice is written in is used
        // preserveOriginalPdf: true,         // optional: keep your PDF as the visual layer instead of a fresh layout
        // includeAdvancedProperties: false,  // optional: leave out the technical properties section
    );

    $zugferd->save(OUTPUT_PATH);
    echo 'Saved the ZUGFeRD invoice to ' . OUTPUT_PATH . ". Check it before you send or book it.\n";
} catch (TooManyRequestsException $e) {
    // Transform calls are not retried automatically, because a retry repeats the AI read.
    echo "Too many requests. Wait {$e->retryAfter} seconds before trying again.\n";
    exit(1);
} catch (ApiException $e) {
    if ($e->errorCode === TOTALS_MISMATCH) {
        // The amounts read from the PDF do not add up to its printed total, so no invoice was created.
        echo "The lines, allowances, charges and VAT read from the PDF do not add up to its printed total.\n";
        echo "Check the PDF, or create the invoice from your own data with createZugferd() instead.\n";
    } elseif ($e->errors !== []) {
        // The data read from the PDF would not make a compliant ZUGFeRD invoice.
        echo "The invoice read from the PDF is not compliant:\n";
        foreach ($e->errors as $finding) {
            echo "  [{$finding->rule}] {$finding->message} (" . implode(', ', $finding->fields) . ")\n";
        }
    } else {
        // For example an invalid API key, or a file that is not a PDF invoice.
        echo "The API refused the request: {$e->getMessage()}\n";
    }
    exit(1);
}
