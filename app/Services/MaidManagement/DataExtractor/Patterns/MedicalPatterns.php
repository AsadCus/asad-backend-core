<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class MedicalPatterns
{
    public static function allergies(): array
    {
        return [
            '/Allergies\s*\(if\s*any\)\s*:\s*___([A-Z][^_\n]+?)___/i',
            '/Allergies\s*\(if\s*any\)\s*:\s*([A-Z][A-Z\/\s]+?)(?=\s*-|\s*Past|\n)/i',
            '/\d+\.\s*Allergies\s*\(if\s*any\)\s*:\s*([^\n_]+?)(?=\s*\d+\.|\s*Past|\n|$)/i',
            '/Allergies\s*\(if\s*[Aa]ny\)\s*:\s*([^\n_]+?)(?=\s*Past|\s*Physical|\n|$)/i',
            '/Allergies:\s*([^\n_]+?)(?=\s*Physical|\n|$)/i',
        ];
    }

    public static function physicalDisabilities(): array
    {
        return [
            '/Physical\s+disabilities\s*:\s*___([A-Z][^_\n]+?)___/i',
            '/Physical\s+disabilities\s*:\s*([A-Z][A-Z\/]+)(?=\s*\n|$)/i',
            '/\d+\.\s*Physical\s+disabilities\s*:\s*([^\n_]+?)(?=\s*\d+\.|\s*Dietary|\n|$)/i',
            '/Physical\s+disabilities\s*:\s*([^\n_]+?)(?=\s*Dietary|\s*Food|\n|$)/i',
            '/Physical\s+[Dd]isability\s*:\s*([^\n_]+?)(?=\s*Dietary|\s*Food|\n|$)/i',
        ];
    }

    public static function dietaryRestrictions(): array
    {
        return [
            '/Dietary\s+restrictions\s*:\s*___([A-Z][^_\n]+?)___/i',
            '/Dietary\s+restrictions\s*:\s*([A-Z][A-Z\/]+)(?=\s*\n|$)/i',
            '/\d+\.\s*Dietary\s+restrictions\s*:\s*([^\n_]+?)(?=\s*\d+\.|\s*Food|\s*A3|\n|$)/i',
            '/Dietary\s+restrictions\s*:\s*([^\n_]+?)(?=\s*Food|\s*A3|\n|$)/i',
            '/Dietary\s+[Rr]estriction\s*:\s*([^\n_]+?)(?=\s*Food|\n|$)/i',
        ];
    }

    public static function foodPreferences(): array
    {
        return [
            '/Food\s+handling\s+preferences\s*:\s*([^\n]+?)(?=\s*\n\s*A3|\s*\n\s*Physical|\n\n|$)/i',
            '/(☒|☑|X|x)\s*No\s+pork/i',
            '/(☒|☑|X|x)\s*No\s+beef/i',
            '/(☒|☑|X|x)\s*Others\s*:\s*([^\n]+)/i',
            '/Yes-\s*Pork/i',
            '/Yes-\s*Beef/i',
        ];
    }

    public static function illnesses(): array
    {
        return [
            'Mental illness',
            'Epilepsy',
            'Asthma',
            'Diabetes',
            'Hypertension',
            'Tuberculosis',
            'Heart disease',
            'Malaria',
            'Operations',
            'Others',
        ];
    }

    public static function illnessPattern(string $illness): array
    {
        // Primary patterns expect enumerated items (i., ii., 1., 2., ...), but some docs omit numbering
        // Also handle common OCR typo: "Hearth disease" for "Heart disease"
        $patterns = [
            'Mental illness' => '/(?:i[…\. ]|1\.)\s*Mental\s*illness\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Epilepsy' => '/(?:ii[…\. ]|2\.)\s*Epilepsy\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Asthma' => '/(?:iii[…\. ]|3\.)\s*Asthma\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Diabetes' => '/(?:iv[…\. ]|4\.)\s*Diabetes\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Hypertension' => '/(?:v[…\. ]|5\.)\s*Hypertension\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Tuberculosis' => '/(?:vi[…\. ]|6\.)\s*Tuberculosis\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Heart disease' => '/(?:vii[…\. ]|7\.)\s*Hea?rth\s*disease\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Malaria' => '/(?:viii[…\. ]|8\.)\s*Malaria\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Operations' => '/(?:ix[…\. ]|9\.)\s*Operations\s*(☒|☑|X|x|√|✓|×|)?/i',
            'Others' => '/(?:x[…\. ]|10\.)\s*Others?\s*(☒|☑|X|x|√|✓|×|)?/i',
        ];

        $illnessEsc = preg_quote($illness, '/');
        // For Heart disease, allow "Hearth" typo in fallback too
        if ($illness === 'Heart disease') {
            $illnessEsc = 'Hea?rth\s*disease';
        }

        // Return both the enumerated pattern (if exists) and a generic fallback without numbering
        return array_values(array_filter([
            $patterns[$illness] ?? null,
            '/(?:'.$illnessEsc.')\s*(☒|☑|X|x|√|✓|×|)?/i',
        ]));
    }

    public static function illnessOthersValue(): array
    {
        return [
            '/(?:x[…\.]|10\.)\s*Others?\s*:\s*([A-Z][^\n]+)/i',
            '/Others?\s*:\s*([A-Z][^\n_]+)/i',
        ];
    }

    public static function restDay(): array
    {
        return [
            '/"rest_day"\s*:\s*"([0-9]+)"/i',
            '/Preference\s*for\s*rest\s*day\s*:\s*([0-9]+)\s*rest\s*day\(s\)\s*per\s*month/i',
            '/\d+\.\s*Preference\s*for\s*rest\s*day\s*:\s*([0-9]+)\s*rest\s*day\(s\)\s*per\s*month/i',
            '/Preference\s*for\s*rest\s*day\s*:\s*([0-9]+)rest\s*day\(s\)\s*per\s*month/i',
            '/Preferenceforrestday\s*:\s*([0-9]+)\s*restday\(s\)permonth/i',
            '/rest\s*day\(s\)\s*per\s*month\s*[:\.]?\s*([0-9]+)/i',
            '/Preference\s*for\s*rest\s*day\s*:\s*\n?\s*([0-9]+)/i',
            '/([0-9]+)\s*rest\s*day\(s\)\s*per\s*month/i',
            '/rest\s*day\s*:\s*([0-9]+)\s*(?:day|per)/i',
        ];
    }

    public static function remarks(): array
    {
        return [
            '/"remarks"\s*:\s*"([^"]+)"/i',
            '/\d+\.\s*Any\s*other\s*remarks\s*:\s*([A-Z][^\n]+?)(?=\s*\(B\)|\s*SKILL|\n\n|$)/is',
            '/Any\s*other\s*remarks\s*:\s*([A-Z][^\n]+?)(?=\s*\(B\)|\s*SKILL|\n\n|$)/is',
            '/Anyotherremarks\s*:\s*([A-Z][^\n]+?)(?=\s*\(B\)|\s*SKILL|\n\n|$)/is',
            '/Any\s*other\s*remarks\s*:\s*\n?\s*([A-Z][^\(]+?)(?=\s*\(B\)|\s*SKILL|$)/is',
            '/remarks\s*:\s*\n?\s*([^\n]+?)(?=\s*\(B\)|\s*SKILL|\n\n|$)/i',
        ];
    }
}
