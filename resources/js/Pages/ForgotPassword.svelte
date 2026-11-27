<script>
    import { Link, router } from "@inertiajs/svelte";
    import BrandLogo from "../Components/Shared/BrandLogo.svelte";
    import FullscreenToggle from "../Components/Shared/FullscreenToggle.svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import Input from "../lib/components/ui/Input.svelte";

    let email = "";
    let processing = false;
    export let errors = {};
    export let error = null;
    export let status = null;

    function submit() {
        processing = true;
        router.post(
            "/forgot-password",
            { email },
            {
                onFinish: () => (processing = false),
            },
        );
    }
</script>

<FullscreenToggle floating />

<svelte:head><title>Forgot Password | One Prompt Away</title></svelte:head>

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
        <h1 class="mt-3 text-2xl font-semibold tracking-tight">
            Forgot Your Password?
        </h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">
            Enter your account email and we will send you a password reset link.
            The message can take a few minutes and may land in your spam or junk
            folder, so please check there too.
        </p>
        {#if status}<div
                class="mt-5 rounded-md border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-xs text-emerald-800"
                role="status"
            >
                {status}
            </div>{/if}
        {#if error}<div
                class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-700"
                role="alert"
            >
                {error}
            </div>{/if}
        {#if errors.email}<div
                class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-700"
                role="alert"
            >
                {errors.email}
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
                    for="recovery-email"
                    class="mb-1.5 block text-xs font-medium text-slate-700"
                    >Email Address <span class="text-rose-600">*</span></label
                >
                <Input
                    id="recovery-email"
                    bind:value={email}
                    type="email"
                    autocomplete="email"
                    required
                    className="h-11"
                />
            </div>
            <Button type="submit" disabled={processing} className="h-9 w-full"
                >{processing ? "Sending…" : "Send Reset Link"}</Button
            >
        </form>
        <Link
            href="/login"
            class="mt-6 block text-center text-xs font-medium text-blue-700 hover:text-blue-900"
            >Back To Sign In</Link
        >
    </section>
</main>
