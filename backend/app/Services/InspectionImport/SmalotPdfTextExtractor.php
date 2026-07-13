<?php

namespace App\Services\InspectionImport;

use Smalot\PdfParser\Parser;
use Throwable;

class SmalotPdfTextExtractor implements PdfTextExtractorInterface
{
    public function extract(string $contents): string
    {
        try {
            return (new Parser)->parseContent($contents)->getText();
        } catch (Throwable $e) {
            throw new PdfExtractionException("PDF text extraction failed: {$e->getMessage()}", previous: $e);
        }
    }
}
