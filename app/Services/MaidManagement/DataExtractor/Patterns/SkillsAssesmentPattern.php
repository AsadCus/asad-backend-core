<?php

namespace App\Services\MaidManagement\DataExtractor\Patterns;

class SkillsAssesmentPattern
{
    public static function getPatterns()
    {
        return [
            'pattern_1' => self::pattern1(),
            'pattern_2' => self::pattern2(),
            'pattern_3' => self::pattern3(),
            'pattern_4' => self::pattern4(),
        ];
    }

    // Pattern 1: Format dengan spasi normal dan numbering (1., 2., 3., dst)
    private static function pattern1()
    {
        return [
            'table_markers' => [
                'start' => '/S\/No\s+Areas of Work\s+Willingness\s+Yes\/No\s+Experience\s+Yes\/No/i',
                'end' => '/Interviewed by overseas training centre/i',
            ],
            'headers' => [
                'sno' => 'S/No',
                'areas_of_work' => 'Areas of Work',
                'willingness' => 'Willingness Yes/No',
                'experience' => 'Experience Yes/No',
                'years' => 'If yes, state the no. of years',
                'assessment' => 'Assessment/Observation',
            ],
            'areas_of_work' => [
                1 => [
                    'key' => 'care_of_infants',
                    'label' => 'Care of infants/children',
                ],
                2 => [
                    'key' => 'care_of_elderly',
                    'label' => 'Care of elderly',
                ],
                3 => [
                    'key' => 'care_of_disabled',
                    'label' => 'Care of disabled',
                ],
                4 => [
                    'key' => 'general_housework',
                    'label' => 'General housework',
                ],
                5 => [
                    'key' => 'cooking',
                    'label' => 'Cooking',
                ],
                6 => [
                    'key' => 'language_abilities',
                    'label' => 'Language abilities (spoken)',
                ],
                7 => [
                    'key' => 'other_skills',
                    'label' => 'Other skills, if any',
                ],
            ],
            'regex_patterns' => [
                'willingness' => '/(YES|NO|Yes|No)/i',
                'experience' => '/(YES|NO|Yes|No)/i',
                'years' => '/(\d+)/',
                'rating' => '/\b([1-5]|N\.?A\.?)\b/i',
            ],
        ];
    }

    // Pattern 2: Format tanpa numbering di Areas of Work
    private static function pattern2()
    {
        return [
            'table_markers' => [
                'start' => '/S\/No\s+Areas of Work\s+Willingness\s+Yes\/No\s+Experience\s+Yes\/No/i',
                'end' => '/Interviewed by overseas training centre/i',
            ],
            'headers' => [
                'sno' => 'S/No',
                'areas_of_work' => 'Areas of Work',
                'willingness' => 'Willingness Yes/No',
                'experience' => 'Experience Yes/No',
                'years' => 'If yes, state the no. of years',
                'assessment' => 'Assessment/Observation',
            ],
            'areas_of_work' => [
                [
                    'key' => 'care_of_infants',
                    'label' => 'Care of infants/children',
                ],
                [
                    'key' => 'care_of_elderly',
                    'label' => 'Care elderly',
                ],
                [
                    'key' => 'care_of_disabled',
                    'label' => 'Care of disabled',
                ],
                [
                    'key' => 'general_housework',
                    'label' => 'General housework',
                ],
                [
                    'key' => 'cooking',
                    'label' => 'Cooking',
                ],
                [
                    'key' => 'language_abilities',
                    'label' => 'Language abilities (spoken)',
                ],
                [
                    'key' => 'other_skills',
                    'label' => 'Other skill, if any',
                ],
            ],
            'regex_patterns' => [
                'willingness' => '/(YES|NO|Yes|No)/i',
                'experience' => '/(YES|NO|Yes|No)/i',
                'years' => '/(\d+)/',
                'rating' => '/\b([1-5]|N\.?A\.?)\b/i',
            ],
        ];
    }

    // Pattern 3: Format dengan "number of years (optional)"
    private static function pattern3()
    {
        return [
            'table_markers' => [
                'start' => '/S\/No\s+Areas of Work\s+Willingness\s+Yes\/No\s+Experience\s+Yes\/No/i',
                'end' => '/Interviewed by overseas training centre/i',
            ],
            'headers' => [
                'sno' => 'S/No',
                'areas_of_work' => 'Areas of Work',
                'willingness' => 'Willingness Yes/No',
                'experience' => 'Experience Yes/No',
                'years' => 'If yes, state the number of years (optional)',
                'assessment' => 'Assessment/Observation',
            ],
            'areas_of_work' => [
                1 => [
                    'key' => 'care_of_infants',
                    'label' => 'Care of infants/children',
                ],
                2 => [
                    'key' => 'care_of_elderly',
                    'label' => 'Care of elderly',
                ],
                3 => [
                    'key' => 'care_of_disabled',
                    'label' => 'Care of disabled',
                ],
                4 => [
                    'key' => 'general_housework',
                    'label' => 'General housework',
                ],
                5 => [
                    'key' => 'cooking',
                    'label' => 'Cooking',
                ],
                6 => [
                    'key' => 'language_abilities',
                    'label' => 'Language abilities (spoken)',
                ],
                7 => [
                    'key' => 'other_skills',
                    'label' => 'Other skills, if any',
                ],
            ],
            'regex_patterns' => [
                'willingness' => '/(YES|NO|Yes|No)/i',
                'experience' => '/(YES|NO|Yes|No)/i',
                'years' => '/(\d+)/',
                'rating' => '/\b([1-5]|N\.?A\.?)\b/i',
            ],
        ];
    }

    // Pattern 4: Format tanpa spasi (compressed)
    private static function pattern4()
    {
        return [
            'table_markers' => [
                'start' => '/S\/No\s*AreasofWork\s*Willingness\s*Yes\/No\s*Experience\s*Yes\/No/i',
                'end' => '/Interviewedbyoverseastrainingcentre/i',
            ],
            'headers' => [
                'sno' => 'S/No',
                'areas_of_work' => 'AreasofWork',
                'willingness' => 'Willingness Yes/No',
                'experience' => 'Experience Yes/No',
                'years' => 'Ifyes,statetheno.ofyears',
                'assessment' => 'Assessment/Observation',
            ],
            'areas_of_work' => [
                [
                    'key' => 'care_of_infants',
                    'label' => 'Careofinfants/children',
                ],
                [
                    'key' => 'care_of_elderly',
                    'label' => 'Careofelderly',
                ],
                [
                    'key' => 'care_of_disabled',
                    'label' => 'Careofdisabled',
                ],
                [
                    'key' => 'general_housework',
                    'label' => 'Generalhousework',
                ],
                [
                    'key' => 'cooking',
                    'label' => 'Cooking',
                ],
                [
                    'key' => 'language_abilities',
                    'label' => 'Languageabilities(spoken)',
                ],
                [
                    'key' => 'other_skills',
                    'label' => 'Otherskills,ifany',
                ],
            ],
            'regex_patterns' => [
                'willingness' => '/(YES|NO|Yes|No)/i',
                'experience' => '/(YES|NO|Yes|No)/i',
                'years' => '/(\d+)/',
                'rating' => '/\b([1-5]|N\.?A\.?)\b/i',
            ],
        ];
    }
}
