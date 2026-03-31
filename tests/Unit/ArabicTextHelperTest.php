<?php

namespace Tests\Unit;

use App\Helpers\ArabicTextHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArabicTextHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_shape_for_pdf_keeps_empty_value(): void
    {
        $this->assertSame('', ArabicTextHelper::shapeForPdf(''));
    }

    public function test_shape_for_pdf_keeps_non_arabic_text_unchanged(): void
    {
        $text = 'Customer';

        $this->assertSame($text, ArabicTextHelper::shapeForPdf($text));
    }

    public function test_shape_for_pdf_shapes_arabic_text_for_pdf(): void
    {
        $text = 'كوستومير';

        $this->assertNotSame($text, ArabicTextHelper::shapeForPdf($text));
    }
}
