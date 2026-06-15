<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserNotification(User $user, array $notif = [], array $pivot = []): UserNotification
    {
        $notification = Notification::create(array_merge([
            'title' => 'New General Enquiry',
            'message' => 'A new enquiry arrived.',
            'type' => 'info',
            'link' => '/general-enquiries/1',
            'exclusive' => false,
        ], $notif));

        return UserNotification::create(array_merge([
            'user_id' => $user->id,
            'notification_id' => $notification->id,
            'is_read' => false,
        ], $pivot));
    }

    public function test_index_renders_without_error(): void
    {
        $user = User::factory()->create();
        $this->makeUserNotification($user);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk();
    }

    public function test_index_paginates_notifications(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            $this->makeUserNotification($user);
        }

        // Page 1: 20 items, more pages available.
        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('notifications/index')
                    ->where('notifications.current_page', 1)
                    ->where('notifications.last_page', 2)
                    ->where('notifications.total', 25)
                    ->has('notifications.data', 20)
                    // shared bell payload stays capped at the latest 10
                    ->has('auth.notifications', 10)
            );

        // Page 2: the remaining 5.
        $this->actingAs($user)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('notifications.current_page', 2)
                    ->has('notifications.data', 5)
            );
    }

    public function test_shared_unread_count_matches_database(): void
    {
        $user = User::factory()->create();
        $this->makeUserNotification($user); // unread
        $this->makeUserNotification($user); // unread
        $this->makeUserNotification($user, [], ['is_read' => true]); // read

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('auth.notifications_unread_count', 2)
                    // popup payload is trimmed to the latest 10
                    ->has('auth.notifications', 3)
            );
    }

    public function test_mark_as_read_redirects_and_marks_read(): void
    {
        $user = User::factory()->create();
        $userNotification = $this->makeUserNotification($user);

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->put(route('notifications.read', $userNotification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertTrue((bool) $userNotification->fresh()->is_read);
    }

    public function test_mark_all_as_read_redirects_and_marks_all_read(): void
    {
        $user = User::factory()->create();
        $this->makeUserNotification($user);
        $this->makeUserNotification($user);

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->put(route('notifications.readAll'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, UserNotification::where('user_id', $user->id)->where('is_read', false)->count());
    }

    public function test_destroy_redirects_and_soft_deletes(): void
    {
        $user = User::factory()->create();
        $userNotification = $this->makeUserNotification($user);

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->delete(route('notifications.destroy', $userNotification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertSoftDeleted('user_notifications', ['id' => $userNotification->id]);
    }

    public function test_handle_action_redirects_to_notification_link(): void
    {
        $user = User::factory()->create();
        $userNotification = $this->makeUserNotification($user, ['link' => '/general-enquiries/42']);

        $this->actingAs($user)
            ->post(route('notifications.action', $userNotification->id))
            ->assertRedirect('/general-enquiries/42');
    }
}
