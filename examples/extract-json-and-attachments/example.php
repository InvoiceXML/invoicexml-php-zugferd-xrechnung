<?php

declare(strict_types=1);

// Reads a ZUGFeRD invoice you received: the invoice data as an array, and the supporting
// documents embedded in it (timesheets, delivery notes) as one ZIP archive.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;
use InvoiceXml\ZugferdXrechnung\File;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const ZUGFERD_PDF_PATH = 'invoices/incoming-zugferd.pdf'; // an XRechnung XML works too
const ATTACHMENTS_ZIP_PATH = 'incoming-zugferd-attachments.zip';

const NO_ATTACHMENTS = 4013;

// Read the file first, so a wrong path stops the example before any API call.
if (!is_file(ZUGFERD_PDF_PATH) || !is_readable(ZUGFERD_PDF_PATH)) {
    echo 'Could not read ' . ZUGFERD_PDF_PATH . ".\n";
    echo "Set ZUGFERD_PDF_PATH at the top of example.php to a ZUGFeRD PDF on your machine.\n";
    exit(1);
}
$file = File::fromPath(ZUGFERD_PDF_PATH);

$client = new Client(API_KEY);

try {
    // 1. The invoice data, read from the embedded XML. It can be passed straight back to the create methods.
    $invoice = $client->extractJson($file)['invoice'];

    echo 'Invoice ' . ($invoice['invoiceNumber'] ?? '?')
        . ' from ' . ($invoice['seller']['name'] ?? '?')
        . ' to ' . ($invoice['buyer']['name'] ?? '?') . "\n";
    echo 'Total ' . ($invoice['totals']['grandTotalAmount'] ?? '?') . ' ' . ($invoice['currency'] ?? '')
        . ', due ' . ($invoice['totals']['duePayableAmount'] ?? '?')
        . ', ' . count($invoice['lines'] ?? []) . " line(s)\n";
    foreach ($invoice['lines'] ?? [] as $line) {
        echo '  ' . ($line['quantity'] ?? '?') . ' x ' . ($line['item']['name'] ?? '?')
            . ' at ' . ($line['priceDetails']['netPrice'] ?? '?') . "\n";
    }

    // 2. The embedded supporting documents, as one ZIP archive.
    try {
        $attachments = $client->extractAttachments($file);
        $attachments->save(ATTACHMENTS_ZIP_PATH);
        echo 'Saved the attachments to ' . ATTACHMENTS_ZIP_PATH . ".\n";
    } catch (ApiException $e) {
        if ($e->errorCode !== NO_ATTACHMENTS) {
            throw $e;
        }
        echo "The invoice has no embedded attachments.\n";
    }
} catch (ApiException $e) {
    // For example an invalid API key, or a PDF without an embedded invoice.
    echo "The API refused the request: {$e->getMessage()}\n";
    exit(1);
}
