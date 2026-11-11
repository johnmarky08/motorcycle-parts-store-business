<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Instructor and admin can change every account to any role. Safeguards: nobody can change or delete
 * their own account here, and at least one active instructor or admin must always remain.
 */
class UserManagementController extends Controller
{
    private const MANAGER_ROLES = ['instructor', 'admin'];

    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($request) {
                $search = Like::escape($request->string('search')->toString());
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->string('role')->isNotEmpty(), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $user->only(['id', 'name', 'email', 'role', 'status', 'last_login_at']));

        return Inertia::render('UserManagement', [
            'users' => $users,
            'initialUsers' => User::query()->latest()->limit(2000)->get(['id', 'name', 'email', 'role', 'status', 'last_login_at']),
            'filters' => $request->only(['search', 'role', 'status']),
            'roles' => Role::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = $request->string('password')->isNotEmpty()
            ? $request->string('password')->toString()
            : str()->random(32); // unusable until the person chooses one with "Forgot Password"

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = $data['role'];
        $user->status = $data['status'];
        $user->save();
        Log::channel('security')->info('user.created', ['by' => $request->user()->id, 'user_id' => $user->id, 'role' => $user->role]);

        return back()->with('status', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($request->user()->is($user) && ($data['role'] !== $user->role || $data['status'] !== $user->status)) {
            return back()->withErrors(['role' => 'You cannot change your own role or status.']);
        }
        if ($this->removesLastManager($user, $data['role'], $data['status'])) {
            return back()->withErrors(['role' => 'At least one active instructor or admin must remain.']);
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        $user->role = $data['role'];
        $user->status = $data['status'];
        if (! empty($data['password'])) {
            $user->password = $data['password']; // a blank password field keeps the current password
        }
        $user->save();
        Log::channel('security')->info('user.updated', ['by' => $request->user()->id, 'user_id' => $user->id, 'role' => $user->role, 'status' => $user->status]);

        return back()->with('status', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        abort_if($this->removesLastManager($user, 'none', 'deleted'), 422, 'At least one active instructor or admin must remain.');

        $user->delete();
        Log::channel('security')->info('user.deleted', ['by' => $request->user()->id, 'user_id' => $user->id]);

        return back()->with('status', 'User deleted successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot change your own status.');

        $next = $user->status === 'active' ? 'suspended' : 'active';
        abort_if($this->removesLastManager($user, $user->role, $next), 422, 'At least one active instructor or admin must remain.');

        $user->update(['status' => $next]);
        Log::channel('security')->info('user.status_changed', ['by' => $request->user()->id, 'user_id' => $user->id, 'status' => $next]);

        return back()->with('status', $next === 'active' ? 'User activated.' : 'User suspended.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\p{L}\p{M}\s.'\-]+$/u"],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(Role::values())],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => ['nullable', 'string', Password::defaults()],
        ]);
    }

    /** True when the change would leave the system without an active instructor or admin. */
    private function removesLastManager(User $user, string $newRole, string $newStatus): bool
    {
        $wasManager = in_array($user->role, self::MANAGER_ROLES, true) && $user->status === 'active';
        if (! $wasManager || (in_array($newRole, self::MANAGER_ROLES, true) && $newStatus === 'active')) {
            return false;
        }

        return ! User::whereIn('role', self::MANAGER_ROLES)->where('status', 'active')->where('id', '!=', $user->id)->exists();
    }
}
