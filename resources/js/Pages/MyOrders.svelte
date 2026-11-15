<script>
    import { Link, router } from "@inertiajs/svelte";
    import { fade } from "svelte/transition";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import { pop } from "../lib/transitions.js";

    export let orders = { data: [], current_page: 1, last_page: 1, total: 0 };
    export let filters = {};
    export let status = null;
    export let auth = { user: null };

    let mobileMenuOpen = false;
    let search = filters.search ?? "";
    let dateFrom = filters.date_from ?? "";
    let dateTo = filters.date_to ?? "";
    let detail = null;
    let filterError = "";

    $: rows = orders.data ?? [];

    function formatPrice(value) {
        return `₱${Number(value ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function formatDate(value) {
        return new Date(value).toLocaleString("en-PH", {
            dateStyle: "medium",
            timeStyle: "short",
        });
    }

    function visit(page = 1) {
        filterError = "";
        if (dateFrom && dateTo && dateFrom > dateTo) {
            filterError = "The “From” date must be on or before the “To” date.";
            return;
        }
        router.get(
            "/orders",
            {
                search: search.trim() || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                page: page > 1 ? page : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function reset() {
        search = dateFrom = dateTo = "";
        visit();
    }
</script>

<svelte:head><title>My Orders | One Prompt Away</title></svelte:head>

<div
    class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen"
>
    <AppSidebar activeItem="My Orders" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="My Orders" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-295 px-4 py-6 sm:px-8 sm:py-7">
                <div
                    class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">
                            Account / Orders
                        </p>
                        <h1
                            class="text-2xl font-semibold tracking-tight text-slate-950"
                        >
                            My Orders
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Every order you placed in the shop, newest first.
                        </p>
                    </div>
                    <Link
                        href="/shop"
                        class="inline-flex h-9 w-fit items-center rounded-md bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-700"
                        >Continue Shopping</Link
                    >
                </div>

                {#if status}<div
                        class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800"
                        role="status"
                    >
                        {status}
                    </div>{/if}

                <section
                    class="rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <form
                        class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                        on:submit|preventDefault={() => visit()}
                    >
                        <input
                            bind:value={search}
                            type="search"
                            placeholder="Order Number"
                            aria-label="Search Orders"
                            class="h-9 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                        <label
                            class="flex items-center gap-2 text-[11px] font-medium text-slate-500"
                            >From <input
                                bind:value={dateFrom}
                                type="date"
                                max={dateTo || undefined}
                                aria-label="From Date"
                                class="h-9 min-w-0 flex-1 rounded-md border border-slate-200 px-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            /></label
                        >
                        <label
                            class="flex items-center gap-2 text-[11px] font-medium text-slate-500"
                            >To <input
                                bind:value={dateTo}
                                type="date"
                                min={dateFrom || undefined}
                                aria-label="To Date"
                                class="h-9 min-w-0 flex-1 rounded-md border border-slate-200 px-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            /></label
                        >
                        <div class="flex gap-2">
                            <Button type="submit" className="h-9 text-xs"
                                >Apply</Button
                            ><Button
                                type="button"
                                variant="outline"
                                className="h-9 text-xs"
                                onclick={reset}>Reset</Button
                            >
                        </div>
                    </form>
                    {#if filterError}<p
                            class="border-b border-rose-100 bg-rose-50 px-4 py-2 text-xs text-rose-700"
                            role="alert"
                        >
                            {filterError}
                        </p>{/if}

                    {#if rows.length === 0}
                        <div
                            class="flex min-h-56 flex-col items-center justify-center px-6 text-center"
                        >
                            <p class="text-sm font-semibold text-slate-800">
                                No Orders Found
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Orders you place in the shop will show up here.
                            </p>
                        </div>
                    {:else}
                        <ul class="divide-y divide-slate-100">
                            {#each rows as order (order.id)}
                                <li
                                    class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {order.transaction_number}
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs text-slate-500"
                                        >
                                            {formatDate(order.transaction_date)}
                                            · {order.payment_method} · {order.items.reduce(
                                                (sum, item) =>
                                                    sum + item.quantity,
                                                0,
                                            )} Items
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <span
                                            class="text-base font-semibold text-slate-950"
                                            >{formatPrice(
                                                order.total_amount,
                                            )}</span
                                        ><button
                                            type="button"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50"
                                            on:click={() => (detail = order)}
                                            >View Order</button
                                        >
                                    </div>
                                </li>
                            {/each}
                        </ul>
                        <div
                            class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-slate-500"
                        >
                            <span
                                >Page {orders.current_page} Of {orders.last_page}</span
                            >
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-8 text-xs"
                                    disabled={orders.current_page <= 1}
                                    onclick={() =>
                                        visit(orders.current_page - 1)}
                                    >Previous</Button
                                ><Button
                                    type="button"
                                    variant="outline"
                                    className="h-8 text-xs"
                                    disabled={orders.current_page >=
                                        orders.last_page}
                                    onclick={() =>
                                        visit(orders.current_page + 1)}
                                    >Next</Button
                                >
                            </div>
                        </div>
                    {/if}
                </section>
            </div>
        </main>
    </div>
</div>

{#if detail}
    <div
        transition:fade={{ duration: 160 }}
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4"
        role="presentation"
        on:click={(event) =>
            event.target === event.currentTarget && (detail = null)}
    >
        <dialog
            open
            transition:pop|global
            class="m-0 max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-6 shadow-xl"
            aria-labelledby="order-title"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2
                        id="order-title"
                        class="text-base font-semibold text-slate-950"
                    >
                        {detail.transaction_number}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {formatDate(detail.transaction_date)} · {detail.payment_method}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700"
                    aria-label="Close Order"
                    on:click={() => (detail = null)}>×</button
                >
            </div>
            <div
                class="mt-4 divide-y divide-slate-100 rounded-md border border-slate-100"
            >
                {#each detail.items as item}<div
                        class="flex items-center justify-between gap-3 px-3 py-2.5 text-xs"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-800">
                                {item.product_name}
                            </p>
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                {item.sku} · {item.quantity} × {formatPrice(
                                    item.unit_price,
                                )}
                            </p>
                        </div>
                        <span class="shrink-0 font-semibold text-slate-800"
                            >{formatPrice(item.line_total)}</span
                        >
                    </div>{/each}
            </div>
            <div
                class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 text-sm"
            >
                <span class="font-medium text-slate-600">Total</span><span
                    class="text-lg font-semibold text-slate-950"
                    >{formatPrice(detail.total_amount)}</span
                >
            </div>
        </dialog>
    </div>
{/if}
