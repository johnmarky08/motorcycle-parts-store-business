<script>
    import { Link, router } from "@inertiajs/svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import Checkbox from "../lib/components/ui/Checkbox.svelte";
    import Input from "../lib/components/ui/Input.svelte";

    export let errors = {};

    let form = { name: "", email: "", password: "", password_confirmation: "" };
    let processing = false;
    let showPassword = false;

    $: rules = [
        { label: "At Least 8 Characters", ok: form.password.length >= 8 },
        { label: "Upper And Lower Case Letters", ok: /[a-z]/.test(form.password) && /[A-Z]/.test(form.password) },
        { label: "A Number", ok: /\d/.test(form.password) },
        { label: "A Symbol (@ # $ !)", ok: /[^A-Za-z0-9]/.test(form.password) },
    ];

    function submit() {
        if (processing) return;
        processing = true;
        router.post("/register", form, {
            onFinish: () => {
                processing = false;
                form.password = "";
                form.password_confirmation = "";
            },
        });
    }
</script>

<svelte:head><title>Register | One Prompt Away</title></svelte:head>

<main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8 font-sans text-slate-900">
    <section class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <div class="mb-8">
            <p class="text-sm font-semibold tracking-tight text-slate-950">One Prompt Away</p>
            <p class="mt-0.5 text-xs text-slate-500">Motorcycle Parts Store</p>
        </div>

        <p class="mb-2 text-xs font-medium text-slate-500">Create Your Account</p>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Register</h1>
        <p class="mt-1 text-sm leading-6 text-slate-500">Create a free account to browse our motorcycle parts, fill your cart and place orders.</p>

        <form class="mt-6 space-y-4" onsubmit={(event) => { event.preventDefault(); submit(); }} novalidate>
            <div>
                <label for="register-name" class="mb-1.5 block text-xs font-medium text-slate-700">Full Name *</label>
                <Input id="register-name" bind:value={form.name} required autocomplete="name" />
                {#if errors.name}<p class="mt-1.5 text-[11px] text-rose-600">{errors.name}</p>{/if}
            </div>
            <div>
                <label for="register-email" class="mb-1.5 block text-xs font-medium text-slate-700">Email Address *</label>
                <Input id="register-email" bind:value={form.email} required type="email" autocomplete="email" />
                {#if errors.email}<p class="mt-1.5 text-[11px] text-rose-600">{errors.email}</p>{/if}
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="register-password" class="mb-1.5 block text-xs font-medium text-slate-700">Password *</label>
                    <Input id="register-password" bind:value={form.password} required type={showPassword ? "text" : "password"} autocomplete="new-password" />
                </div>
                <div>
                    <label for="register-confirmation" class="mb-1.5 block text-xs font-medium text-slate-700">Confirm Password *</label>
                    <Input id="register-confirmation" bind:value={form.password_confirmation} required type={showPassword ? "text" : "password"} autocomplete="new-password" />
                </div>
            </div>
            {#if errors.password}<p class="text-[11px] text-rose-600">{errors.password}</p>{/if}
            <ul class="grid grid-cols-1 gap-1 text-[11px] sm:grid-cols-2" aria-label="Password Requirements">
                {#each rules as rule}
                    <li class:text-emerald-700={rule.ok} class:text-slate-400={!rule.ok} class="flex items-center gap-1.5"><span aria-hidden="true">{rule.ok ? "✓" : "○"}</span>{rule.label}</li>
                {/each}
            </ul>
            <label class="flex items-center gap-2 text-xs text-slate-600"><Checkbox bind:checked={showPassword} /> Show Passwords</label>
            <Button type="submit" disabled={processing} className="h-9 w-full">{processing ? "Creating Account…" : "Register"}</Button>
        </form>
        <p class="mt-6 text-center text-xs text-slate-500">Already Have An Account? <Link href="/login" class="font-semibold text-blue-700 hover:text-blue-900">Sign In</Link></p>
    </section>
</main>
