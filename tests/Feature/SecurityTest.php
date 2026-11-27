<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

        $this->actingAs($admin)->from('/user-management')->patch("/user-management/{$admin->id}/status")
            ->assertRedirect('/user-management')->assertSessionHas('error', 'You cannot change your own status.');
        $this->actingAs($admin)->from('/user-management')->delete("/user-management/{$admin->id}")
            ->assertRedirect('/user-management')->assertSessionHas('error', 'You cannot delete your own account.');
        $this->assertSame('active', $admin->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_the_last_active_instructor_or_admin_cannot_be_removed(): void
    {
        $instructor = User::factory()->instructor()->create();
        $admin = User::factory()->admin()->create();

        // the instructor suspends the admin: allowed because the instructor still remains
        $this->actingAs($instructor)->patch("/user-management/{$admin->id}/status")->assertSessionHas('status', 'User suspended.');
        $this->assertSame('suspended', $admin->fresh()->status);

        // the suspended admin is signed out on the next request and can no longer manage anyone
        $this->actingAs($admin->fresh())->patch("/user-management/{$instructor->id}/status")->assertRedirect('/login');
        $this->assertSame('active', $instructor->fresh()->status);

        // the instructor is now the last active manager and cannot demote or remove their own account
        $this->actingAs($instructor)->put("/user-management/{$instructor->id}", ['name' => $instructor->name, 'email' => $instructor->email, 'role' => 'user', 'status' => 'active'])
            ->assertSessionHasErrors('role');
        $this->actingAs($instructor)->delete("/user-management/{$instructor->id}")->assertSessionHas('error');
        $this->assertSame('instructor', $instructor->fresh()->role);
    }

    public function test_admin_cannot_assign_the_instructor_role_or_change_instructor_accounts(): void
    {
        $instructor = User::factory()->instructor()->create(['password' => 'Original@123']);
        $admin = User::factory()->admin()->create();
        $clerk = User::factory()->create(['role' => 'inventory_clerk', 'status' => 'active']);

        // promoting someone (or creating someone) as instructor would hand the admin the evaluation metrics
        $this->actingAs($admin)->put("/user-management/{$clerk->id}", ['name' => $clerk->name, 'email' => $clerk->email, 'role' => 'instructor', 'status' => 'active'])
            ->assertSessionHasErrors('role');
        $this->assertSame('inventory_clerk', $clerk->fresh()->role);
        $this->actingAs($admin)->post('/user-management', ['name' => 'Fake Instructor', 'email' => 'fake@example.com', 'role' => 'instructor', 'status' => 'active', 'password' => 'Takeover@123'])
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'fake@example.com']);

        // taking over, suspending or deleting an existing instructor account is refused
        $this->actingAs($admin)->put("/user-management/{$instructor->id}", ['name' => $instructor->name, 'email' => $instructor->email, 'role' => 'instructor', 'status' => 'active', 'password' => 'Takeover@123'])
            ->assertSessionHas('error', 'Only an instructor can change an instructor account.');
        $this->assertTrue(Hash::check('Original@123', $instructor->fresh()->password));
        $this->actingAs($admin)->patch("/user-management/{$instructor->id}/status")->assertSessionHas('error');
        $this->assertSame('active', $instructor->fresh()->status);
        $this->actingAs($admin)->delete("/user-management/{$instructor->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $instructor->id]);

        // the admin's page does not even offer the instructor role
        $this->actingAs($admin)->get('/user-management')->assertInertia(fn ($page) => $page
            ->where('roles', fn ($roles) => ! collect($roles)->pluck('value')->contains('instructor'))
            ->where('protectedRoles', ['instructor']));
    }

    public function test_managers_can_set_a_password_when_creating_staff(): void
    {
        $instructor = User::factory()->instructor()->create();

        $this->actingAs($instructor)->post('/user-management', [
            'name' => 'New Clerk', 'email' => 'clerk.new@example.com', 'role' => 'inventory_clerk', 'status' => 'active', 'password' => 'Clerk@12345',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', ['email' => 'clerk.new@example.com', 'password' => 'Clerk@12345'])->assertRedirect('/dashboard');
    }

    public function test_dashboard_only_sends_figures_for_the_roles_sections(): void
    {
        $clerk = User::factory()->create(['role' => 'inventory_clerk', 'status' => 'active']);
        $sales = User::factory()->create(['role' => 'sales_personnel', 'status' => 'active']);
        $product = Product::create(['category_id' => Category::create(['name' => 'Parts'])->id, 'name' => 'Chain', 'sku' => 'CH-1', 'selling_price' => 500, 'stock_quantity' => 3, 'reorder_threshold' => 5]);
        $sale = Sale::create(['customer_id' => $sales->id, 'customer_name' => 'Buyer', 'transaction_number' => 'TX-D-1', 'transaction_date' => now(), 'payment_method' => 'Cash', 'total_amount' => 500]);
        $sale->items()->create(['product_id' => $product->id, 'product_name' => 'Chain', 'sku' => 'CH-1', 'quantity' => 1, 'unit_price' => 500, 'line_total' => 500]);

        $this->actingAs($clerk)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('dashboard.total_sales', 0)->where('dashboard.sales_trend', [])->where('dashboard.best_sellers', [])
            ->where('dashboard.low_stock', 1));
        $this->actingAs($sales)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('dashboard.total_sales', 500)->where('dashboard.products', 0)->where('dashboard.low_stock', 0));
    }

    public function test_reset_password_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/reset-password', ['token' => 'bad', 'email' => 'a@example.com', 'password' => 'Strong@12345', 'password_confirmation' => 'Strong@12345']);
        }
        $this->post('/reset-password', ['token' => 'bad', 'email' => 'a@example.com', 'password' => 'Strong@12345', 'password_confirmation' => 'Strong@12345'])
            ->assertStatus(429);
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
        $this->assertTrue(Hash::check('Original@123', $user->fresh()->password));
    }

    public function test_changing_a_password_on_user_update_requires_the_current_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->salesPersonnel()->create(['password' => 'Original@123']);
        $base = ['name' => $user->name, 'email' => $user->email, 'role' => 'sales_personnel', 'status' => 'active', 'password' => 'Changed@123'];

        $this->actingAs($admin)->put("/user-management/{$user->id}", $base)->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->put("/user-management/{$user->id}", $base + ['current_password' => 'Wrong@123'])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Original@123', $user->fresh()->password));

        $this->actingAs($admin)->put("/user-management/{$user->id}", $base + ['current_password' => 'Original@123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Changed@123', $user->fresh()->password));
    }

    public function test_password_is_required_when_adding_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/user-management', [
            'name' => 'No Password', 'email' => 'nopass@example.com', 'role' => 'user', 'status' => 'active',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'nopass@example.com']);
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

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        Notification::assertCount(1);
        $this->assertSame(session('status'), $known->getSession()->get('status'));
        $unknown->assertSessionHas('status');
        $this->assertStringContainsString('spam', session('status'));
    }

    public function test_reset_email_is_branded_formal_and_escapes_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Juan <b>Dela</b> Cruz', 'email' => 'juan@example.com']);
        $mail = (new ResetPasswordNotification('secret-token'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Reset your One Prompt Away password', $mail->subject);
        $this->assertStringContainsString('Dear Juan &lt;b&gt;Dela&lt;/b&gt; Cruz,', $html);
        $this->assertStringNotContainsString('<b>Dela</b>', $html);
        $this->assertMatchesRegularExpression('#/reset-password/[A-Za-z0-9_-]+"#', $html);
        $this->assertStringNotContainsString('?email=', $html);
        $this->assertStringNotContainsString('juan%40example.com', $html);
        $this->assertStringContainsString('expires in <strong>60 minutes</strong>', $html);
        $this->assertMatchesRegularExpression('#<img src="(cid:|data:image/png;base64,|[^"]*images/logo\.png)#', $html);
        $this->assertStringContainsString('Sincerely,', $html);
        $this->assertFileExists(public_path('images/logo.png'));
    }

    /** Requests a reset for the user and returns the path of the link that was emailed. */
    private function emailedResetPath(User $user): string
    {
        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email]);
        $path = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user, &$path) {
            preg_match('#href="https?://[^/"]+(/reset-password/[^"]+)"#', (string) $notification->toMail($user)->render(), $m);
            $path = $m[1] ?? null;

            return $path !== null;
        });

        return $path;
    }

    public function test_reset_link_has_no_email_parameter_and_resets_the_password(): void
    {
        $user = User::factory()->create(['email' => 'maria@example.com', 'password' => 'Original@123']);
        $path = $this->emailedResetPath($user);

        $this->assertStringNotContainsString('email', $path);
        $code = substr($path, strlen('/reset-password/'));
        $this->get($path)->assertInertia(fn ($page) => $page
            ->component('ResetPassword')->where('email', 'maria@example.com')->where('invalid', false));

        $this->post('/reset-password', ['token' => $code, 'password' => 'Brand@New123', 'password_confirmation' => 'Brand@New123'])
            ->assertRedirect('/login');
        $this->post('/login', ['email' => 'maria@example.com', 'password' => 'Brand@New123'])->assertRedirect('/shop');
    }

    public function test_a_modified_reset_link_is_invalid_and_the_browser_cannot_choose_the_email(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com', 'password' => 'Victim@1234']);
        $attacker = User::factory()->create(['email' => 'attacker@example.com', 'password' => 'Attacker@123']);
        $code = substr($this->emailedResetPath($attacker), strlen('/reset-password/'));

        // one changed character, a cut-off code, or a made-up code: the page shows "Invalid Link" and no email
        $changed = substr($code, 0, 20).($code[20] === 'A' ? 'B' : 'A').substr($code, 21);
        foreach ([$changed, substr($code, 0, -5), 'not-a-real-link'] as $bad) {
            $this->get('/reset-password/'.$bad)->assertInertia(fn ($page) => $page
                ->where('invalid', true)->where('email', '')->where('token', ''));
            $this->post('/reset-password', ['token' => $bad, 'password' => 'Hacked@1234', 'password_confirmation' => 'Hacked@1234'])
                ->assertSessionHasErrors('email');
        }

        // a valid link plus a different "email" field only ever resets the account the link was sent to
        $this->post('/reset-password', ['token' => $code, 'email' => 'victim@example.com', 'password' => 'Hacked@1234', 'password_confirmation' => 'Hacked@1234'])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check('Victim@1234', $victim->fresh()->password));
        $this->assertTrue(Hash::check('Hacked@1234', $attacker->fresh()->password));
    }

    public function test_search_input_treats_like_wildcards_literally(): void
    {
        $clerk = User::factory()->inventoryClerk()->create();

        $this->actingAs($clerk)->get('/inventory?search=%25_%25')->assertOk();
    }
}
