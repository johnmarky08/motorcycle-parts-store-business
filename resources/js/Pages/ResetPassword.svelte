<script>
    import { Link, router } from "@inertiajs/svelte";
    import BrandLogo from "../Components/Shared/BrandLogo.svelte";
    import FullscreenToggle from "../Components/Shared/FullscreenToggle.svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import Input from "../lib/components/ui/Input.svelte";

    // "token" is the sealed code from the email link; the server reads the email from it.
    export let token = "";
    export let email = "";
    export let invalid = false;
    export let errors = {};
    let password = "";
    let passwordConfirmation = "";
    let processing = false;
    let showPassword = false;

    function submit() {
        processing = true;
        router.post(
            "/reset-password",
            {
                token,
                password,
                password_confirmation: passwordConfirmation,
            },
            {
                onFinish: () => (processing = false),
            },
        );
    }
</script>

<FullscreenToggle floating />

<svelte:head><title>Reset Password | One Prompt Away</title></svelte:head>

<main
    class="flex min-h-screen items-center justify-center bg-slate-50 px-4 pb-8 pt-20 font-sans text-slate-900"
>
    <section
        class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10"
    >
        <BrandLogo className="mb-8" />
        <p
            class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700"
        >
            Account Recovery
        </p>
        {#if invalid}
            <!-- The link was modified, cut off or is not one of ours: no form, only a way to get a new link. -->
            <h1 class="mt-3 text-2xl font-semibold tracking-tight">
                Invalid Link
            </h1>
            <div
                class="mt-5 flex gap-3 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs leading-5 text-rose-700"
                role="alert"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"><path d="M12 4 3.5 19h17L12 4Z" /><path d="M12 9v4M12 16h.01" /></svg>
                <span>
                    This password reset link is invalid. It may have been changed,
                    copied incompletely, or it is not a link from this store.
                </span>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-500">
                For your security, please request a new reset link and open it
                straight from the email.
            </p>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                <Link
                    href="/forgot-password"
                    class="inline-flex h-9 flex-1 items-center justify-center rounded-md bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800"
                    >Request A New Link</Link
                >
                <Link
                    href="/login"
                    class="inline-flex h-9 flex-1 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >Back To Sign In</Link
                >
            </div>
        {:else}
        <h1 class="mt-3 text-2xl font-semibold tracking-tight">
            Set A New Password
        </h1>
        {#if errors.email}<div
                class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-700"
                role="alert"
            >
                {errors.email}
                <Link href="/forgot-password" class="ml-1 font-semibold underline">Request a new link</Link>
            </div>{/if}
        <form
            class="mt-6 space-y-5"
            onsubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            novalidate
        >
            <div>
                <label
                    for="reset-email"
                    class="mb-1.5 block text-xs font-medium text-slate-700"
                    >Email Address</label
                ><Input
                    id="reset-email"
                    value={email}
                    type="email"
                    readonly
                    aria-readonly="true"
                    tabindex="-1"
                    aria-describedby="reset-email-help"
                    className="h-11 cursor-not-allowed bg-slate-50 text-slate-500 focus-visible:ring-0"
                />
                <p id="reset-email-help" class="mt-1.5 text-[11px] text-slate-400">
                    The password will be changed for the account this reset link was sent to.
                </p>
            </div>
            <div>
                <label
                    for="reset-password"
                    class="mb-1.5 block text-xs font-medium text-slate-700"
                    >New Password</label
                >
                <div class="relative">
                    <Input
                        id="reset-password"
                        bind:value={password}
                        type={showPassword ? "text" : "password"}
                        required
                        minlength="8"
                        className="h-11 pr-16"
                    /><button
                        type="button"
                        class="absolute inset-y-0 right-0 flex w-14 items-center justify-center text-xs text-slate-500 hover:text-slate-900"
                        onclick={() => (showPassword = !showPassword)}
                        >{showPassword ? "Hide" : "Show"}</button
                    >
                </div>
            </div>
            <div>
                <label
                    for="reset-password-confirmation"
                    class="mb-1.5 block text-xs font-medium text-slate-700"
                    >Confirm Password</label
                ><Input
                    id="reset-password-confirmation"
                    bind:value={passwordConfirmation}
                    type={showPassword ? "text" : "password"}
                    required
                    minlength="8"
                    className="h-11"
                />
                {#if errors.password}<p class="mt-1.5 text-[11px] text-rose-600" role="alert">
                        {errors.password}
                    </p>{:else}<p class="mt-1.5 text-[11px] text-slate-400">
                        At least 8 characters with upper and lower case letters, a number and a symbol.
                    </p>{/if}
            </div>
            <Button type="submit" disabled={processing} className="h-9 w-full"
                >{processing ? "Updating…" : "Update Password"}</Button
            >
        </form>
        {/if}
    </section>
</main>
