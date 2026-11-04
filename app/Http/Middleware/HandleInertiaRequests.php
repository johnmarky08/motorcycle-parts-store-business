<?php

namespace App\Http\Middleware;

use App\Support\NotificationFeed;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default. Only non-sensitive user fields are shared.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only(['id', 'name', 'email', 'role', 'status']),
                    'role_label' => $user->roleEnum()?->label() ?? $user->role,
                ] : null,
            ],
            'navigation' => fn () => $user?->roleEnum()?->navigation() ?? [],
            'notifications' => fn () => $user ? NotificationFeed::for($user) : [],
            'status' => fn () => $request->session()->get('status'),
            'error' => fn () => $request->session()->get('error'),
        ];
    }
}
