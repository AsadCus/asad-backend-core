<?php

namespace App\Http\Controllers;

use App\Models\AppearanceSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppearanceController extends Controller
{
    public function edit()
    {
        return Inertia::render('settings/appearance', [
            'settings' => AppearanceSetting::first(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'auth_bg' => 'required|string',
            'auth_card_bg' => 'required|string',
            'primary_color' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/i', $value)) {
                        $fail('The ' . $attribute . ' must be a valid hex color (e.g., #fff or #ffffff).');
                    }
                },
            ],
            'border_radius' => 'required|string|in:0rem,0.25rem,0.5rem,0.75rem,1rem',
        ]);

        $setting = AppearanceSetting::first();

        if ($setting === null) {
            $setting = AppearanceSetting::create($validated);
            return back()->with('success', 'Appearance created.');
        }

        $setting->update($validated);
        return back()->with('success', 'Appearance updated.');
    }
}
