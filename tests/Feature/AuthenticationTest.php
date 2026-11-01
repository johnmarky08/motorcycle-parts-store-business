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
            'password' => 'secret-password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_user_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended',
            'password' => 'secret-password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
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
            'admin' => ['/dashboard', '/user-management', '/prediction', '/inventory', '/sales', '/knowledge-analysis'],
            'instructor' => ['/dashboard', '/knowledge-analysis'],
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

    public function test_registration_creates_a_pending_account_without_authenticating(): void
    {
        $response = $this->post('/register', [
            'name' => 'Applicant User',
            'email' => 'applicant@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'requested_role' => 'inventory_clerk',
            'affiliation' => 'Employee ID 1001',
        ]);

        $response->assertRedirect();
        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'applicant@example.com',
            'status' => 'pending',
            'requested_role' => 'inventory_clerk',
            'affiliation' => 'Employee ID 1001',
        ]);
    }

    public function test_registration_rejects_duplicate_email_and_admin_request(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $this->from('/register')->post('/register', [
            'name' => 'Applicant User',
            'email' => $user->email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'requested_role' => 'admin',
            'affiliation' => 'Employee ID 1001',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors(['email', 'requested_role']);
    }

    public function test_non_active_accounts_cannot_sign_in(): void
    {
        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $user = User::factory()->create([
                'status' => $status,
                'password' => 'secret-password',
            ]);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_admin_can_approve_registration_with_a_final_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $applicant = User::factory()->create([
            'role' => 'sales_personnel',
            'status' => 'pending',
            'requested_role' => 'inventory_clerk',
        ]);

        $this->actingAs($admin)->patch("/user-management/{$applicant->id}/approve", [
            'role' => 'inventory_clerk',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $applicant->id,
            'status' => 'active',
            'role' => 'inventory_clerk',
        ]);
        $this->assertDatabaseHas('registration_audits', [
            'applicant_id' => $applicant->id,
            'administrator_id' => $admin->id,
            'action' => 'approved',
            'assigned_role' => 'inventory_clerk',
        ]);

        $this->post('/logout');
        $this->post('/login', [
            'email' => $applicant->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($applicant->fresh());
    }

    public function test_admin_can_reject_registration_and_non_admin_cannot_decide(): void
    {
        $salesUser = User::factory()->create(['role' => 'sales_personnel', 'status' => 'active']);
        $applicant = User::factory()->create(['status' => 'pending']);

        $this->actingAs($salesUser)->patch("/user-management/{$applicant->id}/reject")
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->patch("/user-management/{$applicant->id}/reject")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $applicant->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('registration_audits', [
            'applicant_id' => $applicant->id,
            'administrator_id' => $admin->id,
            'action' => 'rejected',
        ]);
    }
}
