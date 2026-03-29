<?php

namespace Tests\Feature;

use App\Models\NumberingFormat;
use App\Models\Package;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NumberingServiceTemplateFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_formats_bootstraps_legacy_default_when_model_has_no_formats(): void
    {
        $service = app(NumberingService::class);

        $formats = $service->listFormats('receipt');

        $this->assertCount(1, $formats);

        $format = $formats->first();
        $this->assertInstanceOf(NumberingFormat::class, $format);
        $this->assertSame('receipt', (string) $format->model_key);
        $this->assertSame('R-%YYYY%-%I%', (string) $format->name);
        $this->assertTrue((bool) $format->is_default);
        $this->assertTrue((bool) $format->is_active);
    }

    public function test_generate_next_number_supports_custom_template_token_order(): void
    {
        $format = NumberingFormat::query()->create([
            'model_key' => 'order',
            'name' => 'KTG%I%-%YYYY%',
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
            $service->generateNextNumberWithMode('order', $format->id, 'format'),
        );

        $this->assertSame(
            'KTG2-'.now()->format('Y'),
            $service->generateNextNumberWithMode('order', $format->id, 'format'),
        );
    }

    public function test_validate_provided_number_supports_template_tokens(): void
    {
        NumberingFormat::query()->create([
            'model_key' => 'order',
            'name' => 'KGT-%DD%-%MM%-%YY%-%I%',
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

        $service->validateProvidedNumber('order', $validNumber, null, null, 'format');

        $this->expectException(ValidationException::class);
        $service->validateProvidedNumber('order', 'KGT-INVALID-NUMBER', null, null, 'format');
    }

    public function test_ensure_number_with_provided_value_advances_next_suggestion_increment(): void
    {
        $format = NumberingFormat::query()->create([
            'model_key' => 'package',
            'name' => 'KTG-%YYYY%-%I%',
            'increment_padding' => 3,
            'increment_start' => 1,
            'increment_scope' => 'format',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = app(NumberingService::class);
        $year = now()->format('Y');

        $number = "KTG-{$year}-001";
        $this->assertSame(
            $number,
            $service->ensureNumber('package', $number, null, $format->id, 'format'),
        );

        $suggestion = $service->suggestNextNumberWithMode('package', $format->id, 'format');

        $this->assertSame($format->id, $suggestion['format_id']);
        $this->assertSame("KTG-{$year}-002", $suggestion['number']);
        $this->assertSame(2, $suggestion['next_increment']);
    }

    public function test_simple_mode_increments_last_numeric_suffix_and_follows_manual_override(): void
    {
        $service = app(NumberingService::class);

        $this->assertSame(
            'KTG1-10',
            $service->ensureNumber('package', 'KTG1-10'),
        );

        $nextAfterFirst = $service->suggestNextNumber('package');
        $this->assertSame('KTG1-11', $nextAfterFirst['number']);

        $this->assertSame(
            'KTG1A-L-25',
            $service->ensureNumber('package', 'KTG1A-L-25'),
        );

        $nextAfterOverride = $service->suggestNextNumber('package');
        $this->assertSame('KTG1A-L-26', $nextAfterOverride['number']);
    }

    public function test_simple_mode_requires_number_to_end_with_numeric_suffix(): void
    {
        $service = app(NumberingService::class);

        $this->expectException(ValidationException::class);
        $service->ensureNumber('package', 'KTG1-ABC');
    }

    public function test_simple_mode_skips_existing_numbers_when_suggesting_next_number(): void
    {
        Package::create([
            'package_number' => 'KTG1-11',
            'name' => 'Existing Package',
            'status' => 'open',
            'price_single' => 1000,
            'price_double' => 900,
            'total_seats' => 1,
            'seats_left' => 1,
        ]);

        $service = app(NumberingService::class);
        $service->updateSimpleLatestNumber('package', 'KTG1-10');

        $suggestion = $service->suggestNextNumber('package');

        $this->assertSame('KTG1-12', $suggestion['number']);
    }
}
