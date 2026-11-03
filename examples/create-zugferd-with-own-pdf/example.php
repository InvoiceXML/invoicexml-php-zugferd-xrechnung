<?php

declare(strict_types=1);

// Creates a ZUGFeRD invoice that keeps your own PDF layout.
// The API downloads your PDF from a public URL, embeds the XML it generates from the invoice
// data into it, and returns a ZUGFeRD PDF/A-3. Nothing about your PDF's look changes.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const PDF_URL = 'https://example.com/invoices/RE-2026-001.pdf'; // public http(s) URL of your PDF
const OUTPUT_PATH = 'RE-2026-001-zugferd.pdf';

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
    // pdfUrl makes your PDF the visual layer. Without it, the API renders its own invoice layout.
    $zugferd = $client->createZugferd($invoice, ['pdfUrl' => PDF_URL]);

    $zugferd->save(OUTPUT_PATH);
    echo 'Saved the ZUGFeRD invoice to ' . OUTPUT_PATH . ".\n";
} catch (ApiException $e) {
    if ($e->errors !== []) {
        // The invoice data would not produce a compliant ZUGFeRD invoice. Each finding names the field to fix.
        echo "The invoice is not compliant:\n";
        foreach ($e->errors as $finding) {
            echo "  [{$finding->rule}] {$finding->message} (" . implode(', ', $finding->fields) . ")\n";
        }
    } else {
        // For example an invalid API key, or a PDF URL the API could not download.
        echo "The API refused the request: {$e->getMessage()}\n";
    }
    exit(1);
}
