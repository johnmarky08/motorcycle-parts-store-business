<script>
    import { Link, router } from "@inertiajs/svelte";
    import FullscreenToggle from "../Components/Shared/FullscreenToggle.svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import Checkbox from "../lib/components/ui/Checkbox.svelte";
    import Input from "../lib/components/ui/Input.svelte";

    let email = "";
    let password = "";
    let rememberMe = false;
    let showPassword = false;
    let processing = false;
    export let errors = {};
    export let status = null;

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

        return Object.keys(nextErrors).length === 0;
    }

    function submitLogin() {
        if (!validate()) return;

        processing = true;
        router.post(
            "/login",
            {
                email: email.trim(),
                password,
                remember: rememberMe,
            },
            {
                onFinish: () => (processing = false),
            },
        );
    }
</script>

<FullscreenToggle floating />

<svelte:head>
    <title>Sign In | One Prompt Away</title>
</svelte:head>

<main
    class="flex min-h-screen items-center justify-center bg-slate-50 px-4 pb-8 pt-20 font-sans text-slate-900 sm:px-6"
>
    <div
        class="grid w-full max-w-260 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(0,0.9fr)_minmax(360px,1.1fr)]"
    >
        <section
            class="hidden bg-slate-900 p-10 text-white lg:flex lg:flex-col lg:justify-between"
        >
            <div>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-slate-900"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="h-5 w-5"
                            aria-hidden="true"
                        >
                            <path
                                d="M5 16.5h14M7 16.5a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3m6 0a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3"
                            />
                            <path d="M9 10h6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-tight">
                            One Prompt Away
                        </p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            Motorcycle Parts Store
                        </p>
                    </div>
                </div>
                <div class="mt-20 max-w-sm">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300"
                    >
                        Welcome
                    </p>
                    <h1
                        class="mt-4 text-3xl font-semibold leading-tight tracking-tight"
                    >
                        The right parts for every ride.
                    </h1>
                    <p class="mt-4 text-sm leading-6 text-slate-300">
                        Shop quality motorcycle parts and track your orders, all
                        in one place for riders and the team behind the store.
                    </p>
                </div>
            </div>
        </section>

        <section class="p-6 sm:p-10">
            <div class="mb-8 lg:hidden">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="h-5 w-5"
                            aria-hidden="true"
                            ><path
                                d="M5 16.5h14M7 16.5a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3m6 0a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3"
                            /><path d="M9 10h6" /></svg
                        >
                    </div>
                    <div>
                        <p
                            class="text-sm font-semibold tracking-tight text-slate-950"
                        >
                            One Prompt Away
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Motorcycle Parts Store
                        </p>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-md">
                <p class="mb-2 text-xs font-medium text-slate-500">
                    Account Access
                </p>
                <h2
                    class="text-2xl font-semibold tracking-tight text-slate-950"
                >
                    Welcome Back
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Sign in to continue to your store workspace.
                </p>

                {#if errors.email && !processing}
                    <div
                        class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs leading-5 text-rose-700"
                        role="alert"
                    >
                        <p class="font-semibold">Unable To Sign In</p>
                        <p class="mt-0.5">{errors.email}</p>
                    </div>
                {/if}
                {#if status}
                    <div
                        class="mt-5 rounded-md border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-xs leading-5 text-emerald-700"
                        role="status"
                    >
                        {status}
                    </div>
                {/if}

                <form
                    class="mt-6 space-y-5"
                    onsubmit={(event) => {
                        event.preventDefault();
                        submitLogin();
                    }}
                    novalidate
                >
                    <div>
                        <label
                            for="login-email"
                            class="mb-1.5 block text-xs font-medium text-slate-700"
                            >Email Address <span class="text-rose-600">*</span
                            ></label
                        >
                        <Input
                            id="login-email"
                            bind:value={email}
                            type="email"
                            autocomplete="username"
                            aria-invalid={errors.email ? "true" : "false"}
                            aria-describedby={errors.email
                                ? "login-email-error"
                                : undefined}
                            className={`h-11 ${errors.email ? "border-rose-300" : ""}`}
                            placeholder="you@example.com"
                        />
                        {#if errors.email}<p
                                id="login-email-error"
                                class="mt-1.5 text-[11px] text-rose-600"
                            >
                                {errors.email}
                            </p>{/if}
                    </div>

                    <div>
                        <label
                            for="login-password"
                            class="mb-1.5 block text-xs font-medium text-slate-700"
                            >Password <span class="text-rose-600">*</span
                            ></label
                        >
                        <div class="relative">
                            <Input
                                id="login-password"
                                bind:value={password}
                                type={showPassword ? "text" : "password"}
                                autocomplete="current-password"
                                aria-invalid={errors.password
                                    ? "true"
                                    : "false"}
                                aria-describedby={errors.password
                                    ? "login-password-error"
                                    : undefined}
                                className={`h-11 pr-12 ${errors.password ? "border-rose-300" : ""}`}
                                placeholder="Enter Your Password"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-700"
                                aria-label={showPassword
                                    ? "Hide Password"
                                    : "Show Password"}
                                onclick={() => (showPassword = !showPassword)}
                            >
                                {#if showPassword}<svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                        ><path
                                            d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.7 10.7 0 0 1 12 5c5.5 0 9 7 9 7a17.4 17.4 0 0 1-2.1 2.9M6.5 6.5C3.9 8.3 3 12 3 12s3.5 7 9 7c1.4 0 2.7-.4 3.8-.9"
                                        /></svg
                                    >{:else}<svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                        ><path
                                            d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z"
                                        /><circle
                                            cx="12"
                                            cy="12"
                                            r="2.5"
                                        /></svg
                                    >{/if}
                            </button>
                        </div>
                        {#if errors.password}<p
                                id="login-password-error"
                                class="mt-1.5 text-[11px] text-rose-600"
                            >
                                {errors.password}
                            </p>{/if}
                    </div>

                    <label
                        class="flex items-center gap-2 text-xs text-slate-600"
                    >
                        <Checkbox bind:checked={rememberMe} />
                        Remember Me
                    </label>

                    <div class="flex justify-end">
                        <Link
                            href="/forgot-password"
                            class="text-xs font-medium text-blue-700 hover:text-blue-900"
                            >Forgot Password?</Link
                        >
                    </div>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="h-9 w-full gap-2"
                    >
                        {#if processing}<span
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                aria-hidden="true"
                            ></span>Signing in…{:else}Sign in{/if}
                    </Button>
                </form>

                <p class="mt-8 text-center text-xs leading-5 text-slate-500">
                    Don’t Have An Account? <Link
                        href="/register"
                        class="font-semibold text-blue-700 hover:text-blue-900"
                        >Register</Link
                    > To Start Shopping.
                </p>
            </div>
        </section>
    </div>
</main>
