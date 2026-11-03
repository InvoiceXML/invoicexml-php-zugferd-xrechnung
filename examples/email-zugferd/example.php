<?php

declare(strict_types=1);

// Creates a ZUGFeRD invoice and emails it to your customer in one call, sent from your own address.
//
// Before running it:
// - Add and verify your sending domain on the Verified email domains page of your dashboard.
//   Until it is verified, the API refuses the call and nothing is sent.
// - The client already retries when the API answers 429, because nothing was sent then.
//   A timeout or a lost connection may still mean the email went out, so check before
//   sending the same invoice again.

use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;

require __DIR__ . '/../../vendor/autoload.php';

// Replace these values with your own.
const API_KEY = 'YOUR_API_KEY';
const FROM = 'invoices@yourcompany.example'; // must be on your verified domain
const TO = 'accounting@customer.example';

const EMAIL_DOMAIN_NOT_VERIFIED = 4022;

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
    // The invoice is validated exactly like on createZugferd() before anything is sent.
    $sent = $client->emailZugferd($invoice, $email, ['language' => 'de']);

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
