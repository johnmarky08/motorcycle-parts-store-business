<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RegistrationAudit;
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
                $search = $request->string('search')->toString();
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
        $user->update($this->validated($request, $user));

        return back()->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if(auth()->id() === $user->id, 422, 'You cannot delete your own account.');
        $user->delete();

        return back()->with('status', 'User deleted successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        abort_if(auth()->id() === $user->id, 422, 'You cannot deactivate your own account.');
        $user->update(['status' => $user->status === 'active' ? 'suspended' : 'active']);

        return back()->with('status', 'User status updated successfully.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(['admin', 'instructor', 'inventory_clerk', 'sales_personnel'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->status === 'pending', 422, 'Only pending registrations can be approved.');
        $data = $request->validate([
            'role' => ['required', Rule::in(['instructor', 'inventory_clerk', 'sales_personnel'])],
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
        RegistrationAudit::create([
            'applicant_id' => $user->id,
            'administrator_id' => $request->user()->id,
            'action' => 'rejected',
        ]);

        return back()->with('status', 'Registration rejected.');
    }
}
