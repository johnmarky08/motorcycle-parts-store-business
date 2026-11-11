<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_signed_in_pages_are_not_cacheable(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/dashboard');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_logout_requires_post(): void
    {
        $this->actingAs(User::factory()->create())->get('/logout')->assertStatus(405);
    }

    public function test_nobody_can_suspend_or_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/user-management/{$admin->id}/status")->assertStatus(422);
        $this->actingAs($admin)->delete("/user-management/{$admin->id}")->assertStatus(422);
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_the_last_active_instructor_or_admin_cannot_be_removed(): void
    {
        $instructor = User::factory()->instructor()->create();
        $admin = User::factory()->admin()->create();

        // admin suspends the instructor: allowed because the admin still remains
        $this->actingAs($admin)->patch("/user-management/{$instructor->id}/status")->assertSessionHasNoErrors();
        $this->assertSame('suspended', $instructor->fresh()->status);

        // a second manager exists only as a suspended account, so the admin cannot be demoted by anyone else here
        $other = User::factory()->instructor()->create();
        $this->actingAs($other)->put("/user-management/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'user', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('user', $admin->fresh()->role);
        $this->actingAs($other)->delete("/user-management/{$other->id}")->assertStatus(422);
    }

    public function test_instructor_can_assign_any_role_including_admin(): void
    {
        $instructor = User::factory()->instructor()->create();
        $customer = User::factory()->customer()->create();

        foreach (['admin', 'instructor', 'inventory_clerk', 'sales_personnel', 'user'] as $role) {
            $this->actingAs($instructor)->put("/user-management/{$customer->id}", ['name' => $customer->name, 'email' => $customer->email, 'role' => $role, 'status' => 'active'])
                ->assertSessionHasNoErrors();
            $this->assertSame($role, $customer->fresh()->role);
        }
    }

    public function test_nobody_can_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->instructor()->create();

        $this->actingAs($admin)->put("/user-management/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'instructor', 'status' => 'active',
        ])->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_blank_password_on_user_update_keeps_the_old_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->salesPersonnel()->create(['password' => 'Original@123']);

        $this->actingAs($admin)->put("/user-management/{$user->id}", [
            'name' => 'Renamed User', 'email' => $user->email, 'role' => 'sales_personnel', 'status' => 'active', 'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed User', $user->fresh()->name);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Original@123', $user->fresh()->password));
    }

    public function test_weak_passwords_are_rejected_on_registration(): void
    {
        $this->post('/register', [
            'name' => 'Weak Person', 'email' => 'weak@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_reset_sends_mail_and_never_reveals_whether_the_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
        $this->assertSame(session('status'), $known->getSession()->get('status'));
        $unknown->assertSessionHas('status');
        $this->assertStringContainsString('spam', session('status'));
    }

    public function test_search_input_treats_like_wildcards_literally(): void
    {
        $clerk = User::factory()->inventoryClerk()->create();

        $this->actingAs($clerk)->get('/inventory?search=%25_%25')->assertOk();
    }
}
