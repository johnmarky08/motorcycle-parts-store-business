<script>
    import { fade } from "svelte/transition";
    import { pop } from "../lib/transitions.js";
    import { router } from "@inertiajs/svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Button from "../lib/components/ui/Button.svelte";

    export let initialUsers = [];
    export let auth = { user: null };

    // Always mirror the server data so approvals, edits and status changes show immediately.
    $: users = initialUsers.map((user) => ({ ...user }));

    const roles = [
        { value: "admin", label: "Admin" },
        { value: "instructor", label: "Instructor" },
        { value: "inventory_clerk", label: "Inventory Clerk" },
        { value: "sales_personnel", label: "Sales Personnel" },
        { value: "user", label: "User" },
    ];

    let search = "";
    let roleFilter = "";
    let statusFilter = "";
    let showForm = false;
    let editingUserId = null;
    let loading = false;
    let successMessage = "";
    let errorMessage = "";
    let formErrors = {};
    let form = emptyForm();
    let mobileMenuOpen = false;

    function emptyForm() {
        return { name: "", email: "", role: "user", status: "active" };
    }

    $: filteredUsers = users.filter((user) => {
        const query = search.trim().toLowerCase();
        const matchesSearch =
            !query ||
            user.name.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query);
        const matchesRole = !roleFilter || user.role === roleFilter;
        const matchesStatus = !statusFilter || user.status === statusFilter;
        return matchesSearch && matchesRole && matchesStatus;
    });

    $: activeCount = users.filter((user) => user.status === "active").length;

    function formatLogin(value) {
        return value
            ? new Date(value).toLocaleString("en-PH", {
                  dateStyle: "medium",
                  timeStyle: "short",
              })
            : "Not yet signed in";
    }

    function roleLabel(value) {
        return roles.find((role) => role.value === value)?.label ?? value;
    }

    function initials(name) {
        return name
            .split(" ")
            .map((part) => part[0])
            .slice(0, 2)
            .join("")
            .toUpperCase();
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
        form = {
            name: user.name,
            email: user.email,
            role: user.role,
            status: user.status,
        };
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
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email))
            errors.email = "Enter a valid email address.";
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

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                showForm = false;
                successMessage = editingUserId
                    ? "User updated successfully."
                    : "User created successfully.";
            },
            onError: (errors) => {
                formErrors = errors;
                errorMessage = "Please correct the highlighted fields.";
            },
        };

        if (editingUserId) {
            router.put(`/user-management/${editingUserId}`, payload, options);
        } else {
            router.post("/user-management", payload, options);
        }
    }

    function toggleStatus(user) {
        router.patch(
            `/user-management/${user.id}/status`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    (successMessage = "User status updated successfully."),
            },
        );
    }

    function deleteUser(user) {
        if (
            !window.confirm(
                `Delete ${user.name}? This action cannot be undone.`,
            )
        )
            return;
        router.delete(`/user-management/${user.id}`, {
            preserveScroll: true,
            onSuccess: () => (successMessage = "User deleted successfully."),
        });
    }
</script>

<svelte:head>
    <title>User Management | One Prompt Away</title>
</svelte:head>

<div
    class="flex min-h-screen overflow-x-hidden bg-slate-50 font-sans text-slate-900 lg:h-screen"
>
    <AppSidebar activeItem="User Management" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="User Management" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-370 px-4 py-6 sm:px-8 sm:py-8">
                <div
                    class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">
                            Workspace / User Management
                        </p>
                        <h1
                            class="text-2xl font-semibold tracking-tight text-slate-950"
                        >
                            User Management
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Manage user access and account status for the store.
                        </p>
                    </div>
                    <Button
                        size="sm"
                        className="flex w-fit items-center gap-2"
                        on:click={openCreateForm}
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="h-4 w-4"
                            aria-hidden="true"
                            ><path d="M12 5v14M5 12h14" /></svg
                        >
                        Add User
                    </Button>
                </div>

                <div class="mb-5 flex flex-wrap items-center gap-3 text-xs">
                    <span
                        class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700"
                        >{activeCount} active users</span
                    >
                </div>

                {#if successMessage}
                    <div
                        class="mb-5 flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800"
                        role="status"
                    >
                        <span>{successMessage}</span>
                        <button
                            type="button"
                            class="font-semibold hover:text-emerald-950"
                            aria-label="Dismiss Success Message"
                            on:click={() => (successMessage = "")}
                            >Dismiss</button
                        >
                    </div>
                {/if}
                {#if errorMessage}
                    <div
                        class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800"
                        role="alert"
                    >
                        {errorMessage}
                    </div>
                {/if}

                <section
                    class="rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                    >
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Store Users
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                {filteredUsers.length} of {users.length} users shown
                            </p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label class="relative">
                                <span class="sr-only">Search Users</span>
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400"
                                    aria-hidden="true"
                                    ><circle cx="10.8" cy="10.8" r="6.8" /><path
                                        d="m16 16 4 4"
                                    /></svg
                                >
                                <input
                                    bind:value={search}
                                    type="search"
                                    placeholder="Search Users"
                                    class="h-9 w-full rounded-md border border-slate-200 bg-white pl-9 pr-3 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-48"
                                />
                            </label>
                            <label>
                                <span class="sr-only">Filter By Role</span>
                                <select
                                    bind:value={roleFilter}
                                    class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-40"
                                >
                                    <option value="">All Roles</option>
                                    {#each roles as role}<option
                                            value={role.value}
                                            >{role.label}</option
                                        >{/each}
                                </select>
                            </label>
                            <label>
                                <span class="sr-only">Filter By Status</span>
                                <select
                                    bind:value={statusFilter}
                                    class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-32"
                                >
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    {#if loading}
                        <div
                            class="space-y-3 p-5"
                            aria-live="polite"
                            aria-label="Loading Users"
                        >
                            {#each Array(4) as _}<div
                                    class="h-12 animate-pulse rounded-md bg-slate-100"
                                ></div>{/each}
                        </div>
                    {:else if filteredUsers.length === 0}
                        <div
                            class="flex flex-col items-center justify-center px-6 py-16 text-center"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-5 w-5"
                                    aria-hidden="true"
                                    ><circle cx="10.8" cy="10.8" r="6.8" /><path
                                        d="m16 16 4 4"
                                    /></svg
                                >
                            </div>
                            <h3
                                class="mt-3 text-sm font-semibold text-slate-800"
                            >
                                No Users Found
                            </h3>
                            <p class="mt-1 max-w-xs text-xs text-slate-500">
                                Try changing your search or filters to find
                                another user.
                            </p>
                        </div>
                    {:else}
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-180 text-left">
                                <thead>
                                    <tr
                                        class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"
                                    >
                                        <th class="px-5 py-3">User</th><th
                                            class="py-3">Role</th
                                        ><th class="py-3">Status</th><th
                                            class="py-3">Last Sign In</th
                                        ><th class="px-5 py-3 text-right"
                                            >Actions</th
                                        >
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    {#each filteredUsers as user (user.id)}
                                        <tr
                                            class="transition-colors hover:bg-slate-50/70"
                                        >
                                            <td class="px-5 py-3.5"
                                                ><div
                                                    class="flex items-center gap-3"
                                                >
                                                    <div
                                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[10px] font-semibold text-blue-700"
                                                    >
                                                        {initials(user.name)}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p
                                                            class="truncate text-xs font-semibold text-slate-800"
                                                        >
                                                            {user.name}
                                                        </p>
                                                        <p
                                                            class="truncate text-[11px] text-slate-400"
                                                        >
                                                            {user.email}
                                                        </p>
                                                    </div>
                                                </div></td
                                            >
                                            <td
                                                class="py-3.5 text-xs text-slate-600"
                                                >{roleLabel(user.role)}</td
                                            >
                                            <td class="py-3.5"
                                                ><span
                                                    class:bg-emerald-50={user.status ===
                                                        "active"}
                                                    class:text-emerald-700={user.status ===
                                                        "active"}
                                                    class:bg-slate-100={user.status !==
                                                        "active"}
                                                    class:text-slate-500={user.status !==
                                                        "active"}
                                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-semibold"
                                                    ><i
                                                        class:h-1.5={user.status ===
                                                            "active"}
                                                        class:h-1={user.status !==
                                                            "active"}
                                                        class:bg-emerald-500={user.status ===
                                                            "active"}
                                                        class:bg-slate-400={user.status !==
                                                            "active"}
                                                        class="w-1.5 rounded-full"
                                                    ></i>{user.status ===
                                                    "active"
                                                        ? "Active"
                                                        : user.status
                                                              .charAt(0)
                                                              .toUpperCase() +
                                                          user.status.slice(
                                                              1,
                                                          )}</span
                                                ></td
                                            >
                                            <td
                                                class="py-3.5 text-xs text-slate-500"
                                                >{formatLogin(
                                                    user.last_login_at,
                                                )}</td
                                            >
                                            <td class="px-5 py-3.5"
                                                ><div
                                                    class="flex justify-end gap-1"
                                                >
                                                    <button
                                                        type="button"
                                                        class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                                                        on:click={() =>
                                                            openEditForm(user)}
                                                        >Edit</button
                                                    >{#if user.status === "active" || user.status === "suspended"}<button
                                                            type="button"
                                                            class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                                                            on:click={() =>
                                                                toggleStatus(
                                                                    user,
                                                                )}
                                                            >{user.status ===
                                                            "active"
                                                                ? "Suspend"
                                                                : "Activate"}</button
                                                        >{/if}<button
                                                        type="button"
                                                        class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-rose-600 hover:bg-rose-50"
                                                        on:click={() =>
                                                            deleteUser(user)}
                                                        >Delete</button
                                                    >
                                                </div></td
                                            >
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {/if}
                </section>

                <p class="mt-4 text-[11px] text-slate-400">
                    Instructors and admins can change any account to any role.
                    You cannot change your own role or status, and at least one
                    active instructor or admin always remains.
                </p>
            </div>
        </main>
    </div>
</div>

{#if showForm}
    <div
        transition:fade={{ duration: 160 }}
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/30 p-4 sm:p-6"
        role="presentation"
        on:click={(event) =>
            event.target === event.currentTarget && closeForm()}
    >
        <div class="flex min-h-full items-center justify-center">
            <dialog
                open
                transition:pop|global
                class="m-0 max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-xl sm:max-h-[calc(100vh-3rem)]"
                aria-labelledby="user-form-title"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h2
                            id="user-form-title"
                            class="text-base font-semibold text-slate-950"
                        >
                            {editingUserId ? "Edit User" : "Add User"}
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Admin-created users start with a private random
                            password and choose their own with “Forgot
                            password”.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700"
                        aria-label="Close Form"
                        on:click={closeForm}
                        ><svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="h-5 w-5"
                            aria-hidden="true"
                            ><path d="m6 6 12 12M18 6 6 18" /></svg
                        ></button
                    >
                </div>
                <form
                    class="mt-6 space-y-4"
                    on:submit|preventDefault={saveUser}
                >
                    <div>
                        <label
                            for="user-name"
                            class="mb-1.5 block text-xs font-medium text-slate-700"
                            >Full Name</label
                        ><input
                            id="user-name"
                            bind:value={form.name}
                            class:border-rose-300={formErrors.name}
                            class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="E.G. Maria Santos"
                        />
                        <p
                            class:invisible={!formErrors.name}
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {formErrors.name || "Name is required."}
                        </p>
                    </div>
                    <div>
                        <label
                            for="user-email"
                            class="mb-1.5 block text-xs font-medium text-slate-700"
                            >Email Address</label
                        ><input
                            id="user-email"
                            type="email"
                            bind:value={form.email}
                            class:border-rose-300={formErrors.email}
                            class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Name@Example.Com"
                        />
                        <p
                            class:invisible={!formErrors.email}
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {formErrors.email || "Enter a valid email address."}
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                for="user-role"
                                class="mb-1.5 block text-xs font-medium text-slate-700"
                                >Role</label
                            ><select
                                id="user-role"
                                bind:value={form.role}
                                class:border-rose-300={formErrors.role}
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >{#each roles as role}<option value={role.value}
                                        >{role.label}</option
                                    >{/each}</select
                            >{#if formErrors.role}<p
                                    class="mt-1 text-[11px] text-rose-600"
                                >
                                    {formErrors.role}
                                </p>{/if}
                        </div>
                        <div>
                            <label
                                for="user-status"
                                class="mb-1.5 block text-xs font-medium text-slate-700"
                                >Status</label
                            ><select
                                id="user-status"
                                bind:value={form.status}
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                ><option value="active">Active</option><option
                                    value="suspended">Suspended</option
                                ></select
                            >
                        </div>
                    </div>
                    <div
                        class="flex justify-end gap-2 border-t border-slate-100 pt-5"
                    >
                        <button
                            type="button"
                            class="rounded-md border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                            on:click={closeForm}>Cancel</button
                        ><button
                            type="submit"
                            class="rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700"
                            >{editingUserId
                                ? "Save Changes"
                                : "Save User"}</button
                        >
                    </div>
                </form>
            </dialog>
        </div>
    </div>
{/if}
