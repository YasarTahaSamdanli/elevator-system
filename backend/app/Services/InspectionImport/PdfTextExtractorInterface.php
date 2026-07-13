<?php

namespace App\Services\InspectionImport;

interface PdfTextExtractorInterface
{
    /**
     * Extract the full text layer of a PDF document.
     *
     * @param  string  $contents  raw PDF bytes
     *
     * @throws PdfExtractionException when the document cannot be read
     */
    public function extract(string $contents): string;
}
