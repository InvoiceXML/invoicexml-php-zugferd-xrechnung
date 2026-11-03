<?php

declare(strict_types=1);

// Emails a PDF you already have as a ZUGFeRD invoice, sent from your own address.
// The CII XML is built from your invoice data, then embedded into your PDF and emailed in
// one call. Use this when your own system prints the invoice PDF.
//
// Before running it:
// - Add and verify your sending domain on the Verified email domains page of your dashboard.
//   Until it is verified, the API refuses the call and nothing is sent.
// - The client already retries when the API answers 429, because nothing was sent then.
//   A timeout or a lost connection may still mean the email went out, so check before
//   sending the same invoice again.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;
use InvoiceXml\ZugferdXrechnung\File;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const PDF_PATH = 'invoices/RE-2026-001.pdf'; // the PDF your system printed
const FROM = 'invoices@yourcompany.example'; // must be on your verified domain
const TO = 'accounting@customer.example';

const EMAIL_DOMAIN_NOT_VERIFIED = 4022;

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

$email = [
    'from' => FROM,
    'fromName' => 'Muster GmbH Billing',
    'to' => TO,
    'subject' => 'Rechnung RE-2026-001',
    'message' => 'Anbei erhalten Sie Ihre Rechnung.',
];

try {
    // 1. Build the CII XML from the invoice data. language words the default texts, such as the payment terms.
    $cii = $client->createCii($invoice, ['language' => 'de']);

    // 2. Embed the XML into your PDF and email the ZUGFeRD invoice. Both are validated before anything is sent.
    $sent = $client->emailEmbeddedZugferd($pdf, $cii, $email);

    // "accepted" means the email was handed over for delivery.
    echo "{$sent->status}: message id {$sent->messageId}\n";
} catch (ApiException $e) {
    if ($e->errorCode === EMAIL_DOMAIN_NOT_VERIFIED) {
        echo 'The domain of ' . FROM . " is not verified yet. Verify it in your dashboard, then run this again.\n";
    } elseif ($e->errors !== []) {
        // The invoice or an email field is invalid. Nothing was sent.
        echo "Nothing was sent. Fix these fields first:\n";
        foreach ($e->errors as $finding) {
            echo "  [{$finding->rule}] {$finding->message} (" . implode(', ', $finding->fields) . ")\n";
        }
    } else {
        echo "The API refused the request: {$e->getMessage()}\n";
    }
    exit(1);
}
