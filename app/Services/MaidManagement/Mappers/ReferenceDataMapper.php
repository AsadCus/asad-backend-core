<?php

namespace App\Services\MaidManagement\Mappers;

use Carbon\Carbon;

/**
 * Maps database models to IDs
 * Responsibility: Handle fuzzy matching for countries, religions, education levels
 */
class ReferenceDataMapper
{
    /**
     * Map nationality string to country_id
     */
    public function mapCountryId(?string $nationality): ?string
    {
        if (empty($nationality)) {
            return null;
        }

        $nationalityInput = strtolower(trim($nationality));

        // Try exact match first (adjective or name)
        $country = \App\Models\Country::whereRaw('LOWER(adjective) = ?', [$nationalityInput])
            ->orWhereRaw('LOWER(name) = ?', [$nationalityInput])
            ->first();

        if ($country) {
            return (string) $country->id;
        }

        // Common nationality variations
        $variations = [
            'indonesian' => ['indonesia', 'indonesian', 'indo'],
            'filipino' => ['philippines', 'filipino', 'filipina', 'pilipinas'],
            'myanmar' => ['myanmar', 'burmese', 'burma'],
            'indian' => ['india', 'indian'],
            'bangladeshi' => ['bangladesh', 'bangladeshi'],
            'sri lankan' => ['sri lanka', 'sri lankan', 'srilanka'],
        ];

        // Try fuzzy match
        foreach ($variations as $standard => $aliases) {
            foreach ($aliases as $alias) {
                if (stripos($nationalityInput, $alias) !== false) {
                    $country = \App\Models\Country::whereRaw('LOWER(adjective) LIKE ?', ['%'.$standard.'%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%'.$standard.'%'])
                        ->first();

                    if ($country) {
                        return (string) $country->id;
                    }
                }
            }
        }

        // Fallback to partial match
        $country = \App\Models\Country::where('adjective', 'like', '%'.$nationalityInput.'%')
            ->orWhere('name', 'like', '%'.$nationalityInput.'%')
            ->first();

        if ($country) {
            return (string) $country->id;
        }

        return null;
    }

    /**
     * Map country from location (place of birth) with common Indonesian cities
     */
    public function mapCountryIdFromLocation(?string $location): ?string
    {
        if (empty($location)) {
            return null;
        }

        $locationInput = strtolower(trim($location));

        // Common Indonesian cities/regions - auto-map to Indonesia
        $indonesianLocations = [
            'jakarta', 'surabaya', 'bandung', 'medan', 'semarang', 'makassar',
            'palembang', 'tangerang', 'depok', 'bekasi', 'bogor',
            'karawang', 'sumedang', 'cianjur', 'tasikmalaya', 'cirebon',
            'garut', 'purwakarta', 'subang', 'indramayu', 'majalengka',
            'yogyakarta', 'solo', 'malang', 'bali', 'denpasar',
            'lombok', 'mataram', 'banyuwangi', 'jember', 'probolinggo',
            'java', 'jawa', 'sumatra', 'kalimantan', 'sulawesi',
            'nusa tenggara', 'ntb', 'ntt',
        ];

        foreach ($indonesianLocations as $city) {
            if (stripos($locationInput, $city) !== false) {
                $country = \App\Models\Country::whereRaw('LOWER(name) = ?', ['indonesia'])->first();
                if ($country) {
                    return (string) $country->id;
                }
            }
        }

        // Try direct match with country name
        $country = \App\Models\Country::whereRaw('LOWER(name) LIKE ?', ['%'.$locationInput.'%'])
            ->orWhereRaw('LOWER(adjective) LIKE ?', ['%'.$locationInput.'%'])
            ->first();

        if ($country) {
            return (string) $country->id;
        }

        return null;
    }

    /**
     * Map religion string to religion_id with fuzzy matching
     */
    public function mapReligionId(?string $religion): ?string
    {
        if (empty($religion)) {
            return null;
        }

        $religionInput = strtolower(trim($religion));

        // Try exact match first
        $model = \App\Models\Religion::whereRaw('LOWER(name) = ?', [$religionInput])->first();

        if ($model) {
            return (string) $model->id;
        }

        // Try fuzzy match with common variations
        $mappings = [
            'islam' => ['islam', 'muslim', 'moslem'],
            'christian' => ['christian', 'catholic', 'protestant'],
            'buddhist' => ['buddha', 'buddhist', 'buddhism'],
            'hindu' => ['hindu', 'hinduism'],
        ];

        $model = $this->fuzzyMatchReligion($religionInput, $mappings);

        if ($model) {
            return (string) $model->id;
        }

        return null;
    }

    /**
     * Map education string to education_level_id with fuzzy matching
     */
    public function mapEducationId(?string $education): ?string
    {
        if (empty($education)) {
            return null;
        }

        $educationInput = strtolower(trim($education));

        // Try exact match first
        $model = \App\Models\EducationLevel::whereRaw('LOWER(name) = ?', [$educationInput])->first();

        if ($model) {
            return (string) $model->id;
        }

        // Try fuzzy match with common variations
        $mappings = [
            'junior high school' => ['junior high', 'middle school', 'smp', 'junior'],
            'high school' => ['senior high', 'high school', 'sma', 'secondary', 'senior'],
        ];

        $model = $this->fuzzyMatchEducation($educationInput, $mappings);

        if ($model) {
            return (string) $model->id;
        }

        return null;
    }

    /**
     * Normalize marital status with common variations
     */
    public function normalizeMaritalStatus(?string $maritalStatus): string
    {
        if (empty($maritalStatus)) {
            return '';
        }

        $maritalInput = strtolower(trim($maritalStatus));

        $mappings = [
            'single' => ['single', 'unmarried', 'not married'],
            'widowed' => ['widowed', 'widow', 'widower'],
            'divorced' => ['divorced', 'divorce', 'separated'],
            'married' => ['married', 'wed'],
        ];

        foreach ($mappings as $standard => $variations) {
            foreach ($variations as $variant) {
                if (stripos($maritalInput, $variant) !== false) {
                    return ucfirst($standard);
                }
            }
        }

        return ucfirst($maritalInput);
    }

    /**
     * Format date to "dd MMMM yyyy"
     */
    public function formatDateOfBirth(?string $dob): ?string
    {
        if (empty($dob)) {
            return null;
        }

        try {
            // Clean input - remove extra spaces and trim
            $dob = trim($dob);
            // Normalize unicode dashes (en/em/etc) to '-'
            $dashVariants = [
                "\xE2\x80\x90",
                "\xE2\x80\x91",
                "\xE2\x80\x92",
                "\xE2\x80\x93",
                "\xE2\x80\x94",
                "\xE2\x80\x95",
            ];
            $dob = str_replace($dashVariants, '-', $dob);
            $dob = str_replace("\xC2\xAD", '', $dob);
            // Collapse any spaced separators to single '-'
            $dob = preg_replace('/\s*[\/-]\s*/', '-', $dob);
            // Also collapse multiple spaces
            $dob = preg_replace('/\s+/', ' ', $dob);

            // Try multiple date formats
            $formats = [
                'd/m/Y',    // 26/06/1992
                'd-m-Y',    // 26-06-1992
                'Y-m-d',    // 1992-06-26
                'd F Y',    // 26 June 1992
                'd M Y',    // 26 Jun 1992
                'd/m/y',    // 26/06/92
                'd-m-y',    // 26-06-92
            ];

            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $dob);
                    if ($date && $date->format($format) === $dob) {
                        return $date->format('d F Y');
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Fallback to Carbon::parse (after normalization)
            return Carbon::parse($dob)->format('d F Y');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fuzzyMatchReligion(string $input, array $mappings): ?\App\Models\Religion
    {
        foreach ($mappings as $dbName => $variations) {
            foreach ($variations as $variant) {
                if (stripos($input, $variant) !== false) {
                    $model = \App\Models\Religion::whereRaw('LOWER(name) = ?', [$dbName])->first();
                    if ($model) {
                        return $model;
                    }
                }
            }
        }

        // Last resort: partial match
        return \App\Models\Religion::whereRaw('LOWER(name) LIKE ?', ['%'.$input.'%'])->first();
    }

    private function fuzzyMatchEducation(string $input, array $mappings): ?\App\Models\EducationLevel
    {
        foreach ($mappings as $dbName => $variations) {
            foreach ($variations as $variant) {
                if (stripos($input, $variant) !== false) {
                    $model = \App\Models\EducationLevel::whereRaw('LOWER(name) = ?', [$dbName])->first();
                    if ($model) {
                        return $model;
                    }
                }
            }
        }

        // Last resort: partial match
        return \App\Models\EducationLevel::whereRaw('LOWER(name) LIKE ?', ['%'.$input.'%'])->first();
    }
}
