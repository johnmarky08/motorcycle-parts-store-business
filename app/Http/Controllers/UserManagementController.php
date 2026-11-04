<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RegistrationAudit;
use App\Support\Like;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
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
            'initialUsers' => User::query()->latest()->get(['id', 'name', 'email', 'role', 'status', 'last_login_at']),
            'filters' => $request->only(['search', 'role', 'status']),
            'pendingCount' => User::where('status', 'pending')->count(),
            'pendingRegistrations' => User::where('status', 'pending')->latest('submitted_at')
                ->get(['id', 'name', 'email', 'requested_role', 'affiliation', 'submitted_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = $request->string('password')->isNotEmpty()
            ? $request->string('password')->toString()
            : str()->random(32);

        User::create($data);

        return back()->with('status', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']); // a blank password field keeps the current password
        }
        if ($request->user()->is($user) && ($data['role'] !== $user->role || $data['status'] !== $user->status)) {
            return back()->withErrors(['role' => 'You cannot change your own role or status.']);
        }
        if ($this->removesLastAdmin($user, $data['role'], $data['status'])) {
            return back()->withErrors(['role' => 'At least one active admin account must remain.']);
        }
        $user->update($data);
        Log::channel('security')->info('user.updated', ['by' => $request->user()->id, 'user_id' => $user->id, 'role' => $user->role, 'status' => $user->status]);

        return back()->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if(auth()->id() === $user->id, 422, 'You cannot delete your own account.');
        abort_if($this->removesLastAdmin($user, 'none', 'deleted'), 422, 'At least one active admin account must remain.');
        $user->delete();
        Log::channel('security')->info('user.deleted', ['by' => auth()->id(), 'user_id' => $user->id]);

        return back()->with('status', 'User deleted successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        abort_if(auth()->id() === $user->id, 422, 'You cannot deactivate your own account.');
        $next = $user->status === 'active' ? 'suspended' : 'active';
        abort_if($this->removesLastAdmin($user, $user->role, $next), 422, 'At least one active admin account must remain.');
        $user->update(['status' => $next]);
        Log::channel('security')->info('user.status_changed', ['by' => auth()->id(), 'user_id' => $user->id, 'status' => $next]);

        return back()->with('status', 'User status updated successfully.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\p{L}\p{M}\s.'\-]+$/u"],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(['admin', 'instructor', 'inventory_clerk', 'sales_personnel'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => ['nullable', 'string', Password::defaults()],
        ]);
    }

    /** True when the change would leave the system without an active admin. */
    private function removesLastAdmin(User $user, string $newRole, string $newStatus): bool
    {
        $wasActiveAdmin = $user->role === 'admin' && $user->status === 'active';
        if (! $wasActiveAdmin || ($newRole === 'admin' && $newStatus === 'active')) {
            return false;
        }

        return ! User::where('role', 'admin')->where('status', 'active')->where('id', '!=', $user->id)->exists();
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->status === 'pending', 422, 'Only pending registrations can be approved.');
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'instructor', 'inventory_clerk', 'sales_personnel'])],
        ]);

        $user->update(['role' => $data['role'], 'status' => 'active']);
        RegistrationAudit::create([
            'applicant_id' => $user->id,
            'administrator_id' => $request->user()->id,
            'action' => 'approved',
            'assigned_role' => $data['role'],
        ]);

        return back()->with('status', 'Registration approved successfully.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->status === 'pending', 422, 'Only pending registrations can be rejected.');
        $user->update(['status' => 'rejected']);
        Log::channel('security')->info('user.registration_rejected', ['by' => $request->user()->id, 'user_id' => $user->id]);
        RegistrationAudit::create([
            'applicant_id' => $user->id,
            'administrator_id' => $request->user()->id,
            'action' => 'rejected',
        ]);

        return back()->with('status', 'Registration rejected.');
    }
}
