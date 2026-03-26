<?php

namespace Tests\Feature;

use App\Models\NumberingFormat;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NumberingServiceTemplateFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_next_number_supports_custom_template_token_order(): void
    {
        $format = NumberingFormat::query()->create([
            'model_key' => 'order',
            'name' => 'KTG%I%-%YYYY%',
            'prefix' => null,
            'separator' => '-',
            'include_year' => true,
            'year_format' => 'Y',
            'increment_padding' => 1,
            'increment_start' => 1,
            'increment_scope' => 'format',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = app(NumberingService::class);

        $this->assertSame(
            'KTG1-'.now()->format('Y'),
            $service->generateNextNumber('order', $format->id),
        );

        $this->assertSame(
            'KTG2-'.now()->format('Y'),
            $service->generateNextNumber('order', $format->id),
        );
    }

    public function test_validate_provided_number_supports_template_tokens(): void
    {
        NumberingFormat::query()->create([
            'model_key' => 'order',
            'name' => 'KGT-%DD%-%MM%-%YY%-%I%',
            'prefix' => null,
            'separator' => '-',
            'include_year' => true,
            'year_format' => 'y',
            'increment_padding' => 3,
            'increment_start' => 1,
            'increment_scope' => 'format',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = app(NumberingService::class);

        $validNumber = sprintf(
            'KGT-%s-%s-%s-001',
            now()->format('d'),
            now()->format('m'),
            now()->format('y'),
        );

        $service->validateProvidedNumber('order', $validNumber);

        $this->expectException(ValidationException::class);
        $service->validateProvidedNumber('order', 'KGT-INVALID-NUMBER');
    }
}
