<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: int}> */
    public static function matrix(): array
    {
        return [
            'admin dashboard' => ['admin', '/dashboard', 200],
            'admin users' => ['admin', '/user-management', 200],
            'admin prediction' => ['admin', '/prediction', 200],
            'admin knowledge analysis blocked' => ['admin', '/knowledge-analysis', 403],
            'admin inventory blocked' => ['admin', '/inventory', 403],
            'admin sales blocked' => ['admin', '/sales', 403],
            'instructor dashboard' => ['instructor', '/dashboard', 200],
            'instructor prediction' => ['instructor', '/prediction', 200],
            'instructor knowledge analysis' => ['instructor', '/knowledge-analysis', 200],
            'instructor users blocked' => ['instructor', '/user-management', 403],
            'instructor inventory blocked' => ['instructor', '/inventory', 403],
            'clerk inventory' => ['inventory_clerk', '/inventory', 200],
            'clerk sales blocked' => ['inventory_clerk', '/sales', 403],
            'clerk prediction blocked' => ['inventory_clerk', '/prediction', 403],
            'clerk analysis blocked' => ['inventory_clerk', '/knowledge-analysis', 403],
            'sales sales' => ['sales_personnel', '/sales', 200],
            'sales inventory blocked' => ['sales_personnel', '/inventory', 403],
            'sales knowledge blocked' => ['sales_personnel', '/knowledge-analysis', 403],
            'sales users blocked' => ['sales_personnel', '/user-management', 403],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_access(string $role, string $path, int $status): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get($path)->assertStatus($status);
    }

    public function test_suspended_user_is_signed_out_of_an_open_session(): void
    {
        $user = User::factory()->admin()->inactive()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guests_cannot_reach_protected_pages(): void
    {
        foreach (['/dashboard', '/inventory', '/sales', '/prediction', '/knowledge-analysis', '/user-management'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }
}
