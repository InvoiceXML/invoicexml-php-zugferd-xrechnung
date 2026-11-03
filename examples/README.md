# Examples

Small scripts, one per common task. Each one is a complete `example.php` you can read from top to bottom and adapt.

| Example | What it shows |
|---|---|
| [create-zugferd-with-own-pdf](create-zugferd-with-own-pdf/example.php) | Create a ZUGFeRD invoice that keeps your own PDF layout, passed as a public URL (`pdfUrl`). |
| [create-cii-and-embed](create-cii-and-embed/example.php) | Build a CII XML from your invoice data with `createCii()`, then embed it into a PDF you already have with `embedZugferd()`. |
| [email-zugferd](email-zugferd/example.php) | Create a ZUGFeRD invoice and email it from your own verified domain in one call. |
| [email-embedded-zugferd](email-embedded-zugferd/example.php) | Build a CII XML from your invoice data, then embed it into a PDF you already have and email the ZUGFeRD invoice with `emailEmbeddedZugferd()`. |
| [validate-invoices](validate-invoices/example.php) | Validate a ZUGFeRD PDF and an XRechnung XML, print every finding, and save the printable validation report. |
| [render-xml-to-pdf](render-xml-to-pdf/example.php) | Turn any invoice XML (XRechnung, CII or UBL) into a readable PDF. |
| [convert-ubl-to-zugferd](convert-ubl-to-zugferd/example.php) | Convert a UBL invoice XML into a ZUGFeRD invoice. |
| [transform-to-zugferd](transform-to-zugferd/example.php) | Turn an ordinary PDF invoice into a ZUGFeRD invoice. AI reads the PDF, so a person checks the result. |
| [parse-pdf-invoice](parse-pdf-invoice/example.php) | Read an ordinary PDF invoice, typed, scanned or photographed, into invoice data with AI, and see how sure the read was per area. |
| [extract-json-and-attachments](extract-json-and-attachments/example.php) | Read a received ZUGFeRD invoice as invoice data and save its embedded attachments as a ZIP. |

## Run an example

1. Install the dependencies once from the repository root with `composer install`.
2. Open the example's `example.php` and replace the values at the top: `YOUR_API_KEY` with your API key, and the file paths or URLs with your own. An example whose file is missing or unreadable tells you which value to change.
3. Run it from the repository root, for example:

```bash
php examples/create-cii-and-embed/example.php
```

The examples load the library source in this repository through `vendor/autoload.php`. To use one in your own project, copy its `example.php`, install the package instead and change the `require` line to your project's `vendor/autoload.php`:

```bash
composer require invoicexml/zugferd-xrechnung
```

Get an API key with the **30-day free trial: 100 credits, no credit card required** at [www.invoicexml.com/account/signup](https://www.invoicexml.com/account/signup). The calls use credits from your account, so run the examples with test data.
