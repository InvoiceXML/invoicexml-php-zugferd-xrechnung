<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvoiceXml\ZugferdXrechnung\ApiException;
use InvoiceXml\ZugferdXrechnung\Client;
use InvoiceXml\ZugferdXrechnung\File;
use InvoiceXml\ZugferdXrechnung\TooManyRequestsException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ClientTest extends TestCase
{
    private MockHandler $mock;

    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    /** @var list<int> */
    private array $sleeps = [];

    private function client(Response ...$responses): Client
    {
        $this->mock = new MockHandler($responses);
        $this->history = [];
        $this->sleeps = [];
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new Client(
            'test-key',
            new GuzzleClient(['handler' => $stack]),
            sleep: function (int $seconds): void {
                $this->sleeps[] = $seconds;
            },
        );
    }

    private function lastRequest(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }

    /** @return array<string, mixed> */
    private static function invoice(): array
    {
        return [
            'invoiceNumber' => 'INV-2026-00045',
            'issueDate' => '2026-05-17',
            'currency' => 'EUR',
            'seller' => ['name' => 'Acme Engineering GmbH', 'vatIdentifier' => 'DE123456789'],
            'buyer' => ['name' => 'Globex SAS'],
            'lines' => [['quantity' => 10, 'priceDetails' => ['netPrice' => 150], 'vatInformation' => ['rate' => 19], 'item' => ['name' => 'Senior consulting']]],
        ];
    }

    private static function pdfResponse(string $name = 'invoice-zugferd-INV-1.pdf'): Response
    {
        return new Response(200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$name}\"; filename*=UTF-8''" . rawurlencode($name),
        ], '%PDF-1.7 test');
    }

    /** @param array<string, mixed> $body */
    private static function jsonResponse(array $body, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function testCreateZugferdPostsEnvelopeAndReturnsPdf(): void
    {
        $client = $this->client(self::pdfResponse());

        $file = $client->createZugferd(self::invoice(), ['language' => 'de']);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.invoicexml.com/v1/create/zugferd', (string) $request->getUri());
        self::assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            ['invoice' => self::invoice(), 'options' => ['language' => 'de']],
            json_decode((string) $request->getBody(), true),
        );

        self::assertSame('%PDF-1.7 test', $file->contents);
        self::assertSame('invoice-zugferd-INV-1.pdf', $file->filename);
        self::assertSame('application/pdf', $file->contentType);
    }

    public function testCreateOmitsOptionsWhenEmpty(): void
    {
        $client = $this->client(new Response(200, ['Content-Type' => 'application/xml'], '<Invoice/>'));

        $file = $client->createXrechnung(self::invoice());

        self::assertSame('https://api.invoicexml.com/v1/create/xrechnung', (string) $this->lastRequest()->getUri());
        self::assertSame(['invoice' => self::invoice()], json_decode((string) $this->lastRequest()->getBody(), true));
        self::assertSame('invoice-xrechnung.xml', $file->filename);
        self::assertSame('application/xml', $file->contentType);
    }

    public function testCreateXrechnungPassesSyntaxOption(): void
    {
        $client = $this->client(new Response(200, ['Content-Type' => 'application/xml'], '<rsm:CrossIndustryInvoice/>'));

        $client->createXrechnung(self::invoice(), ['syntax' => 'cii']);

        self::assertSame(['syntax' => 'cii'], json_decode((string) $this->lastRequest()->getBody(), true)['options']);
    }

    public function testRejectedCreateThrowsWithFindings(): void
    {
        $client = $this->client(self::jsonResponse([
            'type' => 'https://tools.ietf.org/html/rfc9110#section-15.5.1',
            'title' => 'Invoice validation failed',
            'status' => 400,
            'detail' => 'The generated invoice violates 1 rule.',
            'valid' => false,
            'errorCode' => 4001,
            'errors' => [[
                'rule' => 'BR-DE-15',
                'layer' => 'cius',
                'line' => 12,
                'message' => 'The buyer reference (Leitweg-ID) is missing.',
                'btCodes' => ['BT-10'],
                'fields' => ['buyerReference'],
                'raw' => '[BR-DE-15] ...',
            ]],
            'warnings' => [],
        ], 400, ['Content-Type' => 'application/problem+json']));

        try {
            $client->createXrechnung(self::invoice());
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(400, $e->statusCode);
            self::assertSame(4001, $e->errorCode);
            self::assertSame('Invoice validation failed', $e->title);
            self::assertCount(1, $e->errors);
            self::assertSame('BR-DE-15', $e->errors[0]->rule);
            self::assertSame('cius', $e->errors[0]->layer);
            self::assertSame(12, $e->errors[0]->line);
            self::assertSame(['BT-10'], $e->errors[0]->btCodes);
            self::assertSame(['buyerReference'], $e->errors[0]->fields);
            self::assertStringContainsString('400', $e->getMessage());
        }
    }

    public function testModelErrorFindingsWithoutRuleAreMapped(): void
    {
        $client = $this->client(self::jsonResponse([
            'title' => 'One or more validation errors occurred.',
            'status' => 400,
            'errorCode' => 4002,
            'errors' => [['rule' => null, 'layer' => null, 'line' => null, 'message' => 'The seller name is required.', 'btCodes' => ['BT-27'], 'fields' => ['seller.name'], 'raw' => null]],
        ], 400));

        try {
            $client->createZugferd(self::invoice());
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(4002, $e->errorCode);
            self::assertNull($e->errors[0]->rule);
            self::assertNull($e->errors[0]->layer);
            self::assertSame(['seller.name'], $e->errors[0]->fields);
        }
    }

    public function testValidateZugferdUploadsMultipartAndMapsResult(): void
    {
        $client = $this->client(self::jsonResponse([
            'valid' => false,
            'detail' => 'The invoice violates 1 rule.',
            'data' => ['hasEmbeddedXml' => true, 'schemaValid' => true, 'schematronValid' => false, 'profile' => 'en16931'],
            'errors' => [['rule' => 'BR-CO-15', 'layer' => 'en16931', 'line' => 88, 'message' => 'Total with VAT does not match.', 'btCodes' => ['BT-112'], 'fields' => ['totals.amountDueForPayment'], 'raw' => 'x']],
            'warnings' => [],
            'report' => [['code' => 'BT-1', 'name' => 'Invoice number', 'section' => 'header', 'path' => 'invoiceNumber', 'value' => 'INV-1', 'isValid' => true, 'errors' => [], 'warnings' => []]],
        ]));

        $result = $client->validateZugferd(new File('%PDF-1.7 bytes', 'invoice.pdf'));

        $request = $this->lastRequest();
        self::assertSame('https://api.invoicexml.com/v1/validate/zugferd', (string) $request->getUri());
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        $body = (string) $request->getBody();
        self::assertStringContainsString('Content-Disposition: form-data; name="file"; filename="invoice.pdf"', $body);
        self::assertStringContainsString('Content-Type: application/pdf', $body);
        self::assertStringContainsString('%PDF-1.7 bytes', $body);

        self::assertFalse($result->valid);
        self::assertSame('The invoice violates 1 rule.', $result->detail);
        self::assertSame('en16931', $result->data['profile']);
        self::assertSame('BR-CO-15', $result->errors[0]->rule);
        self::assertSame('en16931', $result->errors[0]->layer);
        self::assertSame('BT-1', $result->report[0]['code']);
    }

    public function testValidateXrechnungReadsFileFromPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ixml') . '.xml';
        file_put_contents($path, '<?xml version="1.0"?><Invoice/>');

        try {
            $client = $this->client(self::jsonResponse(['valid' => true, 'errors' => [], 'warnings' => []]));
            $result = $client->validateXrechnung($path);
        } finally {
            unlink($path);
        }

        $body = (string) $this->lastRequest()->getBody();
        self::assertSame('https://api.invoicexml.com/v1/validate/xrechnung', (string) $this->lastRequest()->getUri());
        self::assertStringContainsString('filename="' . basename($path) . '"', $body);
        self::assertStringContainsString('Content-Type: application/xml', $body);
        self::assertTrue($result->valid);
        self::assertSame([], $result->errors);
        self::assertSame([], $result->report);
    }

    public function testEmbedZugferdSendsBothPartsAndSkipValidation(): void
    {
        $client = $this->client(self::pdfResponse());

        $client->embedZugferd(new File('%PDF-1.7 visual', 'visual.pdf'), new File('<rsm:CrossIndustryInvoice/>', 'factur-x.xml'), true);

        $body = (string) $this->lastRequest()->getBody();
        self::assertSame('https://api.invoicexml.com/v1/embed/zugferd', (string) $this->lastRequest()->getUri());
        self::assertStringContainsString('name="pdf"; filename="visual.pdf"', $body);
        self::assertStringContainsString('name="xml"; filename="factur-x.xml"', $body);
        self::assertStringContainsString("name=\"skipValidation\"\r\n\r\ntrue", $body);
    }

    public function testEmbedZugferdOmitsSkipValidationByDefault(): void
    {
        $client = $this->client(self::pdfResponse());

        $client->embedZugferd(new File('%PDF', 'a.pdf'), new File('<x/>', 'a.xml'));

        self::assertStringNotContainsString('skipValidation', (string) $this->lastRequest()->getBody());
    }

    public function testRenderXrechnungSendsLanguageAndLogo(): void
    {
        $client = $this->client(self::pdfResponse('invoice.pdf'));

        $file = $client->renderXrechnungToPdf(new File('<Invoice/>', 'x.xml'), 'de', 'https://example.com/logo.png');

        $body = (string) $this->lastRequest()->getBody();
        self::assertSame('https://api.invoicexml.com/v1/render/xrechnung/to/pdf', (string) $this->lastRequest()->getUri());
        self::assertStringContainsString("name=\"language\"\r\n\r\nde", $body);
        self::assertStringContainsString("name=\"logoUrl\"\r\n\r\nhttps://example.com/logo.png", $body);
        self::assertSame('invoice.pdf', $file->filename);
    }

    public function testExtractJsonReturnsInvoiceEnvelope(): void
    {
        $client = $this->client(self::jsonResponse(['invoice' => self::invoice()]));

        $result = $client->extractJson(new File('%PDF', 'in.pdf'));

        self::assertSame('https://api.invoicexml.com/v1/extract/json', (string) $this->lastRequest()->getUri());
        self::assertSame(self::invoice(), $result['invoice']);
    }

    public function testExtractAttachmentsReturnsZip(): void
    {
        $name = 'in-attachments.zip';
        $client = $this->client(new Response(200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ], "PK\x03\x04"));

        $file = $client->extractAttachments(new File('%PDF', 'in.pdf'));

        self::assertSame('https://api.invoicexml.com/v1/extract/attachments', (string) $this->lastRequest()->getUri());
        self::assertSame($name, $file->filename);
        self::assertSame('application/zip', $file->contentType);
    }

    public function testUnicodeFilenameIsDecoded(): void
    {
        $client = $this->client(new Response(200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"Rechnung-M_ller.pdf\"; filename*=UTF-8''Rechnung-M%C3%BCller.pdf",
        ], '%PDF'));

        self::assertSame('Rechnung-Müller.pdf', $client->createZugferd(self::invoice())->filename);
    }

    public function testRetriesAfter429AndHonoursRetryAfter(): void
    {
        $client = $this->client(
            self::jsonResponse(['status' => 429, 'errorCode' => 4019], 429, ['Retry-After' => '7']),
            self::pdfResponse(),
        );

        $file = $client->createZugferd(self::invoice());

        self::assertSame([7], $this->sleeps);
        self::assertCount(2, $this->history);
        self::assertSame(
            (string) $this->history[0]['request']->getBody(),
            (string) $this->history[1]['request']->getBody(),
        );
        self::assertSame('%PDF-1.7 test', $file->contents);
    }

    public function testThrowsTooManyRequestsWhenRetriesAreUsedUp(): void
    {
        $limited = static fn (): Response => self::jsonResponse(
            ['title' => 'Too Many Requests', 'status' => 429, 'detail' => 'Retry later.', 'errorCode' => 4019],
            429,
            ['Retry-After' => '3'],
        );
        $client = $this->client($limited(), $limited(), $limited(), $limited());

        try {
            $client->validateXrechnung(new File('<Invoice/>', 'x.xml'));
            self::fail('Expected TooManyRequestsException');
        } catch (TooManyRequestsException $e) {
            self::assertSame(429, $e->statusCode);
            self::assertSame(3, $e->retryAfter);
            self::assertSame(4019, $e->errorCode);
        }

        self::assertSame([3, 3, 3], $this->sleeps);
        self::assertCount(4, $this->history);
    }

    public function testRetriesCanBeDisabled(): void
    {
        $stack = HandlerStack::create(new MockHandler([self::jsonResponse(['status' => 429], 429, ['Retry-After' => '5'])]));
        $client = new Client('k', new GuzzleClient(['handler' => $stack]), maxRetries: 0);

        $this->expectException(TooManyRequestsException::class);
        $client->createZugferd(self::invoice());
    }

    public function testUnauthorizedWithNonJsonBody(): void
    {
        $client = $this->client(new Response(401, [], 'Unauthorized'));

        try {
            $client->createZugferd(self::invoice());
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->statusCode);
            self::assertSame('Unauthorized', $e->detail);
            self::assertNull($e->errorCode);
            self::assertSame([], $e->errors);
        }
    }

    public function testCustomBaseUrl(): void
    {
        $stack = HandlerStack::create(new MockHandler([self::pdfResponse()]));
        $history = [];
        $stack->push(Middleware::history($history));
        $client = new Client('k', new GuzzleClient(['handler' => $stack]), baseUrl: 'https://staging.example.test/');

        $client->createZugferd(self::invoice());

        self::assertSame('https://staging.example.test/v1/create/zugferd', (string) $history[0]['request']->getUri());
    }

    public function testEmptyApiKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(' ');
    }

    public function testMissingUploadPathIsRejected(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);
        $client->validateZugferd('/does/not/exist.pdf');
    }
}
