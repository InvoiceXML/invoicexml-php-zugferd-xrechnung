# ZUGFeRD and XRechnung for PHP

[![tests](https://github.com/InvoiceXML/invoicexml-php-zugferd-xrechnung/actions/workflows/tests.yml/badge.svg)](https://github.com/InvoiceXML/invoicexml-php-zugferd-xrechnung/actions/workflows/tests.yml)

A thin PHP client for the [InvoiceXML API](https://www.invoicexml.com) that creates, validates, embeds, renders, converts, emails and reads German e-invoices:

- **ZUGFeRD**: hybrid PDF/A-3 invoices with embedded EN 16931 CII XML, for B2B.
- **XRechnung**: XML invoices in UBL or CII syntax checked against the KoSIT rules, for the public sector.

You send an invoice as a PHP array, and the API computes totals and the VAT breakdown, builds the XML and the PDF/A-3, and validates the result before returning it. Your project needs no PDF library, no XML builder and no Schematron tooling.

## Installation

```bash
composer require invoicexml/zugferd-xrechnung
```

Requires PHP 8.1 or newer. Guzzle is used by default, and any PSR-18 HTTP client works.

## Quick start

```php
use InvoiceXml\ZugferdXrechnung\Client;

$client = new Client(getenv('INVOICEXML_API_KEY'));

$pdf = $client->createZugferd([
    'invoiceNumber'  => 'INV-2026-00045',
    'issueDate'      => '2026-05-17',
    'currency'       => 'EUR',
    'seller'         => ['name' => 'Acme Engineering GmbH', 'vatIdentifier' => 'DE123456789', 'postalAddress' => ['line1' => 'Hauptstraße 12', 'city' => 'Berlin', 'postCode' => '10115', 'country' => 'DE']],
    'buyer'          => ['name' => 'Globex SAS', 'postalAddress' => ['line1' => '15 rue de Rivoli', 'city' => 'Paris', 'postCode' => '75001', 'country' => 'FR']],
    'paymentDetails' => ['paymentAccountIdentifier' => 'DE89370400440532013000'],
    'lines'          => [['quantity' => 10, 'priceDetails' => ['netPrice' => 150], 'vatInformation' => ['rate' => 19], 'item' => ['name' => 'Senior consulting']]],
]);

$pdf->save('invoice.pdf');
```

The result is a ZUGFeRD PDF/A-3 in the EN 16931 profile, already validated against the official rules.

## Why InvoiceXML

- **Rule sets updated on their legal effective dates.** New KoSIT and FeRD releases go live server-side on the day they take effect. You never update a package or redeploy to stay compliant.
- **Validated before delivery.** Every created or embedded invoice passes the full rule set before it is returned. Data that cannot produce a compliant invoice is refused with the violated rules, so nothing broken reaches a customer.
- **Zero data retention.** Documents are processed in memory and discarded when the response is returned. Your data is never used for AI training. When you email an invoice, it goes to our email service for delivery, and we keep no copy.
- **Processing in Frankfurt.** All processing runs in Frankfurt, Germany.
- **Integration assistance.** When a rule finding needs a human explanation, a person who knows the rule sets answers your support request.

## Get an API key

Sign up at [www.invoicexml.com](https://www.invoicexml.com/account/signup) to start a 30-day free trial with 100 credits. No credit card is needed. Plans are listed on the [pricing page](https://www.invoicexml.com/pricing).

## Usage

Invoices use the field names of the EN 16931 `InvoiceDocument` model from the [OpenAPI specification](https://api.invoicexml.com/v1/openapi). The [API explorer](https://api.invoicexml.com/v1/scalar/) documents every field. Every method that takes a file accepts a path on disk or a `File` object.

### Create a ZUGFeRD PDF

```php
$pdf = $client->createZugferd($invoice, [
    'language'   => 'de',          // labels of the rendered PDF: en, de or fr
    'brandColor' => '#0F766E',
    'logoUrl'    => 'https://example.com/logo.png',
]);

$pdf->save($pdf->filename);       // for example invoice-zugferd-INV-2026-00045.pdf
```

To keep your own invoice layout, pass `'pdfUrl' => 'https://...'` and the API embeds the XML into that PDF instead of rendering one.

### Create an XRechnung

XRechnung needs the Leitweg-ID in `buyerReference`, a seller contact and electronic addresses for both parties.

```php
$xml = $client->createXrechnung([
    'invoiceNumber'  => 'RE-2026-3311',
    'issueDate'      => '2026-07-28',
    'dueDate'        => '2026-08-27',
    'currency'       => 'EUR',
    'buyerReference' => '08111-00234-45',
    'seller' => [
        'name'              => 'Neckarblick IT GmbH',
        'vatIdentifier'     => 'DE315498762',
        'postalAddress'     => ['line1' => 'Königstraße 82', 'city' => 'Stuttgart', 'postCode' => '70173', 'country' => 'DE'],
        'contact'           => ['name' => 'Miriam Vogel', 'phone' => '+49 711 2203980', 'email' => 'rechnung@neckarblick-it.de'],
        'electronicAddress' => ['identifier' => 'DE315498762', 'schemeId' => '0204'],
    ],
    'buyer' => [
        'name'              => 'Landeshauptstadt Stuttgart, Schulverwaltungsamt',
        'postalAddress'     => ['line1' => 'Hauptstätter Straße 79', 'city' => 'Stuttgart', 'postCode' => '70178', 'country' => 'DE'],
        'electronicAddress' => ['identifier' => '08111-00234-45', 'schemeId' => '0204'],
    ],
    'paymentDetails' => ['paymentMeansCode' => 58, 'paymentAccountIdentifier' => 'DE75512108001245126199'],
    'lines' => [[
        'quantity'       => 210,
        'unitCode'       => 'HUR',
        'item'           => ['name' => 'Betreuung Schul-IT, Juli 2026'],
        'priceDetails'   => ['netPrice' => 92.00],
        'vatInformation' => ['rate' => 19, 'categoryCode' => 'S'],
    ]],
]);

$xml->save('xrechnung.xml');
```

The output uses UBL syntax by default. Pass `['syntax' => 'cii']` as the second argument for the CII syntax. Both pass the same German rules.

### Validate

The API answers valid and invalid documents alike with a result, so check `valid`.

```php
$result = $client->validateZugferd('incoming.pdf');      // or validateXrechnung('incoming.xml')

if (!$result->valid) {
    foreach ($result->errors as $error) {
        echo "{$error->rule} ({$error->layer}): {$error->message}\n";
    }
}
```

Each finding carries the following fields:

- `rule` is the rule identifier, for example `BR-DE-15`.
- `layer` is `xsd`, `en16931` or `cius`. A `cius` finding means the invoice is valid EN 16931 but misses a German requirement.
- `message` is a plain-language explanation.
- `fields` names the invoice fields involved, for example `seller.name`.
- `line` and `raw` give the XML line and the verbatim validator output.

`$result->data` holds the verdict details, such as `schemaValid`, `schematronValid` and `profile`. `$result->report` is the field report: one row per invoice field with its value and its findings.

### Get a validation report as PDF

```php
$report = $client->validateZugferdReport('incoming.pdf', validationLanguage: 'de');   // or validateXrechnungReport('incoming.xml')

$report->file->save('report.pdf');

if ($report->valid === false) {
    echo "The invoice failed validation, see the report.\n";
}
```

`$report->file` is the printable report. `$report->valid` is `true` or `false`, or `null` if the response carried no verdict. A failed verdict is a normal result, not an exception. A missing or malformed file still throws `ApiException`. `validationLanguage` (`en`, `de` or `fr`) words the whole report.

The reference pages are [ZUGFeRD report](https://www.invoicexml.com/docs/api/validate/zugferd/report) and [XRechnung report](https://www.invoicexml.com/docs/api/validate/xrechnung/report).

### Embed XML into an existing PDF

```php
$zugferd = $client->embedZugferd('layout.pdf', 'factur-x.xml');
```

The XML is validated before embedding. Pass `skipValidation: true` to embed it as is.

### Create a CII XML for your own PDF

To keep your own invoice layout, create the CII XML from the invoice, then embed it into your PDF:

```php
$xml     = $client->createCii($invoice, ['language' => 'de']);
$zugferd = $client->embedZugferd('layout.pdf', $xml);
```

The XML declares the EN 16931 profile, so `embedZugferd()` takes it as is. `language` (`en`, `de` or `fr`) words default texts such as payment terms. The reference page is [create CII](https://www.invoicexml.com/docs/api/create/cii).

### Create a UBL invoice

`createUbl()` takes the same invoice array, and `profile` picks the UBL profile and its rules (`peppol-bis-3` when omitted). The reference page is [create UBL](https://www.invoicexml.com/docs/api/create/ubl).

```php
$ubl = $client->createUbl($invoice, ['profile' => 'peppol-bis-3']);
```

### Render an invoice XML as PDF

```php
$preview = $client->renderXrechnungToPdf('xrechnung.xml', language: 'de');
$preview = $client->renderCiiToPdf('invoice.xml', logoUrl: 'https://example.com/logo.png', includeQrCode: true);
```

`language` is `en`, `de` or `fr`. `logoUrl` prints your logo and `includeQrCode: true` prints the payment QR code. Rendering shows what the XML carries and does not validate it.

The reference pages are [render XRechnung](https://www.invoicexml.com/docs/api/render/xrechnung/to/pdf) and [render CII](https://www.invoicexml.com/docs/api/render/cii/to/pdf).

### Convert between formats

```php
$ubl = $client->convertZugferdToUbl('incoming.pdf');
$pdf = $client->convertCiiToZugferd('invoice.xml', language: 'de');
```

Each route has its own method:

- ZUGFeRD PDF to XML: [`convertZugferdToUbl()`](https://www.invoicexml.com/docs/api/convert/zugferd/to/ubl), [`convertZugferdToCii()`](https://www.invoicexml.com/docs/api/convert/zugferd/to/cii)
- XRechnung to plain EN 16931 XML: [`convertXrechnungToUbl()`](https://www.invoicexml.com/docs/api/convert/xrechnung/to/ubl), [`convertXrechnungToCii()`](https://www.invoicexml.com/docs/api/convert/xrechnung/to/cii)
- XML to XRechnung: [`convertUblToXrechnung()`](https://www.invoicexml.com/docs/api/convert/ubl/to/xrechnung), [`convertCiiToXrechnung()`](https://www.invoicexml.com/docs/api/convert/cii/to/xrechnung)
- XML to ZUGFeRD PDF: [`convertCiiToZugferd()`](https://www.invoicexml.com/docs/api/convert/cii/to/zugferd), [`convertUblToZugferd()`](https://www.invoicexml.com/docs/api/convert/ubl/to/zugferd), [`convertXrechnungToZugferd()`](https://www.invoicexml.com/docs/api/convert/xrechnung/to/zugferd)

The methods that return a ZUGFeRD PDF take `language` (`en`, `de` or `fr`) for the labels of the PDF. Every method takes `validationLanguage` (`en`, `de` or `fr`), which words the findings of a failed conversion. A failed conversion throws `ApiException`, and `$e->errors` lists the violated rules.

### Send an invoice by email

The API creates and validates the invoice like the create methods, then sends the file as a real email from your own address. The sender must be on a domain added and verified on the Verified email domains page of the dashboard.

```php
$result = $client->emailZugferd($invoice, [
    'from'     => 'billing@acme.example',    // required
    'to'       => 'accounts@globex.example', // required, one address
    'fromName' => 'Acme Billing',
    'cc'       => ['anna@globex.example'],   // cc and bcc hold at most 2 addresses together
    'bcc'      => ['archive@acme.example'],
    'replyTo'  => 'support@acme.example',
    'subject'  => 'Your invoice INV-2026-00045',
    'message'  => "Hello Anna,\n\nyour invoice is attached.",
], ['language' => 'de']);

echo $result->status, ' ', $result->messageId;
```

`emailXrechnung($invoice, $email, $options)` sends an XRechnung the same way. To send a PDF and XML you already have, use `emailEmbeddedZugferd()`, which always validates the XML:

```php
$result = $client->emailEmbeddedZugferd('layout.pdf', 'factur-x.xml', [
    'from' => 'billing@acme.example',
    'to'   => 'accounts@globex.example',
]);
```

No file comes back. `accepted` means the email was handed over for delivery, not yet that the recipient's mail server received it. Keep `messageId`: it identifies the email when you ask about a delivery later.

A refused email throws `ApiException` with one of these `errorCode` values, and 4020, 4021 and 4022 are not billed:

- `4020`: your plan does not include email delivery, see the [pricing page](https://www.invoicexml.com/pricing).
- `4021`: email sending is disabled or paused for your account.
- `4022`: the sender domain has not finished verification.
- `4002`: a field was refused, for example a bad address, a sender domain that is not on your account, or too many copies. `$e->errors` names the fields.

Only a 429 is retried, because nothing was sent. A timeout or connection failure throws the HTTP client's own exception rather than `ApiException`, and the email may already have gone out, so check before you send it again.

The full guide is at [Send an invoice by email](https://www.invoicexml.com/docs/api/create/zugferd/email).

### Read incoming invoices

```php
$data = $client->extractJson('incoming.pdf');             // ZUGFeRD PDF or XRechnung XML
$invoice = $data['invoice'];                              // same shape the create methods accept

$zip = $client->extractAttachments('incoming.pdf');       // supporting documents as a ZIP
```

Extraction parses the embedded XML deterministically, with no AI involved. The returned invoice can go straight back into `createZugferd()` or `createXrechnung()`.

### Transform a PDF invoice

These methods take a PDF without embedded XML. AI reads the PDF, so a person must check the result before it is used.

```php
$zugferd = $client->transformToZugferd('invoice.pdf', language: 'de');
$xml     = $client->transformToXrechnung('invoice.pdf', buyerReference: '08111-00234-45');
```

`transformToZugferd()` takes `language` (`en`, `de`, `fr` or `auto`, the default, which follows the language of the invoice). By default the PDF is rendered from the data that was read and ends with an electronic invoice details section, so that data can be checked on the pages. Pass `includeAdvancedProperties: false` to leave the section out, or `preserveOriginalPdf: true` to keep the uploaded PDF as the visual layer. `transformToXrechnung()` requires the Leitweg-ID in `buyerReference`. Both take `validationLanguage` (`en`, `de` or `fr`), which words the findings of a refused invoice. A 429 on these methods is not retried automatically, so wait for `Retry-After` before calling again if you want to retry.

The reference pages are [transform to ZUGFeRD](https://www.invoicexml.com/docs/api/transform/to/zugferd) and [transform to XRechnung](https://www.invoicexml.com/docs/api/transform/to/xrechnung).

## Errors and rate limits

Any error status throws `InvoiceXml\ZugferdXrechnung\ApiException`. When a create or embed request is refused because the invoice would not be compliant, `$e->errors` lists the violated rules as findings.

```php
use InvoiceXml\ZugferdXrechnung\ApiException;

try {
    $client->createXrechnung($invoice);
} catch (ApiException $e) {
    echo $e->statusCode, ' ', $e->errorCode, ': ', $e->detail, "\n";
    foreach ($e->errors as $error) {
        echo "{$error->rule}: {$error->message}\n";
    }
}
```

`errorCode` is a stable numeric code, documented under [error handling](https://www.invoicexml.com/docs/error-handling).

When too many requests for one API key run at the same time, the API answers 429 with a `Retry-After` header. Every call except transform waits that long and resends the request, up to 3 times. A 429 is not billed. If the retries are used up, a `TooManyRequestsException` carries `retryAfter` in seconds. Set `maxRetries: 0` to handle 429 yourself.

## Configuration

```php
$client = new Client(
    apiKey: getenv('INVOICEXML_API_KEY'),
    httpClient: $psr18Client,          // any PSR-18 client, Guzzle by default
    requestFactory: $psr17Factory,     // PSR-17 factories, Guzzle's by default
    streamFactory: $psr17Factory,
    maxRetries: 3,
);
```

## Links

- [Documentation](https://www.invoicexml.com/docs)
- [API explorer](https://api.invoicexml.com/v1/scalar/)
- [ZUGFeRD and XRechnung in PHP guide](https://www.invoicexml.com/blog/zugferd-xrechnung-php)
- [Factur-X package for PHP](https://github.com/InvoiceXML/invoicexml-php-facturx)
- [Contact and support](https://www.invoicexml.com/contact)

## License

MIT
