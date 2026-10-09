<?php

declare(strict_types=1);

namespace InvoiceXml\ZugferdXrechnung;

/**
 * A file sent to or returned by the API: raw bytes plus a file name and media type.
 *
 * Every method that uploads a file accepts either a path on disk or a File, so the
 * output of one call (a created ZUGFeRD PDF, for example) can go straight into the next.
 */
final class File
{
    public readonly string $contentType;

    public function __construct(
        public readonly string $contents,
        public readonly string $filename,
        ?string $contentType = null,
    ) {
        $this->contentType = $contentType ?? self::detectContentType($contents, $filename);
    }

    /** Reads a file from disk. */
    public static function fromPath(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('File "%s" does not exist or is not readable.', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \InvalidArgumentException(sprintf('File "%s" could not be read.', $path));
        }

        return new self($contents, basename($path));
    }

    /** Writes the file to disk and returns the number of bytes written. */
    public function save(string $path): int
    {
        $written = file_put_contents($path, $this->contents);
        if ($written === false) {
            throw new \RuntimeException(sprintf('File "%s" could not be written.', $path));
        }

        return $written;
    }

    public function size(): int
    {
        return strlen($this->contents);
    }

    private static function detectContentType(string $contents, string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'pdf' || str_starts_with($contents, '%PDF')) {
            return 'application/pdf';
        }
        if ($extension === 'xml' || str_starts_with(ltrim($contents, "\xEF\xBB\xBF \t\r\n"), '<')) {
            return 'application/xml';
        }
        if ($extension === 'zip') {
            return 'application/zip';
        }

        return 'application/octet-stream';
    }
}
