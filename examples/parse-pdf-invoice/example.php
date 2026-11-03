<?php

declare(strict_types=1);

// Reads an ordinary PDF invoice, typed, scanned or photographed, into invoice data.
// AI reads the PDF, so a person must check the data before it is used. The result says how sure
// the read was per area, which tells the person where to look first.
// To read a ZUGFeRD PDF or an XRechnung XML, use the extract-json-and-attachments example instead:
// it reads the embedded XML exactly, without AI.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;
use InvoiceXml\ZugferdXrechnung\File;
use InvoiceXml\ZugferdXrechnung\TooManyRequestsException;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const PDF_PATH = 'invoices/supplier-invoice.pdf'; // an ordinary PDF invoice
const CHECK_BELOW = 0.8; // areas scored below this are flagged for a closer check

// Read the PDF first, so a wrong path stops the example before any API call.
if (!is_file(PDF_PATH) || !is_readable(PDF_PATH)) {
    echo 'Could not read ' . PDF_PATH . ".\n";
    echo "Set PDF_PATH at the top of example.php to a PDF invoice on your machine.\n";
    exit(1);
}
$pdf = File::fromPath(PDF_PATH);

$client = new Client(API_KEY);

function flag(string $area, ?float $score): void
{
    $note = $score === null ? 'no score' : ($score < CHECK_BELOW ? 'check this closely' : 'ok');
    echo "  {$area}: " . ($score ?? '-') . " ({$note})\n";
}

try {
    $parsed = $client->parseJson($pdf);
    $invoice = $parsed['invoice'];

    echo 'Invoice ' . ($invoice['invoiceNumber'] ?? '?')
        . ' from ' . ($invoice['seller']['name'] ?? '?')
        . ' to ' . ($invoice['buyer']['name'] ?? '?') . "\n";
    echo 'Total ' . ($invoice['totals']['grandTotalAmount'] ?? '?') . ' ' . ($invoice['currency'] ?? '')
        . ', due ' . ($invoice['totals']['duePayableAmount'] ?? '?') . "\n";
    foreach ($invoice['lines'] ?? [] as $line) {
        echo '  ' . ($line['quantity'] ?? '?') . ' x ' . ($line['item']['name'] ?? '?')
            . ' at ' . ($line['priceDetails']['netPrice'] ?? '?') . "\n";
    }

    // The language the invoice is written in, as an ISO 639-1 code such as "de". Null when unknown.
    // Any language can come back, so it is not always a language the create methods take.
    echo 'Language: ' . ($parsed['language'] ?? 'unknown') . "\n";

    // Scores run from 0.0 (had to guess) to 1.0 (unambiguous). They can be missing.
    $confidence = $parsed['confidence'];
    if ($confidence !== null) {
        echo 'Confidence overall: ' . ($confidence['overall'] ?? '-') . "\n";
        flag('seller', $confidence['areas']['sellerIdentification'] ?? null);
        flag('buyer', $confidence['areas']['buyerIdentification'] ?? null);
        flag('VAT', $confidence['areas']['taxCalculation'] ?? null);
        flag('lines', $confidence['areas']['lineItems'] ?? null);
    }

    // The checked invoice can be passed straight to a create method, for example
    // $client->createZugferd($invoice) to turn it into a ZUGFeRD invoice.
} catch (TooManyRequestsException $e) {
    // Parse calls are not retried automatically, because a retry repeats the AI read.
    echo "Too many requests. Wait {$e->retryAfter} seconds before trying again.\n";
    exit(1);
} catch (ApiException $e) {
    // For example an invalid API key, or a file that is not a PDF invoice.
    echo "The API refused the request: {$e->getMessage()}\n";
    exit(1);
}
