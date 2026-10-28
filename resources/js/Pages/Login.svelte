<script>
    import { onDestroy } from "svelte";

    let email = "";
    let password = "";
    let rememberMe = false;
    let showPassword = false;
    let status = "idle";
    let errors = {};
    let submitTimer;

    function validate() {
        const nextErrors = {};

        if (!email.trim()) {
            nextErrors.email = "Enter your email address.";
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
            nextErrors.email = "Enter a valid email address.";
        }

        if (!password) {
            nextErrors.password = "Enter your password.";
        }

        errors = nextErrors;
        return Object.keys(nextErrors).length === 0;
    }

    function submitLogin() {
        status = "idle";
        if (!validate()) return;

        window.clearTimeout(submitTimer);
        status = "loading";
        submitTimer = window.setTimeout(() => {
            status = "invalid";
        }, 700);
    }

    onDestroy(() => window.clearTimeout(submitTimer));
</script>

<svelte:head>
    <title>Sign In | One Prompt Away</title>
</svelte:head>

<main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8 font-sans text-slate-900 sm:px-6">
    <div class="grid w-full max-w-[1040px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(0,0.9fr)_minmax(360px,1.1fr)]">
        <section class="hidden bg-slate-900 p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-slate-900">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                            <path d="M5 16.5h14M7 16.5a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3m6 0a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3" />
                            <path d="M9 10h6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-tight">One Prompt Away</p>
                        <p class="mt-0.5 text-xs text-slate-400">Motorcycle Parts Store</p>
                    </div>
                </div>
                <div class="mt-20 max-w-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300">Store operations</p>
                    <h1 class="mt-4 text-3xl font-semibold leading-tight tracking-tight">Keep every part, sale and forecast in view.</h1>
                    <p class="mt-4 text-sm leading-6 text-slate-300">A practical workspace for managing motorcycle parts, inventory, sales and analysis.</p>
                </div>
            </div>
            <p class="text-xs text-slate-400">Frontend preview · Authentication will be connected by the backend.</p>
        </section>

        <section class="p-6 sm:p-10">
            <div class="mb-8 lg:hidden">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M5 16.5h14M7 16.5a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3m6 0a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3" /><path d="M9 10h6" /></svg>
                    </div>
                    <div><p class="text-sm font-semibold tracking-tight text-slate-950">One Prompt Away</p><p class="mt-0.5 text-xs text-slate-500">Motorcycle Parts Store</p></div>
                </div>
            </div>

            <div class="mx-auto max-w-md">
                <p class="mb-2 text-xs font-medium text-slate-500">Account access</p>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Welcome back</h2>
                <p class="mt-1 text-sm text-slate-500">Sign in to continue to your store workspace.</p>

                <div class="mt-6 flex items-start gap-3 rounded-md border border-blue-100 bg-blue-50/70 px-3.5 py-3 text-xs leading-5 text-blue-800">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 10v6M12 7h.01" /></svg>
                    <p><span class="font-semibold">Preview only —</span> authentication is not connected. No credentials are verified or stored.</p>
                </div>

                {#if status === "invalid"}
                    <div class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs leading-5 text-rose-700" role="alert">
                        <p class="font-semibold">Unable to sign in</p>
                        <p class="mt-0.5">Invalid credentials. This preview cannot verify accounts until backend authentication is connected.</p>
                    </div>
                {/if}

                <form class="mt-6 space-y-5" on:submit|preventDefault={submitLogin} novalidate>
                    <div>
                        <label for="login-email" class="mb-1.5 block text-xs font-medium text-slate-700">Email address <span class="text-rose-600">*</span></label>
                        <input id="login-email" bind:value={email} type="email" autocomplete="username" aria-invalid={errors.email ? "true" : "false"} aria-describedby={errors.email ? "login-email-error" : undefined} class:border-rose-300={errors.email} class="h-11 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-800 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="you@example.com" />
                        {#if errors.email}<p id="login-email-error" class="mt-1.5 text-[11px] text-rose-600">{errors.email}</p>{/if}
                    </div>

                    <div>
                        <label for="login-password" class="mb-1.5 block text-xs font-medium text-slate-700">Password <span class="text-rose-600">*</span></label>
                        <div class="relative">
                            <input id="login-password" bind:value={password} type={showPassword ? "text" : "password"} autocomplete="current-password" aria-invalid={errors.password ? "true" : "false"} aria-describedby={errors.password ? "login-password-error" : undefined} class:border-rose-300={errors.password} class="h-11 w-full rounded-md border border-slate-200 px-3 pr-12 text-sm text-slate-800 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Enter your password" />
                            <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-700" aria-label={showPassword ? "Hide password" : "Show password"} on:click={() => (showPassword = !showPassword)}>
                                {#if showPassword}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.7 10.7 0 0 1 12 5c5.5 0 9 7 9 7a17.4 17.4 0 0 1-2.1 2.9M6.5 6.5C3.9 8.3 3 12 3 12s3.5 7 9 7c1.4 0 2.7-.4 3.8-.9" /></svg>{:else}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z" /><circle cx="12" cy="12" r="2.5" /></svg>{/if}
                            </button>
                        </div>
                        {#if errors.password}<p id="login-password-error" class="mt-1.5 text-[11px] text-rose-600">{errors.password}</p>{/if}
                    </div>

                    <label class="flex items-center gap-2 text-xs text-slate-600">
                        <input bind:checked={rememberMe} type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                        Remember me <span class="text-slate-400">(preview only)</span>
                    </label>

                    <button type="submit" disabled={status === "loading"} class="flex h-11 w-full items-center justify-center gap-2 rounded-md bg-slate-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-70">
                        {#if status === "loading"}<span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>Checking preview…{:else}Sign in{/if}
                    </button>
                </form>

                <p class="mt-8 text-center text-[11px] leading-5 text-slate-400">Your assigned <span class="font-semibold text-slate-500">user_level</span> will determine your role after real authentication is implemented. Role selection is intentionally not available on this form.</p>
            </div>
        </section>
    </div>
</main>
