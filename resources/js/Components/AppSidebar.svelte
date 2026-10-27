<script>
    import { onMount } from "svelte";

    export let activeItem = "Dashboard";
    export let mobileMenuOpen = false;

    const navigation = [
        { label: "Dashboard", icon: "grid", href: "/dashboard" },
        { label: "Prediction", icon: "chart", href: "/prediction" },
        { label: "User Management", icon: "users", href: "/user-management" },
    ];

    function closeDrawer() {
        mobileMenuOpen = false;
    }

    onMount(() => {
        const handleKeydown = (event) => {
            if (event.key === "Escape") closeDrawer();
        };

        window.addEventListener("keydown", handleKeydown);
        return () => {
            window.removeEventListener("keydown", handleKeydown);
            document.body.style.overflow = "";
        };
    });

    $: if (typeof document !== "undefined") {
        document.body.style.overflow = mobileMenuOpen ? "hidden" : "";
    }
</script>

<aside class="hidden w-[248px] shrink-0 flex-col border-r border-slate-200 bg-white lg:flex">
    <div class="flex h-[76px] items-center gap-3 border-b border-slate-200 px-6">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                <path d="M5 16.5h14M7 16.5a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3m6 0a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3" />
                <path d="M9 10h6" />
            </svg>
        </div>
        <div>
            <p class="text-[13px] font-semibold tracking-tight text-slate-950">One Prompt Away</p>
            <p class="mt-0.5 text-[11px] font-medium text-slate-500">Motorcycle Parts Store</p>
        </div>
    </div>

    {@render navigationContent(false)}

</aside>

{#if mobileMenuOpen}
    <div class="fixed inset-0 z-30 lg:hidden" role="presentation" on:click={(event) => event.target === event.currentTarget && closeDrawer()}>
        <div class="absolute inset-0 bg-slate-950/20"></div>
        <div class="relative flex h-full w-[min(82vw,248px)] flex-col border-r border-slate-200 bg-white shadow-xl" role="dialog" aria-modal="true" aria-label="Mobile navigation">
            <div class="flex h-[76px] items-center justify-between border-b border-slate-200 px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M5 16.5h14M7 16.5a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3m6 0a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3" /><path d="M9 10h6" /></svg>
                    </div>
                    <div><p class="text-[13px] font-semibold tracking-tight text-slate-950">One Prompt Away</p><p class="mt-0.5 text-[11px] font-medium text-slate-500">Motorcycle Parts Store</p></div>
                </div>
                <button type="button" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-50 hover:text-slate-900" aria-label="Close navigation menu" on:click={closeDrawer}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>
            {@render navigationContent(true)}
        </div>
    </div>
{/if}

{#snippet navigationContent(isMobile)}
    <nav class="flex-1 px-3 py-6" aria-label="Admin navigation">
        <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Workspace</p>
        <div class="space-y-1">
            {#each navigation as item}
                {#if item.href}
                    <a href={item.href} on:click={isMobile ? closeDrawer : undefined} class:bg-slate-100={activeItem === item.label} class:text-slate-950={activeItem === item.label} class:font-semibold={activeItem === item.label} class="group flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-[13px] font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-950" aria-current={activeItem === item.label ? "page" : undefined}>
                        {@render navigationIcon(item)}<span>{item.label}</span>{#if activeItem === item.label}<span class="ml-auto h-1.5 w-1.5 rounded-full bg-blue-600"></span>{/if}
                    </a>
                {/if}
            {/each}
        </div>
    </nav>
    <div class="border-t border-slate-200 p-4">
        <div class="flex items-center gap-3 rounded-md px-2 py-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-xs font-semibold text-blue-700">JD</div>
            <div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-800">John Dela Cruz</p><p class="truncate text-[11px] text-slate-500">Administrator</p></div>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-4 w-4 text-slate-400" aria-hidden="true"><path d="m8 10 4 4 4-4" /></svg>
        </div>
    </div>
{/snippet}

{#snippet navigationIcon(item)}
    {#if item.icon === "grid"}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-[17px] w-[17px]" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1" /><rect x="14" y="4" width="6" height="6" rx="1" /><rect x="4" y="14" width="6" height="6" rx="1" /><rect x="14" y="14" width="6" height="6" rx="1" /></svg>
    {:else if item.icon === "chart"}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-[17px] w-[17px]" aria-hidden="true"><path d="M4 19V5m0 14h16" /><path d="m7 15 3-4 3 2 5-7" /></svg>
    {:else}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-[17px] w-[17px]" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3.5 19c.6-3 2.4-4.5 5.5-4.5s4.9 1.5 5.5 4.5M16 11a3 3 0 1 0-1.2-5.8M16 14.5c2.6.1 4.2 1.6 4.7 4.5" /></svg>
    {/if}
{/snippet}
