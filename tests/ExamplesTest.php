<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung\Tests;

use InvoiceXml\ZugferdXrechnung\Client;
use PHPUnit\Framework\TestCase;

/**
 * The examples are never run here, because they call the API. They are linted, so a
 * syntax error cannot slip in, checked to load this repository's source, and checked
 * to call only methods the Client has.
 */
final class ExamplesTest extends TestCase
{
    /** @return list<string> */
    private static function examples(): array
    {
        $files = glob(dirname(__DIR__) . '/examples/*/*.php');

        return $files === false ? [] : $files;
    }

    public function testEveryExampleFolderHoldsOneScript(): void
    {
        $folders = glob(dirname(__DIR__) . '/examples/*', GLOB_ONLYDIR);

        self::assertNotEmpty($folders);
        foreach ($folders as $folder) {
            self::assertCount(1, glob($folder . '/*.php') ?: [], basename($folder) . ' must hold exactly one script.');
        }
    }

    public function testEveryExampleLintsAndLoadsTheRepositorySource(): void
    {
        $examples = self::examples();
        self::assertNotEmpty($examples);

        foreach ($examples as $example) {
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($example) . ' 2>&1', $output, $exitCode);
            self::assertSame(0, $exitCode, $example . ': ' . implode("\n", $output));

            $source = (string) file_get_contents($example);
            self::assertStringContainsString("require __DIR__ . '/../../vendor/autoload.php';", $source, $example);
            self::assertStringContainsString("const API_KEY = 'YOUR_API_KEY';", $source, $example);
        }
    }

    public function testEveryExampleCallsOnlyPublicClientMethods(): void
    {
        $client = new \ReflectionClass(Client::class);

        foreach (self::examples() as $example) {
            $source = (string) file_get_contents($example);
            preg_match_all('/\$client->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches);
            self::assertNotEmpty($matches[1], $example . ' calls no client method.');

            foreach (array_unique($matches[1]) as $method) {
                self::assertTrue(
                    $client->hasMethod($method) && $client->getMethod($method)->isPublic(),
                    $example . ' calls $client->' . $method . '(), which is not a public method of Client.',
                );
            }
        }
    }
}
