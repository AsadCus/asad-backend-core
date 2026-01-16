<?php

namespace App\Services\MaidManagement\FileParser;

use Smalot\PdfParser\Parser;
use Exception;

class PdfParser
{
    private array $errors = [];
    private array $metadata = [];

    public function parse(string $path): string
    {
        try {
            $this->validateFile($path);
            
            $parser = new Parser();
            $pdf = $parser->parseFile($path);
            
            $pages = $pdf->getPages();
            $this->metadata['page_count'] = count($pages);
            
            $text = $pdf->getText();
            
            if (empty(trim($text))) {
                throw new Exception("No text extracted from PDF. Document might be scanned or image-based.");
            }
            
            $text = $this->normalizeText($text);
            $this->validateStructure($text);
            $this->metadata['text_length'] = strlen($text);
            
            return trim($text);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            throw new Exception("PDF parsing failed: " . $e->getMessage());
        }
    }

    private function validateFile(string $path): void
    {
        if (!file_exists($path)) {
            throw new Exception("File not found: $path");
        }
        
        if (filesize($path) === 0) {
            throw new Exception("File is empty");
        }
        
        if (filesize($path) > 10 * 1024 * 1024) {
            throw new Exception("File too large (max 10MB)");
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);
        
        if ($mimeType !== 'application/pdf') {
            throw new Exception("Invalid file type: expected PDF, got $mimeType");
        }
    }

    private function normalizeText(string $text): string
    {
        // Add spaces between concatenated words (e.g., "ZOHRATULAINI" -> "ZOHRATUL AINI")
        $text = $this->addSpacesBetweenWords($text);

        $dashVariants = [
            "\xE2\x80\x90",
            "\xE2\x80\x91",
            "\xE2\x80\x92",
            "\xE2\x80\x93",
            "\xE2\x80\x94",
            "\xE2\x80\x95",
        ];
        $text = str_replace($dashVariants, '-', $text);
        $text = str_replace("\xC2\xAD", '', $text);
        
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", $text);
        $text = trim($text);
        $text = preg_replace('/A-\d+\s*/', '', $text);

        $replacements = [
            'Height:'            => 'Height & weight:',
            '& Weight:'          => '& weight:',
            'Education Level'    => 'Education level:',
            'Marital status'     => 'Marital status:',
            'Number of children' => 'Number of children:',
            'Age(s) of children (if any)' => 'Age(s) of children (if any):',
            'Allergies (if any) :' => 'Allergies (if any):',
            'Employment  HISTORY' => 'Employment history',
            'Hearth disease'     => 'Heart disease',
        ];
        $text = str_ireplace(array_keys($replacements), array_values($replacements), $text);

        $text = str_replace(["", "", "", "✓"], ["☐", "☒", "☑", "☑"], $text);
        $text = preg_replace('/^\d+\.\s*/m', '', $text);
        $text = preg_replace('/(\d{1,2})\.\s*/', '$1. ', $text);
        $text = preg_replace('/(\([A-E]\))/', "\n$1", $text);
        $text = preg_replace('/:{2,}/', ':', $text);
        
        $text = $this->handleMultiColumnLayout($text);

        return $text;
    }

    private function addSpacesBetweenWords(string $text): string
    {
        // Add space before capital letters in concatenated words
        // But preserve acronyms and intentional all-caps words
        $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text);
        
        // Add space between lowercase and uppercase sequences
        $text = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $text);
        
        // For all-caps concatenated words, add space before common patterns
        // Pattern: WORD1WORD2 where both are all caps
        // This is a best-effort approach for common words
        $commonWords = [
            'TAKE', 'CARE', 'OF', 'CHILDREN', 'ELDERLY', 'GENERAL', 'HOUSEWORK',
            'DO', 'SWEEP', 'AND', 'MOP', 'THE', 'FLOOR', 'WASH', 'IRONING',
            'CLOTHES', 'NASI', 'LEMAK', 'CURRY', 'RENDANG', 'FISH', 'FRIED',
            'CHICKEN', 'AYAM', 'CHILI', 'PADI', 'ASAM', 'PEDAS', 'NEWBORN',
            'YO', 'FINISH', 'INDIA', 'MELAYU', 'ARAB', 'SINGAPORE', 'UAE',
        ];
        
        foreach ($commonWords as $word) {
            // Add space before the word if it's preceded by another letter
            $text = preg_replace('/([A-Z])(' . preg_quote($word, '/') . ')/', '$1 $2', $text);
        }
        
        return $text;
    }

    private function handleMultiColumnLayout(string $text): string
    {
        $lines = explode("\n", $text);
        $processedLines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/(.{20,})\s{5,}(.{20,})/', $line, $matches)) {
                $processedLines[] = trim($matches[1]);
                $processedLines[] = trim($matches[2]);
            } else {
                $processedLines[] = $line;
            }
        }
        
        return implode("\n", $processedLines);
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
        
        if (!empty($missingSections)) {
            $this->errors[] = "Missing sections: " . implode(', ', $missingSections);
        }
        
        if (strlen($text) < 100) {
            $this->errors[] = "Document content too short";
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
