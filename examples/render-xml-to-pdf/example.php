<?php

declare(strict_types=1);

// Turns any invoice XML into a readable PDF, for approval workflows and archives.
// XRechnung, CII (such as the XML inside a ZUGFeRD invoice) and UBL are all accepted;
// the format is detected from the XML.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;
use InvoiceXml\ZugferdXrechnung\File;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const XML_PATH = 'invoices/RE-2026-001.xml';
const OUTPUT_PATH = 'RE-2026-001-preview.pdf';

// Read the XML first, so a wrong path stops the example before any API call.
if (!is_file(XML_PATH) || !is_readable(XML_PATH)) {
    echo 'Could not read ' . XML_PATH . ".\n";
    echo "Set XML_PATH at the top of example.php to an invoice XML on your machine.\n";
    exit(1);
}
$xml = File::fromPath(XML_PATH);

$client = new Client(API_KEY);

try {
    $pdf = $client->renderXmlToPdf(
        $xml,
        language: 'de',                             // labels and date formats of the PDF
        includeQrCode: true,                        // optional: a payment QR code for banking apps
        // logoUrl: 'https://example.com/logo.png', // optional: public URL of your logo, printed top left
    );

    $pdf->save(OUTPUT_PATH);
    echo 'Saved the PDF to ' . OUTPUT_PATH . ".\n";
} catch (ApiException $e) {
    // For example an invalid API key, or a file that is not an invoice XML.
    echo "The API refused the request: {$e->getMessage()}\n";
    exit(1);
}
