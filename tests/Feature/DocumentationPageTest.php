<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_documentation_page(): void
    {
        $response = $this->get(route('documentations.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_documentation_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documentations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('documentations/v1/index')
                ->has('documentation', fn (Assert $documentation) => $documentation
                    ->where('manual.title', 'Documentation V1 - Travel Management System Manual')
                    ->hasAll([
                        'introduction',
                        'menuGroups',
                        'coreWorkflows',
                        'howToGuides',
                        'commonStatuses',
                        'tips',
                    ])
                    ->has('menuGroups.0', fn (Assert $group) => $group
                        ->hasAll([
                            'menu',
                            'route_path',
                            'module',
                            'purpose',
                            'features',
                            'how_to',
                        ])
                        ->etc()
                    )
                    ->has('coreWorkflows.0', fn (Assert $workflow) => $workflow
                        ->hasAll(['name', 'goal', 'steps'])
                    )
                    ->has('howToGuides.0', fn (Assert $guide) => $guide
                        ->hasAll(['task', 'steps'])
                    )
                    ->has('commonStatuses.0', fn (Assert $status) => $status
                        ->hasAll(['topic', 'notes'])
                    )
                    ->etc()
                )
            );
    }

    public function test_authenticated_user_can_open_documentation_v2_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documentations.index', ['version' => 'v2']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('documentations/v2/index')
                ->has('documentation', fn (Assert $documentation) => $documentation
                    ->where('manual.title', 'Documentation V2 - Travel Management System Manual')
                    ->etc()
                )
            );
    }

    public function test_authenticated_user_can_open_documentation_v3_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documentations.index', ['version' => 'v3']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('documentations/v3/index')
                ->has('documentation', fn (Assert $documentation) => $documentation
                    ->where('manual.title', 'Documentation V3 - Travel Management System Manual')
                    ->hasAll([
                        'introduction',
                        'menuGroups',
                        'coreWorkflows',
                        'howToGuides',
                        'commonStatuses',
                        'tips',
                    ])
                    ->has('menuGroups.0', fn (Assert $group) => $group
                        ->hasAll([
                            'menu',
                            'route_path',
                            'module',
                            'purpose',
                            'features',
                            'how_to',
                        ])
                        ->etc()
                    )
                    ->etc()
                )
            );
    }
}
