<script>
    import { Link, router } from "@inertiajs/svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import Input from "../lib/components/ui/Input.svelte";

    export let token = "";
    export let email = "";
    export let errors = {};
    let password = "";
    let passwordConfirmation = "";
    let processing = false;
    let showPassword = false;

    function submit() {
        processing = true;
        router.post("/reset-password", {
            token,
            email,
            password,
            password_confirmation: passwordConfirmation,
        }, {
            onFinish: () => (processing = false),
        });
    }
</script>

<svelte:head><title>Reset Password | One Prompt Away</title></svelte:head>

<main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8 font-sans text-slate-900">
    <section class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Account Recovery</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight">Set A New Password</h1>
        {#if errors.email}<div class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-700" role="alert">{errors.email}</div>{/if}
        <form class="mt-6 space-y-5" onsubmit={(event) => { event.preventDefault(); submit(); }} novalidate>
            <div><label for="reset-email" class="mb-1.5 block text-xs font-medium text-slate-700">Email Address</label><Input id="reset-email" bind:value={email} type="email" required className="h-11" /></div>
            <div><label for="reset-password" class="mb-1.5 block text-xs font-medium text-slate-700">New Password</label><div class="relative"><Input id="reset-password" bind:value={password} type={showPassword ? "text" : "password"} required minlength="8" className="h-11 pr-12" /><button type="button" class="absolute inset-y-0 right-0 px-3 text-xs text-slate-500 hover:text-slate-900" onclick={() => (showPassword = !showPassword)}>{showPassword ? "Hide" : "Show"}</button></div></div>
            <div><label for="reset-password-confirmation" class="mb-1.5 block text-xs font-medium text-slate-700">Confirm Password</label><Input id="reset-password-confirmation" bind:value={passwordConfirmation} type={showPassword ? "text" : "password"} required minlength="8" className="h-11" /></div>
            <Button type="submit" disabled={processing} className="h-9 w-full">{processing ? "Updating…" : "Update Password"}</Button>
        </form>
    </section>
</main>
