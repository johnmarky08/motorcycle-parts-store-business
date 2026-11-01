<script>
    import { router } from "@inertiajs/svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import Checkbox from "../lib/components/ui/Checkbox.svelte";
    import Input from "../lib/components/ui/Input.svelte";

    export let errors = {};
    export let registration = null;

    let form = { name: "", email: "", password: "", password_confirmation: "", requested_role: "sales_personnel", affiliation: "" };
    let processing = false;
    let showPassword = false;

    const roles = [
        { value: "instructor", label: "Instructor" },
        { value: "inventory_clerk", label: "Inventory Clerk" },
        { value: "sales_personnel", label: "Sales Personnel" },
    ];

    function submit() {
        processing = true;
        router.post("/register", form, { onFinish: () => (processing = false) });
    }

    function roleLabel(value) {
        return roles.find((role) => role.value === value)?.label ?? value;
    }
</script>

<svelte:head><title>Request access | One Prompt Away</title></svelte:head>

<main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8 font-sans text-slate-900">
    <section class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <div class="mb-8">
            <p class="text-sm font-semibold tracking-tight text-slate-950">One Prompt Away</p>
            <p class="mt-0.5 text-xs text-slate-500">Motorcycle Parts Store</p>
        </div>

        {#if registration}
            <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-4 text-sm text-blue-900" role="status">
                <h1 class="font-semibold">Registration status: {registration.status}</h1>
                <p class="mt-2 text-xs leading-5">Your request for {roleLabel(registration.requested_role)} is being reviewed by an administrator. You cannot sign in until approval is complete.</p>
            </div>
            <a href="/login" class="mt-6 block text-center text-xs font-semibold text-blue-700 hover:text-blue-900">Return to sign in</a>
        {:else}
            <p class="mb-2 text-xs font-medium text-slate-500">Request account access</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Create a registration request</h1>
            <p class="mt-1 text-sm leading-6 text-slate-500">An administrator must review and assign your final role before you can sign in.</p>

            {#if errors.email}
                <div class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-700" role="alert">{errors.email}</div>
            {/if}

            <form class="mt-6 space-y-4" onsubmit={(event) => { event.preventDefault(); submit(); }} novalidate>
                <div><label for="register-name" class="mb-1.5 block text-xs font-medium text-slate-700">Full name *</label><Input id="register-name" bind:value={form.name} required autocomplete="name" className="h-11" />{#if errors.name}<p class="mt-1 text-[11px] text-rose-600">{errors.name}</p>{/if}</div>
                <div><label for="register-email" class="mb-1.5 block text-xs font-medium text-slate-700">Email address *</label><Input id="register-email" bind:value={form.email} required type="email" autocomplete="email" className="h-11" />{#if errors.email}<p class="mt-1 text-[11px] text-rose-600">{errors.email}</p>{/if}</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="register-password" class="mb-1.5 block text-xs font-medium text-slate-700">Password *</label><Input id="register-password" bind:value={form.password} required type={showPassword ? "text" : "password"} autocomplete="new-password" className="h-11" />{#if errors.password}<p class="mt-1 text-[11px] text-rose-600">{errors.password}</p>{/if}</div>
                    <div><label for="register-confirmation" class="mb-1.5 block text-xs font-medium text-slate-700">Confirm password *</label><Input id="register-confirmation" bind:value={form.password_confirmation} required type={showPassword ? "text" : "password"} autocomplete="new-password" className="h-11" />{#if errors.password_confirmation}<p class="mt-1 text-[11px] text-rose-600">{errors.password_confirmation}</p>{/if}</div>
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600"><Checkbox bind:checked={showPassword} /> Show passwords</label>
                <div><label for="register-role" class="mb-1.5 block text-xs font-medium text-slate-700">Requested role *</label><select id="register-role" bind:value={form.requested_role} class="h-11 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{#each roles as role}<option value={role.value}>{role.label}</option>{/each}</select></div>
                <div><label for="register-affiliation" class="mb-1.5 block text-xs font-medium text-slate-700">Employee/student identifier or affiliation *</label><Input id="register-affiliation" bind:value={form.affiliation} required className="h-11" placeholder="e.g. Employee ID or department" />{#if errors.affiliation}<p class="mt-1 text-[11px] text-rose-600">{errors.affiliation}</p>{/if}</div>
                <Button type="submit" disabled={processing} className="h-9 w-full">{processing ? "Submitting request…" : "Submit registration request"}</Button>
            </form>
            <p class="mt-6 text-center text-xs text-slate-500">Already have access? <a href="/login" class="font-semibold text-blue-700 hover:text-blue-900">Sign in</a></p>
        {/if}
    </section>
</main>
