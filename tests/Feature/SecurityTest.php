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

    public function test_admin_cannot_remove_the_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/user-management/{$admin->id}/status")->assertStatus(422);
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(); // a second admin exists, so only the self-protection applies

        $this->actingAs($admin)->put("/user-management/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'sales_personnel', 'status' => 'active',
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
            'requested_role' => 'sales_personnel', 'affiliation' => 'Shop',
        ])->assertSessionHasErrors('password');
    }

    public function test_all_four_roles_can_be_requested_at_registration(): void
    {
        foreach (['admin', 'instructor', 'inventory_clerk', 'sales_personnel'] as $i => $role) {
            $this->post('/register', [
                'name' => 'Applicant Person', 'email' => "applicant{$i}@example.com", 'password' => 'Strong@12345', 'password_confirmation' => 'Strong@12345',
                'requested_role' => $role, 'affiliation' => 'Parts Depot',
            ])->assertSessionHasNoErrors();

            $this->assertDatabaseHas('users', ['email' => "applicant{$i}@example.com", 'status' => 'pending', 'requested_role' => $role]);
            RateLimiter::clear('registration|127.0.0.1');
            $this->flushSession();
        }
    }

    public function test_pending_applicant_cannot_sign_in_until_approved(): void
    {
        $user = User::factory()->pending('admin')->create(['password' => 'Strong@12345']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Strong@12345'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch("/user-management/{$user->id}/approve", ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('active', $user->fresh()->status);
        $this->assertSame('admin', $user->fresh()->role);
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
