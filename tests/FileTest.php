<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung\Tests;

use InvoiceXml\ZugferdXrechnung\File;
use PHPUnit\Framework\TestCase;

final class FileTest extends TestCase
{
    public function testDetectsContentTypeFromExtensionAndBytes(): void
    {
        self::assertSame('application/pdf', (new File('anything', 'a.PDF'))->contentType);
        self::assertSame('application/pdf', (new File('%PDF-1.7', 'upload'))->contentType);
        self::assertSame('application/xml', (new File('anything', 'a.xml'))->contentType);
        self::assertSame('application/xml', (new File("\xEF\xBB\xBF\n<?xml version=\"1.0\"?>", 'upload'))->contentType);
        self::assertSame('application/octet-stream', (new File('plain', 'upload'))->contentType);
        self::assertSame('text/xml', (new File('<x/>', 'a.xml', 'text/xml'))->contentType);
    }

    public function testRoundTripsThroughDisk(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ixml');

        try {
            $written = (new File('%PDF-1.7 data', 'x.pdf'))->save($path);
            $file = File::fromPath($path);
        } finally {
            unlink($path);
        }

        self::assertSame(13, $written);
        self::assertSame('%PDF-1.7 data', $file->contents);
        self::assertSame(basename($path), $file->filename);
        self::assertSame('application/pdf', $file->contentType);
        self::assertSame(13, $file->size());
    }
}
