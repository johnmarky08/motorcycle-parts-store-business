<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_sign_in_and_reach_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'password' => 'Secret@Pass123',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secret@Pass123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_user_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended',
            'password' => 'Secret@Pass123',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secret@Pass123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_role_restricted_routes_are_enforced_server_side(): void
    {
        $user = User::factory()->create([
            'role' => 'sales_personnel',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/user-management')->assertForbidden();
        $this->actingAs($user)->get('/sales')->assertSuccessful();
    }

    public function test_authenticated_user_is_shared_to_inertia_pages(): void
    {
        $user = User::factory()->create([
            'name' => 'Angela Garcia',
            'role' => 'instructor',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', 'Angela Garcia')
                ->where('auth.user.role', 'instructor')
                ->where('auth.user.status', 'active'));
    }

    public function test_role_matrix_is_enforced_for_all_authenticated_roles(): void
    {
        $routes = [
            'admin' => ['/dashboard', '/user-management', '/prediction'],
            'instructor' => ['/dashboard', '/user-management', '/prediction', '/knowledge-analysis'],
            'inventory_clerk' => ['/dashboard', '/inventory'],
            'sales_personnel' => ['/dashboard', '/sales'],
        ];

        foreach ($routes as $role => $allowedRoutes) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            foreach (array_merge(...array_values($routes)) as $route) {
                $response = $this->actingAs($user)->get($route);

                if (in_array($route, $allowedRoutes, true)) {
                    $response->assertSuccessful();
                } else {
                    $response->assertForbidden();
                }
            }
        }
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_registration_creates_an_active_customer_and_signs_them_in(): void
    {
        $this->post('/register', [
            'name' => 'Juan Dela Cruz',
            'email' => 'Juan@Example.com',
            'password' => 'Strong@12345',
            'password_confirmation' => 'Strong@12345',
        ])->assertRedirect('/shop');

        $this->assertDatabaseHas('users', ['email' => 'juan@example.com', 'role' => 'user', 'status' => 'active']);
        $this->assertAuthenticated();
    }

    public function test_registration_ignores_a_role_sent_by_the_browser(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky Person', 'email' => 'sneaky@example.com', 'password' => 'Strong@12345',
            'password_confirmation' => 'Strong@12345', 'role' => 'instructor', 'status' => 'active',
        ])->assertRedirect('/shop');

        $this->assertDatabaseHas('users', ['email' => 'sneaky@example.com', 'role' => 'user']);
    }

    public function test_registration_rejects_weak_passwords_and_duplicate_emails(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $this->from('/register')->post('/register', [
            'name' => 'Applicant User', 'email' => $existing->email, 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect('/register')->assertSessionHasErrors(['email', 'password']);
    }

    public function test_suspended_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'password' => 'Secret@Pass123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret@Pass123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_customers_start_in_the_shop_and_staff_on_the_dashboard(): void
    {
        $customer = User::factory()->customer()->create(['password' => 'Secret@Pass123']);
        $staff = User::factory()->salesPersonnel()->create(['password' => 'Secret@Pass123']);

        $this->post('/login', ['email' => $customer->email, 'password' => 'Secret@Pass123'])->assertRedirect('/shop');
        auth()->logout();
        $this->post('/login', ['email' => $staff->email, 'password' => 'Secret@Pass123'])->assertRedirect('/dashboard');
    }
}
