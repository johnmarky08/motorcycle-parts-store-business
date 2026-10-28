<script>
    import { onMount } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";

    // Preview-only data. Replace with Inertia props when the backend contract is available.
    let users = [
        { id: 1, name: "John Dela Cruz", email: "john@example.com", role: "admin", status: "active", last_login_at: "Today, 9:42 AM" },
        { id: 2, name: "Maria Santos", email: "maria.santos@example.com", role: "inventory_clerk", status: "active", last_login_at: "Today, 8:16 AM" },
        { id: 3, name: "Carlo Reyes", email: "carlo.reyes@example.com", role: "sales_personnel", status: "active", last_login_at: "Yesterday, 4:28 PM" },
        { id: 4, name: "Angela Garcia", email: "angela.garcia@example.com", role: "instructor", status: "inactive", last_login_at: "Oct 21, 2026" },
        { id: 5, name: "Paolo Mendoza", email: "paolo.mendoza@example.com", role: "inventory_clerk", status: "active", last_login_at: "Oct 20, 2026" },
    ];

    const roles = [
        { value: "admin", label: "Admin" },
        { value: "instructor", label: "Instructor" },
        { value: "inventory_clerk", label: "Inventory Clerk" },
        { value: "sales_personnel", label: "Sales Personnel" },
    ];

    let search = "";
    let roleFilter = "";
    let statusFilter = "";
    let showForm = false;
    let editingUserId = null;
    let loading = true;
    let successMessage = "";
    let errorMessage = "";
    let formErrors = {};
    let form = emptyForm();
    let mobileMenuOpen = false;

    function emptyForm() {
        return { name: "", email: "", role: "inventory_clerk", status: "active" };
    }

    $: filteredUsers = users.filter((user) => {
        const query = search.trim().toLowerCase();
        const matchesSearch = !query || user.name.toLowerCase().includes(query) || user.email.toLowerCase().includes(query);
        const matchesRole = !roleFilter || user.role === roleFilter;
        const matchesStatus = !statusFilter || user.status === statusFilter;
        return matchesSearch && matchesRole && matchesStatus;
    });

    $: activeCount = users.filter((user) => user.status === "active").length;

    onMount(() => {
        const timer = window.setTimeout(() => (loading = false), 350);
        return () => window.clearTimeout(timer);
    });

    function roleLabel(value) {
        return roles.find((role) => role.value === value)?.label ?? value;
    }

    function initials(name) {
        return name.split(" ").map((part) => part[0]).slice(0, 2).join("").toUpperCase();
    }

    function openCreateForm() {
        editingUserId = null;
        form = emptyForm();
        formErrors = {};
        errorMessage = "";
        successMessage = "";
        showForm = true;
    }

    function openEditForm(user) {
        editingUserId = user.id;
        form = { name: user.name, email: user.email, role: user.role, status: user.status };
        formErrors = {};
        errorMessage = "";
        successMessage = "";
        showForm = true;
    }

    function closeForm() {
        showForm = false;
        formErrors = {};
        errorMessage = "";
    }

    function validateForm() {
        const errors = {};
        if (!form.name.trim()) errors.name = "Name is required.";
        if (!form.email.trim()) errors.email = "Email is required.";
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.email = "Enter a valid email address.";
        if (!form.role) errors.role = "Select a role.";
        formErrors = errors;
        return Object.keys(errors).length === 0;
    }

    function saveUser() {
        successMessage = "";
        errorMessage = "";
        if (!validateForm()) return;

        const payload = {
            name: form.name.trim(),
            email: form.email.trim().toLowerCase(),
            role: form.role,
            status: form.status,
            last_login_at: "Not yet signed in",
        };

        if (editingUserId) {
            users = users.map((user) => user.id === editingUserId ? { ...user, ...payload } : user);
            successMessage = "User updated in this preview.";
        } else {
            const nextId = Math.max(...users.map((user) => user.id), 0) + 1;
            users = [...users, { id: nextId, ...payload }];
            successMessage = "User added to this preview.";
        }

        showForm = false;
    }

    function toggleStatus(user) {
        users = users.map((item) => item.id === user.id ? { ...item, status: item.status === "active" ? "inactive" : "active" } : item);
        successMessage = `${user.name} is now ${user.status === "active" ? "inactive" : "active"} in this preview.`;
    }
</script>

<svelte:head>
    <title>User Management | One Prompt Away</title>
</svelte:head>

<div class="flex h-screen min-h-[720px] overflow-hidden bg-slate-50 font-sans text-slate-900">
    <AppSidebar activeItem="User Management" bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="User Management" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
                <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">Workspace / User Management</p>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">User Management</h1>
                        <p class="mt-1 text-sm text-slate-500">Manage user access and account status for the store.</p>
                    </div>
                    <button type="button" class="flex w-fit items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700" on:click={openCreateForm}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Add user
                    </button>
                </div>

                <div class="mb-5 flex flex-wrap items-center gap-3 text-xs">
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">{activeCount} active users</span>
                    <span class="text-slate-400">Preview data only · changes are not persisted</span>
                </div>

                {#if successMessage}
                    <div class="mb-5 flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800" role="status">
                        <span>{successMessage}</span>
                        <button type="button" class="font-semibold hover:text-emerald-950" aria-label="Dismiss success message" on:click={() => (successMessage = "")}>Dismiss</button>
                    </div>
                {/if}
                {#if errorMessage}
                    <div class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800" role="alert">{errorMessage}</div>
                {/if}

                <section class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Store users</h2>
                            <p class="mt-1 text-xs text-slate-500">{filteredUsers.length} of {users.length} users shown</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label class="relative">
                                <span class="sr-only">Search users</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4 4" /></svg>
                                <input bind:value={search} type="search" placeholder="Search users" class="h-9 w-full rounded-md border border-slate-200 bg-white pl-9 pr-3 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-48" />
                            </label>
                            <label>
                                <span class="sr-only">Filter by role</span>
                                <select bind:value={roleFilter} class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-40">
                                    <option value="">All roles</option>
                                    {#each roles as role}<option value={role.value}>{role.label}</option>{/each}
                                </select>
                            </label>
                            <label>
                                <span class="sr-only">Filter by status</span>
                                <select bind:value={statusFilter} class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-32">
                                    <option value="">All status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    {#if loading}
                        <div class="space-y-3 p-5" aria-live="polite" aria-label="Loading users">
                            {#each Array(4) as _}<div class="h-12 animate-pulse rounded-md bg-slate-100"></div>{/each}
                        </div>
                    {:else if filteredUsers.length === 0}
                        <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4 4" /></svg></div>
                            <h3 class="mt-3 text-sm font-semibold text-slate-800">No users found</h3>
                            <p class="mt-1 max-w-xs text-xs text-slate-500">Try changing your search or filters to find another user.</p>
                        </div>
                    {:else}
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-left">
                                <thead>
                                    <tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                        <th class="px-5 py-3">User</th><th class="py-3">Role</th><th class="py-3">Status</th><th class="py-3">Last sign in</th><th class="px-5 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    {#each filteredUsers as user (user.id)}
                                        <tr class="transition-colors hover:bg-slate-50/70">
                                            <td class="px-5 py-3.5"><div class="flex items-center gap-3"><div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[10px] font-semibold text-blue-700">{initials(user.name)}</div><div class="min-w-0"><p class="truncate text-xs font-semibold text-slate-800">{user.name}</p><p class="truncate text-[11px] text-slate-400">{user.email}</p></div></div></td>
                                            <td class="py-3.5 text-xs text-slate-600">{roleLabel(user.role)}</td>
                                            <td class="py-3.5"><span class:bg-emerald-50={user.status === "active"} class:text-emerald-700={user.status === "active"} class:bg-slate-100={user.status === "inactive"} class:text-slate-500={user.status === "inactive"} class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-semibold"><i class:h-1.5={user.status === "active"} class:h-1={user.status === "inactive"} class:bg-emerald-500={user.status === "active"} class:bg-slate-400={user.status === "inactive"} class="w-1.5 rounded-full"></i>{user.status === "active" ? "Active" : "Inactive"}</span></td>
                                            <td class="py-3.5 text-xs text-slate-500">{user.last_login_at}</td>
                                            <td class="px-5 py-3.5"><div class="flex justify-end gap-1"><button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900" on:click={() => openEditForm(user)}>Edit</button><button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-900" on:click={() => toggleStatus(user)}>{user.status === "active" ? "Deactivate" : "Activate"}</button></div></td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {/if}
                </section>

                <p class="mt-4 text-[11px] text-slate-400">Mock users are displayed for layout and interaction preview. Backend persistence and authorization will be connected later.</p>
            </div>
        </main>
    </div>
</div>

{#if showForm}
    <div class="fixed inset-0 z-20 flex items-center justify-center bg-slate-950/20 p-4 sm:p-6" role="presentation" on:click={(event) => event.target === event.currentTarget && closeForm()}>
        <dialog open class="static m-0 max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-xl sm:max-h-[calc(100vh-3rem)]" aria-labelledby="user-form-title">
            <div class="flex items-start justify-between">
                <div><h2 id="user-form-title" class="text-base font-semibold text-slate-950">{editingUserId ? "Edit user" : "Add user"}</h2><p class="mt-1 text-xs text-slate-500">Preview form · no changes are sent to a backend.</p></div>
                <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close form" on:click={closeForm}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
            </div>
            <form class="mt-6 space-y-4" on:submit|preventDefault={saveUser}>
                <div><label for="user-name" class="mb-1.5 block text-xs font-medium text-slate-700">Full name</label><input id="user-name" bind:value={form.name} class:border-rose-300={formErrors.name} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="e.g. Maria Santos" /><p class:invisible={!formErrors.name} class="mt-1 text-[11px] text-rose-600">{formErrors.name || "Name is required."}</p></div>
                <div><label for="user-email" class="mb-1.5 block text-xs font-medium text-slate-700">Email address</label><input id="user-email" type="email" bind:value={form.email} class:border-rose-300={formErrors.email} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="name@example.com" /><p class:invisible={!formErrors.email} class="mt-1 text-[11px] text-rose-600">{formErrors.email || "Enter a valid email address."}</p></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="user-role" class="mb-1.5 block text-xs font-medium text-slate-700">Role</label><select id="user-role" bind:value={form.role} class:border-rose-300={formErrors.role} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{#each roles as role}<option value={role.value}>{role.label}</option>{/each}</select>{#if formErrors.role}<p class="mt-1 text-[11px] text-rose-600">{formErrors.role}</p>{/if}</div><div><label for="user-status" class="mb-1.5 block text-xs font-medium text-slate-700">Status</label><select id="user-status" bind:value={form.status} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-5"><button type="button" class="rounded-md border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" on:click={closeForm}>Cancel</button><button type="submit" class="rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700">{editingUserId ? "Save changes" : "Save user"}</button></div>
            </form>
        </dialog>
    </div>
{/if}
