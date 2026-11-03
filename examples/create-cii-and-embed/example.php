<?php

declare(strict_types=1);

// Builds a CII XML from your invoice data, then embeds it into a PDF you already have.
// Use this when your own system prints the invoice PDF and you want it to become a
// ZUGFeRD invoice. The CII XML declares the EN 16931 profile, so it embeds as it is.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;
use InvoiceXml\ZugferdXrechnung\File;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const PDF_PATH = 'invoices/RE-2026-001.pdf'; // the PDF your system printed
const OUTPUT_PATH = 'RE-2026-001-zugferd.pdf';

// Read the PDF first, so a wrong path stops the example before any API call.
if (!is_file(PDF_PATH) || !is_readable(PDF_PATH)) {
    echo 'Could not read ' . PDF_PATH . ".\n";
    echo "Set PDF_PATH at the top of example.php to a PDF on your machine.\n";
    exit(1);
}
$pdf = File::fromPath(PDF_PATH);

$client = new Client(API_KEY);

$invoice = [
    'invoiceNumber' => 'RE-2026-001',
    'currency' => 'EUR',
    'seller' => [
        'name' => 'Muster GmbH',
        'vatIdentifier' => 'DE123456789',
        'postalAddress' => ['city' => 'Berlin', 'postCode' => '10115', 'country' => 'DE'],
    ],
    'buyer' => [
        'name' => 'Kunde AG',
        'postalAddress' => ['city' => 'Hamburg', 'postCode' => '20095', 'country' => 'DE'],
    ],
    'lines' => [
        [
            'quantity' => 10,
            'unitCode' => 'HUR', // hour
            'item' => ['name' => 'Consulting'],
            'priceDetails' => ['netPrice' => 95.50],
            'vatInformation' => ['categoryCode' => 'S', 'rate' => 19],
        ],
    ],
];

try {
    // 1. Build the CII XML from the invoice data. language words the default texts, such as the payment terms.
    $cii = $client->createCii($invoice, ['language' => 'de']);

    // 2. Embed the XML into your PDF. The result is a ZUGFeRD PDF/A-3.
    $zugferd = $client->embedZugferd($pdf, $cii);

    $zugferd->save(OUTPUT_PATH);
    echo 'Saved the ZUGFeRD invoice to ' . OUTPUT_PATH . ".\n";
} catch (ApiException $e) {
    if ($e->errors !== []) {
        // The invoice data would not produce a compliant invoice. Each finding names the field to fix.
        echo "The invoice is not compliant:\n";
        foreach ($e->errors as $finding) {
            echo "  [{$finding->rule}] {$finding->message} (" . implode(', ', $finding->fields) . ")\n";
        }
    } else {
        echo "The API refused the request: {$e->getMessage()}\n";
    }
    exit(1);
}
