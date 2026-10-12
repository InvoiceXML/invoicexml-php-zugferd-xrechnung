# ZUGFeRD and XRechnung for PHP

[![tests](https://github.com/InvoiceXML/invoicexml-php-zugferd-xrechnung/actions/workflows/tests.yml/badge.svg)](https://github.com/InvoiceXML/invoicexml-php-zugferd-xrechnung/actions/workflows/tests.yml)

A thin PHP client for the [InvoiceXML API](https://www.invoicexml.com) that creates, validates, embeds, renders and reads German e-invoices:

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
- **Zero data retention.** Documents are processed in memory and discarded when the response is returned. Your data is never used for AI training.
- **Processing in Frankfurt.** All processing runs in Frankfurt, Germany.
- **Integration assistance.** When a rule finding needs a human explanation, a person who knows the rule sets answers your support request.

## Get an API key

Sign up at [invoicexml.com](https://www.invoicexml.com/account/signup) to start a 30-day free trial with 100 credits. No credit card is needed. Plans are listed on the [pricing page](https://www.invoicexml.com/pricing).

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

### Embed XML into an existing PDF

```php
$zugferd = $client->embedZugferd('layout.pdf', 'factur-x.xml');
```

The XML is validated before embedding. Pass `skipValidation: true` to embed it as is.

### Render an XRechnung as PDF

```php
$preview = $client->renderXrechnungToPdf('xrechnung.xml', language: 'de');
```

### Read incoming invoices

```php
$data = $client->extractJson('incoming.pdf');             // ZUGFeRD PDF or XRechnung XML
$invoice = $data['invoice'];                              // same shape the create methods accept

$zip = $client->extractAttachments('incoming.pdf');       // supporting documents as a ZIP
```

Extraction parses the embedded XML deterministically, with no AI involved. The returned invoice can go straight back into `createZugferd()` or `createXrechnung()`.

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

When too many requests for one API key run at the same time, the API answers 429 with a `Retry-After` header. The client waits that long and resends the request, up to 3 times. A request answered with 429 was not processed and not billed, so resending never creates duplicates. If the retries are used up, a `TooManyRequestsException` carries `retryAfter` in seconds. Set `maxRetries: 0` to handle 429 yourself.

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
