<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class SkillsPatterns
{
    public static function skillDataPattern(string $skillLabel): array
    {
        $escaped = preg_quote($skillLabel, '/');
        
        return [
            // Pattern 1: With detailed assessment
            '/' . $escaped . '.*?\n.*?\n(Yes|No)\s*\n(Yes|No)\s*\n([^\n]+(?:\n[A-Z][^\n]+)*?)(?=\n\d+\.|$)/is',
            
            // Pattern 2: With numeric assessment
            '/' . $escaped . '.*?\n.*?\n(Yes|No)\s*\n(Yes|No)\s*\n([0-9]+)/is',
            
            // Pattern 3: Simple Yes/No
            '/' . $escaped . '.*?\n.*?\n(Yes|No)\s*\n(Yes|No)/is',
            
            // Pattern 4: Compact format
            '/' . $escaped . '[^\n]*\n(Yes|No)\s*(Yes|No)\s*([^\n]*)/is',
        ];
    }

    public static function evaluationMethods(): array
    {
        return [
            'fdw_declaration' => '/(☒|☑|X|√)\s*Based on FDW\'?s declaration/i',
            'interviewed_by_sg_ea' => '/(☒|☑|X|√)\s*Interviewed by Singapore EA/i',
            'telephone_teleconference' => '/(☒|☑|X|√)\s*Interviewed via telephone/i',
            'videoconference' => '/(☒|☑|X|√)\s*Interviewed via videoconference/i',
            'in_person' => '/(☒|☑|X|√)\s*Interviewed in person(?!\s+and also)/i',
            'in_person_with_observation' => '/(☒|☑|X|√)\s*Interviewed in person and also made observation/i',
        ];
    }

    public static function specificDetails(): array
    {
        return [
            'infant_age_range' => '/Care of infants.*?age range:\s*([^\n]+)/is',
            'cooking_cuisines' => '/Cooking.*?cuisines:\s*([^\n]+)/is',
            'language_specify' => '/Language abilities.*?specify:\s*([^\n]+)/is',
            'other_skills_specify' => '/Other skills.*?Specify:\s*([^\n]+)/is',
        ];
    }

    public static function trainingCentre(): array
    {
        return [
            '/Interviewed by overseas training centre.*?EA:\s*([^\n]+)/is',
            '/name of foreign training centre.*?:\s*([^\n]+)/is',
        ];
    }

    public static function certification(): array
    {
        return [
            '/certified.*?:\s*([^\n]+)/is',
            '/ISO9001.*?:\s*([^\n]+)/is',
        ];
    }
}
