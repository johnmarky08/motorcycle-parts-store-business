<script>
    import { router } from "@inertiajs/svelte";
    import { fade } from "svelte/transition";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import { pop } from "../lib/transitions.js";

    export let transactions = { data: [], current_page: 1, last_page: 1, total: 0 };
    export let filters = {};
    export let summary = { transactions: 0, total: 0, average: 0 };
    export let paymentMethods = [];
    export let auth = { user: null };

    let mobileMenuOpen = false;
    let search = filters.search ?? "";
    let paymentMethod = filters.payment_method ?? "";
    let dateFrom = filters.date_from ?? "";
    let dateTo = filters.date_to ?? "";
    let detail = null;
    let filterError = "";

    $: rows = transactions.data ?? [];

    function formatPrice(value) {
        return `₱${Number(value ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function formatDate(value) {
        return new Date(value).toLocaleString("en-PH", { dateStyle: "medium", timeStyle: "short" });
    }

    function visit(page = 1) {
        filterError = "";
        if (dateFrom && dateTo && dateFrom > dateTo) {
            filterError = "The “From” date must be on or before the “To” date.";
            return;
        }
        router.get("/sales", {
            search: search.trim() || undefined,
            payment_method: paymentMethod || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            page: page > 1 ? page : undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function reset() {
        search = paymentMethod = dateFrom = dateTo = "";
        visit();
    }
</script>

<svelte:head><title>Sales Management | One Prompt Away</title></svelte:head>

<div class="flex min-h-screen overflow-x-hidden bg-slate-50 font-sans text-slate-900 lg:h-screen">
    <AppSidebar activeItem="Sales Management" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Sales Management" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
                <div class="mb-7">
                    <p class="mb-2 text-xs font-medium text-slate-500">Workspace / Sales</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Sales Management</h1>
                    <p class="mt-1 text-sm text-slate-500">Every order placed by customers in the shop appears here automatically. This page is read-only.</p>
                </div>

                <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3" aria-label="Sales Summary">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs text-slate-500">Total Sales</p><p class="mt-2 text-2xl font-semibold">{formatPrice(summary.total)}</p><p class="mt-3 text-[11px] text-slate-400">Matching The Current Filters</p></article>
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs text-slate-500">Transactions</p><p class="mt-2 text-2xl font-semibold">{summary.transactions.toLocaleString()}</p><p class="mt-3 text-[11px] text-slate-400">Matching The Current Filters</p></article>
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs text-slate-500">Average Transaction</p><p class="mt-2 text-2xl font-semibold">{formatPrice(summary.average)}</p><p class="mt-3 text-[11px] text-slate-400">Total Divided By Transactions</p></article>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <form class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,0.8fr)_auto]" on:submit|preventDefault={() => visit()}>
                        <input bind:value={search} type="search" placeholder="Transaction Or Customer" aria-label="Search Transactions" class="h-9 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                        <select bind:value={paymentMethod} aria-label="Payment Method" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">All Payment Methods</option>
                            {#each paymentMethods as method}<option value={method}>{method}</option>{/each}
                        </select>
                        <label class="flex items-center gap-2 text-[11px] font-medium text-slate-500">From <input bind:value={dateFrom} type="date" max={dateTo || undefined} aria-label="From Date" class="h-9 min-w-0 flex-1 rounded-md border border-slate-200 px-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /></label>
                        <label class="flex items-center gap-2 text-[11px] font-medium text-slate-500">To <input bind:value={dateTo} type="date" min={dateFrom || undefined} aria-label="To Date" class="h-9 min-w-0 flex-1 rounded-md border border-slate-200 px-2 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /></label>
                        <div class="flex gap-2"><Button type="submit" className="h-9 text-xs">Apply</Button><Button type="button" variant="outline" className="h-9 text-xs" onclick={reset}>Reset</Button></div>
                    </form>
                    {#if filterError}<p class="border-b border-rose-100 bg-rose-50 px-4 py-2 text-xs text-rose-700" role="alert">{filterError}</p>{/if}

                    {#if rows.length === 0}
                        <div class="flex min-h-56 items-center justify-center px-6 text-center text-xs text-slate-500">No transactions match these filters.</div>
                    {:else}
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[820px] text-left">
                                <thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="px-5 py-3">Transaction</th><th class="px-3 py-3">Date</th><th class="px-3 py-3">Customer</th><th class="px-3 py-3">Payment</th><th class="px-3 py-3 text-right">Items</th><th class="px-3 py-3 text-right">Total</th><th class="px-5 py-3 text-right">Details</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    {#each rows as sale (sale.id)}
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="px-5 py-3.5 text-xs font-semibold text-slate-800">{sale.transaction_number}</td>
                                            <td class="px-3 py-3.5 text-xs text-slate-500">{formatDate(sale.transaction_date)}</td>
                                            <td class="px-3 py-3.5 text-xs text-slate-700">{sale.customer_name}</td>
                                            <td class="px-3 py-3.5 text-xs text-slate-500">{sale.payment_method}</td>
                                            <td class="px-3 py-3.5 text-right text-xs text-slate-500">{sale.items.reduce((sum, item) => sum + item.quantity, 0)}</td>
                                            <td class="px-3 py-3.5 text-right text-xs font-semibold text-slate-800">{formatPrice(sale.total_amount)}</td>
                                            <td class="px-5 py-3.5 text-right"><button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-blue-600 hover:bg-blue-50" on:click={() => (detail = sale)}>View</button></td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                            <span>Page {transactions.current_page} Of {transactions.last_page}</span>
                            <div class="flex gap-2">
                                <Button type="button" variant="outline" className="h-8 text-xs" disabled={transactions.current_page <= 1} onclick={() => visit(transactions.current_page - 1)}>Previous</Button>
                                <Button type="button" variant="outline" className="h-8 text-xs" disabled={transactions.current_page >= transactions.last_page} onclick={() => visit(transactions.current_page + 1)}>Next</Button>
                            </div>
                        </div>
                    {/if}
                </section>
            </div>
        </main>
    </div>
</div>

{#if detail}
    <div transition:fade={{ duration: 160 }} class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4" role="presentation" on:click={(event) => event.target === event.currentTarget && (detail = null)}>
        <dialog open transition:pop|global class="m-0 max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-6 shadow-xl" aria-labelledby="sale-detail-title">
            <div class="flex items-start justify-between gap-3"><div><h2 id="sale-detail-title" class="text-base font-semibold text-slate-950">{detail.transaction_number}</h2><p class="mt-1 text-xs text-slate-500">{formatDate(detail.transaction_date)} · {detail.payment_method}</p></div><button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close Details" on:click={() => (detail = null)}>×</button></div>
            <p class="mt-4 text-xs text-slate-500">Customer</p><p class="text-sm font-semibold text-slate-900">{detail.customer_name}</p>
            <div class="mt-4 divide-y divide-slate-100 rounded-md border border-slate-100">
                {#each detail.items as item}<div class="flex items-center justify-between gap-3 px-3 py-2.5 text-xs"><div class="min-w-0"><p class="truncate font-semibold text-slate-800">{item.product_name}</p><p class="mt-0.5 text-[10px] text-slate-400">{item.sku} · {item.quantity} × {formatPrice(item.unit_price)}</p></div><span class="shrink-0 font-semibold text-slate-800">{formatPrice(item.line_total)}</span></div>{/each}
            </div>
            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 text-sm"><span class="font-medium text-slate-600">Total</span><span class="text-lg font-semibold text-slate-950">{formatPrice(detail.total_amount)}</span></div>
        </dialog>
    </div>
{/if}
