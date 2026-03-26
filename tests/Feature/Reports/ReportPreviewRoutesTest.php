<?php

namespace Tests\Feature\Reports;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReportPreviewRoutesTest extends TestCase
{
    public function test_preview_routes_require_authentication(): void
    {
        $previewRoutes = [
            ['name' => 'invoice.preview', 'parameters' => ['id' => 1]],
            ['name' => 'quotation.preview', 'parameters' => ['id' => 1]],
            ['name' => 'receipt.preview', 'parameters' => ['id' => 1]],
            ['name' => 'agreement.preview', 'parameters' => ['quotation' => 1]],
            ['name' => 'sales.preview', 'parameters' => ['sale' => 1]],
            ['name' => 'packages.preview', 'parameters' => ['id' => 1]],
        ];

        foreach ($previewRoutes as $previewRoute) {
            if (! Route::has($previewRoute['name'])) {
                continue;
            }

            $this->get(route($previewRoute['name'], $previewRoute['parameters']))
                ->assertRedirect(route('login'));
        }
    }
}
