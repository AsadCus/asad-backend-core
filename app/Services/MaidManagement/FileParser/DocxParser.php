<?php

namespace App\Services\MaidManagement\FileParser;

use DOMDocument;
use DOMXPath;
use Exception;
use ZipArchive;

class DocxParser
{
    private array $errors = [];

    private array $metadata = [];

    public function parse(string $path): string
    {
        try {
            // Normalize path separators for Windows
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
            $this->validateFile($path);

            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new Exception("Unable to open DOCX file: $path");
            }

            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                $zip->close();
                throw new Exception('Invalid DOCX structure: document.xml not found');
            }
            $zip->close();

            $doc = new DOMDocument;
            if (! @$doc->loadXML($xml)) {
                throw new Exception('Failed to parse XML content');
            }

            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $textParts = $this->extractTextWithTables($xpath);
            $text = $this->normalizeText(implode("\n", $textParts));

            $this->validateStructure($text);
            $this->metadata['text_length'] = strlen($text);
            $this->metadata['has_tables'] = $this->hasTableContent($text);

            return trim($text);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            throw new Exception('DOCX parsing failed: '.$e->getMessage());
        }
    }

    private function validateFile(string $path): void
    {
        if (! file_exists($path)) {
            throw new Exception("File not found: $path");
        }

        if (filesize($path) === 0) {
            throw new Exception('File is empty');
        }

        if (filesize($path) > 10 * 1024 * 1024) {
            throw new Exception('File too large (max 10MB)');
        }
    }

    private function extractTextWithTables(DOMXPath $xpath): array
    {
        $textParts = [];
        $body = $xpath->query('//w:body')->item(0);

        if (! $body) {
            throw new Exception('Document body not found');
        }

        foreach ($body->childNodes as $node) {
            if ($node->nodeName === 'w:tbl') {
                $tableText = $this->extractTable($xpath, $node);
                if (! empty($tableText)) {
                    $textParts[] = $tableText;
                }
            } elseif ($node->nodeName === 'w:p') {
                $pText = $this->extractParagraph($xpath, $node);
                if (trim($pText) !== '') {
                    $textParts[] = trim($pText);
                }
            }
        }

        return $textParts;
    }

    private function extractTable(DOMXPath $xpath, $tableNode): string
    {
        $rows = $xpath->query('.//w:tr', $tableNode);
        $tableLines = [];

        foreach ($rows as $row) {
            $cells = $xpath->query('.//w:tc', $row);
            $rowCells = [];

            foreach ($cells as $cell) {
                $cellParagraphs = [];
                $paragraphs = $xpath->query('.//w:p', $cell);

                foreach ($paragraphs as $p) {
                    $pText = '';
                    $texts = $xpath->query('.//w:t', $p);
                    foreach ($texts as $t) {
                        $pText .= $t->nodeValue;
                    }
                    if (trim($pText) !== '') {
                        $cellParagraphs[] = trim($pText);
                    }
                }

                if (! empty($cellParagraphs)) {
                    $rowCells[] = implode("\n", $cellParagraphs);
                }
            }

            if (! empty($rowCells)) {
                // Join cells in row with newline to preserve vertical structure
                $tableLines[] = implode("\n", $rowCells);
            }
        }

        return implode("\n", $tableLines);
    }

    private function extractParagraph(DOMXPath $xpath, $pNode): string
    {
        $pText = '';
        $runs = $xpath->query('.//w:t', $pNode);
        foreach ($runs as $r) {
            $pText .= $r->nodeValue;
        }

        $checkboxes = $xpath->query('.//w:checkBox', $pNode);
        foreach ($checkboxes as $cb) {
            $checked = $xpath->query('.//w:checked', $cb);
            if ($checked->length > 0 && $checked->item(0)->getAttribute('w:val') === '1') {
                $pText .= ' ☒';
            } else {
                $pText .= ' ☐';
            }
        }

        return $pText;
    }

    private function normalizeText(string $text): string
    {
        $dashVariants = [
            "\xE2\x80\x90", // hyphen
            "\xE2\x80\x91", // non-breaking hyphen
            "\xE2\x80\x92", // figure dash
            "\xE2\x80\x93", // en dash
            "\xE2\x80\x94", // em dash
            "\xE2\x80\x95", // horizontal bar
        ];

        $text = str_replace($dashVariants, '-', $text);
        $text = str_replace("\xC2\xAD", '', $text); // soft hyphen
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", $text);
        $text = preg_replace('/(\d{1,2})\.\s*/', '$1. ', $text);
        $text = preg_replace('/(\([A-E]\))/', "\n$1", $text);
        $text = preg_replace('/:{2,}/', ':', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $text;
    }

    private function validateStructure(string $text): void
    {
        $requiredSections = ['(A)', '(B)', '(C)'];
        $missingSections = [];

        foreach ($requiredSections as $section) {
            if (stripos($text, $section) === false) {
                $missingSections[] = $section;
            }
        }

        if (! empty($missingSections)) {
            $this->errors[] = 'Missing sections: '.implode(', ', $missingSections);
        }

        if (strlen($text) < 100) {
            $this->errors[] = 'Document content too short';
        }
    }

    private function hasTableContent(string $text): bool
    {
        return strpos($text, '|') !== false;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Extract photo dari DOCX dan return binary content
     *
     * @param  string  $path  Path ke file DOCX
     * @return array|null ['content' => binary, 'filename' => string, 'mime_type' => string]
     */
    public function extractPhoto(string $path): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        try {
            // Loop through all files in the archive
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                // Check if it's an image in word/media directory
                if (preg_match('/word\/media\/(.*\.(jpe?g|png|bmp|gif))$/i', $entry, $matches)) {
                    $imgContent = $zip->getFromIndex($i);

                    if ($imgContent === false || empty($imgContent)) {
                        continue;
                    }

                    // Detect mime type
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($imgContent);

                    $zip->close();

                    return [
                        'content' => $imgContent,
                        'filename' => $matches[1], // Original filename from DOCX
                        'mime_type' => $mimeType,
                        'size' => strlen($imgContent),
                    ];
                }
            }
        } catch (Exception $e) {
            $this->errors[] = 'Photo extraction failed: '.$e->getMessage();
        } finally {
            $zip->close();
        }

        return null;
    }

    /**
     * Extract semua photos dari DOCX
     *
     * @param  string  $path  Path ke file DOCX
     * @return array Array of photo data
     */
    public function extractAllPhotos(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        $photos = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if (preg_match('/word\/media\/(.*\.(jpe?g|png|bmp|gif))$/i', $entry, $matches)) {
                    $imgContent = $zip->getFromIndex($i);

                    if ($imgContent === false || empty($imgContent)) {
                        continue;
                    }

                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($imgContent);

                    $photos[] = [
                        'content' => $imgContent,
                        'filename' => $matches[1],
                        'mime_type' => $mimeType,
                        'size' => strlen($imgContent),
                        'index' => count($photos), // 0-based index
                    ];
                }
            }
        } catch (Exception $e) {
            $this->errors[] = 'Photos extraction failed: '.$e->getMessage();
        } finally {
            $zip->close();
        }

        return $photos;
    }
}
