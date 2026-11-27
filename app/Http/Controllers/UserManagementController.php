<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User accounts for instructors and admins. Safeguards (OWASP A01):
 *  - nobody can change, suspend or delete their own account here;
 *  - at least one active instructor or admin always remains;
 *  - an admin can never create, edit, suspend or delete an instructor account, nor assign the
 *    instructor role, because that would let an admin reach the evaluation metrics and datasets
 *    that are reserved for the instructor.
 */
class UserManagementController extends Controller
{
    private const MANAGER_ROLES = ['instructor', 'admin'];

    public function index(Request $request): Response
    {
        return Inertia::render('UserManagement', [
            'initialUsers' => User::query()->latest()->limit(2000)->get(['id', 'name', 'email', 'role', 'status', 'last_login_at']),
            'roles' => Role::options($this->assignableRoles($request->user())),
            'protectedRoles' => $request->user()->hasRole(Role::Instructor) ? [] : [Role::Instructor->value],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = $data['role'];
        $user->status = $data['status'];
        $user->save();
        Log::channel('security')->info('user.created', ['by' => $request->user()->id, 'user_id' => $user->id, 'role' => $user->role]);

        return back()->with('status', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($denied = $this->denyProtected($request, $user)) {
            return $denied;
        }
        $data = $this->validated($request, $user);

        if ($request->user()->is($user) && ($data['role'] !== $user->role || $data['status'] !== $user->status)) {
            return back()->withErrors(['role' => 'You cannot change your own role or status.']);
        }
        if ($this->removesLastManager($user, $data['role'], $data['status'])) {
            return back()->withErrors(['role' => 'At least one active instructor or admin must remain.']);
        }
        // A new password is only accepted together with the account's current password.
        if (! empty($data['password']) && ! Hash::check((string) $data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
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
        if ($denied = $this->denyProtected($request, $user)) {
            return $denied;
        }
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($this->removesLastManager($user, 'none', 'deleted')) {
            return back()->with('error', 'At least one active instructor or admin must remain.');
        }

        $user->delete();
        Log::channel('security')->info('user.deleted', ['by' => $request->user()->id, 'user_id' => $user->id]);

        return back()->with('status', 'User deleted successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($denied = $this->denyProtected($request, $user)) {
            return $denied;
        }
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot change your own status.');
        }

        $next = $user->status === 'active' ? 'suspended' : 'active';
        if ($this->removesLastManager($user, $user->role, $next)) {
            return back()->with('error', 'At least one active instructor or admin must remain.');
        }

        $user->forceFill(['status' => $next])->save();
        Log::channel('security')->info('user.status_changed', ['by' => $request->user()->id, 'user_id' => $user->id, 'status' => $next]);

        return back()->with('status', $next === 'active' ? 'User activated.' : 'User suspended.');
    }

    /** @return list<string> */
    private function assignableRoles(User $actor): array
    {
        return $actor->hasRole(Role::Instructor)
            ? Role::values()
            : array_values(array_diff(Role::values(), [Role::Instructor->value]));
    }

    /** Admins may not touch instructor accounts (they would otherwise gain the instructor's access). */
    private function denyProtected(Request $request, User $target): ?RedirectResponse
    {
        if ($target->hasRole(Role::Instructor) && ! $request->user()->hasRole(Role::Instructor)) {
            Log::channel('security')->warning('user.protected_denied', ['by' => $request->user()->id, 'user_id' => $target->id, 'path' => $request->path()]);

            return back()->with('error', 'Only an instructor can change an instructor account.');
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\p{L}\p{M}\s.'\-]+$/u"],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in($this->assignableRoles($request->user()))],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'max:72', Password::defaults()],
            'current_password' => $user ? ['nullable', 'required_with:password', 'string', 'max:72'] : ['prohibited'],
        ], [
            'role.in' => 'You are not allowed to assign this role.',
            'current_password.required_with' => 'Enter the current password to set a new one.',
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
