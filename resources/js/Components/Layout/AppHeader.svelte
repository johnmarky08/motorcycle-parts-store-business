<script>
    import { onMount, tick } from "svelte";
    import { page } from "@inertiajs/svelte";
    import Button from "../../lib/components/ui/Button.svelte";

    export let title = "Dashboard";
    export let mobileMenuOpen = false;

    // Notifications are built from live data on the server; "read" state is remembered per browser.
    const STORAGE_KEY = "opa-read-notifications";
    let readIds = [];

    let notificationsOpen = false;
    let notificationPanel;
    let notificationBell;

    $: notifications = (page.props.notifications ?? []).map((notification) => ({ ...notification, read: readIds.includes(notification.id) }));
    $: unreadCount = notifications.filter((notification) => !notification.read).length;

    function saveRead() {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(readIds.slice(-200)));
        } catch (error) {
            /* storage unavailable: the read state is simply not remembered */
        }
    }

    onMount(() => {
        try {
            const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || "[]");
            readIds = Array.isArray(stored) ? stored.filter((id) => typeof id === "string") : [];
        } catch (error) {
            readIds = [];
        }

        const handleDocumentClick = (event) => {
            if (notificationsOpen && notificationPanel && !notificationPanel.contains(event.target) && notificationBell && !notificationBell.contains(event.target)) {
                closeNotifications();
            }
        };

        const handleKeydown = (event) => {
            if (event.key === "Escape" && notificationsOpen) {
                closeNotifications();
            }
        };

        document.addEventListener("click", handleDocumentClick);
        window.addEventListener("keydown", handleKeydown);

        return () => {
            document.removeEventListener("click", handleDocumentClick);
            window.removeEventListener("keydown", handleKeydown);
        };
    });

    async function toggleNotifications() {
        notificationsOpen = !notificationsOpen;

        if (notificationsOpen) {
            await tick();
            notificationPanel?.querySelector("button")?.focus();
        }
    }

    function closeNotifications() {
        notificationsOpen = false;
        notificationBell?.focus();
    }

    function markAsRead(id) {
        if (!readIds.includes(id)) readIds = [...readIds, id];
        saveRead();
    }

    function markAllAsRead() {
        readIds = [...new Set([...readIds, ...(page.props.notifications ?? []).map((notification) => notification.id)])];
        saveRead();
    }

</script>

<header class="relative flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-8">
    <div class="flex items-center gap-3">
        <Button variant="ghost" size="icon" className="lg:hidden" aria-label="Open Navigation Menu" aria-expanded={mobileMenuOpen} onclick={() => (mobileMenuOpen = true)}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
        </Button>
        <div class="hidden h-5 w-px bg-slate-200 lg:block"></div>
        <p class="text-sm font-semibold tracking-tight text-slate-700">{title}</p>
    </div>
    <div class="flex items-center gap-3 sm:gap-4">
        <button
            bind:this={notificationBell}
            type="button"
            class="relative rounded-md p-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-900"
            aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ""}`}
            aria-expanded={notificationsOpen}
            aria-controls="notification-panel"
            onclick={(event) => { event.stopPropagation(); toggleNotifications(); }}
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-[19px] w-[19px]" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            {#if unreadCount > 0}
                <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-blue-600 ring-2 ring-white"></span>
            {/if}
        </button>
    </div>

    {#if notificationsOpen}
        <div
            bind:this={notificationPanel}
            id="notification-panel"
            role="dialog"
            aria-label="Notifications"
            class="absolute right-4 top-[calc(100%+8px)] z-50 w-[min(360px,calc(100vw-2rem))] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg sm:right-8"
        >
            <div class="flex items-start justify-between border-b border-slate-200 px-4 py-3.5">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Notifications</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {unreadCount > 0 ? `${unreadCount} unread notification${unreadCount === 1 ? "" : "s"}` : "You'Re All Caught Up"}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    {#if unreadCount > 0}
                        <button type="button" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700" onclick={markAllAsRead}>
                            Mark All As Read
                        </button>
                    {/if}
                    <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close Notifications" onclick={closeNotifications}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>
            </div>

            {#if notifications.length > 0}
                <div class="max-h-[min(420px,calc(100vh-150px))] overflow-y-auto">
                    {#each notifications as notification}
                        <button
                            type="button"
                            class:bg-slate-50={!notification.read}
                            class="flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-slate-50"
                            onclick={() => markAsRead(notification.id)}
                        >
                            <span class:bg-amber-50={notification.type === "stock"} class:bg-rose-50={notification.type === "critical"} class:bg-blue-50={notification.type === "sales"} class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                                {#if notification.type === "stock"}
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-amber-600" aria-hidden="true"><path d="M4 7h16v13H4zM8 7V5h8v2M8 11h8M8 15h5" /></svg>
                                {:else if notification.type === "critical"}
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-rose-600" aria-hidden="true"><path d="M12 4 3.5 19h17L12 4Z" /><path d="M12 9v4M12 16h.01" /></svg>
                                {:else}
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-blue-600" aria-hidden="true"><path d="M4 19V5m0 14h16" /><path d="m7 15 3-4 3 2 5-7" /></svg>
                                {/if}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-start justify-between gap-2">
                                    <span class="text-xs font-semibold text-slate-800">{notification.title}</span>
                                    {#if !notification.read}
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600" aria-label="Unread"></span>
                                    {/if}
                                </span>
                                <span class="mt-1 block text-[11px] leading-4 text-slate-500">{notification.description}</span>
                                <span class="mt-1.5 block text-[10px] font-medium text-slate-400">{notification.timestamp}</span>
                            </span>
                        </button>
                    {/each}
                </div>
            {:else}
                <div class="px-5 py-8 text-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="mx-auto h-8 w-8 text-slate-300" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
                    <h3 class="mt-3 text-xs font-semibold text-slate-700">No Notifications</h3>
                    <p class="mt-1 text-[11px] text-slate-500">New activity will appear here.</p>
                </div>
            {/if}
        </div>
    {/if}
</header>
