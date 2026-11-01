<script>
    import { router } from "@inertiajs/svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import StatCard from "../Components/Shared/StatCard.svelte";
    import TracktorSalesChart from "../Components/Shared/TracktorSalesChart.svelte";

    export let auth = { user: null };
    export let dashboard = {
        range: 30,
        total_sales: 0,
        transactions: 0,
        products: 0,
        low_stock: 0,
        out_of_stock: 0,
        inventory_health: { healthy: 0, low_stock: 0, out_of_stock: 0 },
        sales_trend: [],
        best_sellers: [],
        slow_moving: [],
    };

    let mobileMenuOpen = false;
    let selectedRange = dashboard.range ?? 30;
    let loading = false;

    $: totalInventory = dashboard.products || 0;
    $: healthyPercent = totalInventory ? Math.round((dashboard.inventory_health.healthy / totalInventory) * 100) : 0;
    $: lowStockPercent = totalInventory ? Math.round((dashboard.inventory_health.low_stock / totalInventory) * 100) : 0;
    $: outOfStockPercent = totalInventory ? Math.max(100 - healthyPercent - lowStockPercent, 0) : 0;
    $: inventory = [
        { label: "Healthy stock", count: dashboard.inventory_health.healthy, percent: healthyPercent, color: "bg-emerald-500", text: "text-emerald-700" },
        { label: "Needs attention", count: dashboard.inventory_health.low_stock, percent: lowStockPercent, color: "bg-amber-400", text: "text-amber-700" },
        { label: "Out of stock", count: dashboard.inventory_health.out_of_stock, percent: outOfStockPercent, color: "bg-rose-500", text: "text-rose-700" },
    ];
    $: stats = [
        { label: "Total Sales", value: formatPrice(dashboard.total_sales), change: "Completed sales", icon: "sales" },
        { label: "Products", value: dashboard.products.toLocaleString(), change: "Catalog items", icon: "products" },
        { label: "Low Stock", value: dashboard.low_stock.toLocaleString(), change: "Above zero stock", trend: "down", icon: "stock" },
        { label: "Transactions", value: dashboard.transactions.toLocaleString(), change: "Completed sales", icon: "transactions" },
    ];

    function formatPrice(value) {
        return `₱${Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function formatDate(value) {
        return new Date(`${value}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" });
    }

    function openProductForm() {
        router.visit("/inventory?create=1");
    }

    function selectRange(range) {
        if (range === selectedRange) return;
        selectedRange = range;
        loading = true;
        router.get("/dashboard", { range }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (loading = false),
        });
    }
</script>

<svelte:head><title>Dashboard | One Prompt Away</title></svelte:head>

<div class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen">
    <AppSidebar {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-7">
                <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">Store workspace · {auth?.user?.role ?? "User"}</p>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-950">Good morning, {auth?.user?.name ?? "there"}</h1>
                        <p class="mt-1 text-sm text-slate-500">Here’s an overview of your store’s performance today.</p>
                    </div>
                    {#if ["admin", "inventory_clerk"].includes(auth?.user?.role)}
                        <button type="button" class="flex w-fit items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700" onclick={openProductForm}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            Add product
                        </button>
                    {/if}
                </div>

                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key metrics">
                    {#each stats as stat}<StatCard {...stat} />{/each}
                </section>

                <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.9fr)]">
                    <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between">
                            <div><h2 class="text-sm font-semibold text-slate-900">Sales overview</h2><p class="mt-1 text-xs text-slate-500">Completed sales from the selected period</p></div>
                            <div class="flex items-center gap-1 rounded-md border border-slate-200 p-1 text-[11px]">
                                <button type="button" class:font-semibold={selectedRange === 365} class:bg-slate-100={selectedRange === 365} class="rounded px-2.5 py-1 text-slate-700" onclick={() => selectRange(365)}>12 months</button>
                                <button type="button" class:font-semibold={selectedRange === 30} class:bg-slate-100={selectedRange === 30} class="rounded px-2.5 py-1 text-slate-700" onclick={() => selectRange(30)}>30 days</button>
                            </div>
                        </div>
                        {#if dashboard.sales_trend.length}
                            <div class="mt-5 flex items-end gap-3"><p class="text-2xl font-semibold tracking-tight text-slate-950">{formatPrice(dashboard.total_sales)}</p><span class="mb-1 text-xs text-slate-500">All completed sales</span></div>
                            <TracktorSalesChart rows={dashboard.sales_trend} formatValue={formatPrice} formatDate={formatDate} />
                            <div class="mt-5 flex items-center gap-5 text-[11px] text-slate-500"><span class="flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-blue-600"></i>Historical sales</span><span class="text-slate-400">No forecast configured</span></div>
                        {:else}
                            <div class="flex h-64 items-center justify-center text-center"><div><p class="text-sm font-semibold text-slate-700">No completed sales yet</p><p class="mt-1 text-xs text-slate-500">Sales history will appear here after the first completed transaction.</p></div></div>
                        {/if}
                    </article>
                    <article class="rounded-lg border border-slate-200 bg-white p-6"><div class="flex items-start justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Inventory health</h2><p class="mt-1 text-xs text-slate-500">Current stock distribution</p></div><span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">{totalInventory ? "Current" : "No products"}</span></div><div class="mt-7 flex items-center justify-center"><div class="relative flex h-36 w-36 items-center justify-center rounded-full" style={`background: conic-gradient(#10b981 0 ${healthyPercent}%, #fbbf24 ${healthyPercent}% ${healthyPercent + lowStockPercent}%, #f43f5e ${healthyPercent + lowStockPercent}% 100%);`}><div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white"><span class="text-2xl font-semibold text-slate-950">{healthyPercent}%</span><span class="text-[10px] text-slate-500">healthy stock</span></div></div></div><div class="mt-7 space-y-3">{#each inventory as item}<div class="flex items-center gap-2.5 text-xs"><i class={`h-2 w-2 rounded-full ${item.color}`}></i><span class="flex-1 text-slate-600">{item.label}</span><span class={`font-semibold ${item.text}`}>{item.count}</span><span class="w-8 text-right text-slate-400">{item.percent}%</span></div>{/each}</div></article>
                </section>

                {#if auth?.user?.role === "admin"}
                    <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
                        <article class="rounded-lg border border-slate-200 bg-white p-6"><div class="mb-5 flex items-center justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Best-selling parts</h2><p class="mt-1 text-xs text-slate-500">Completed sale quantities across the catalog</p></div><a href="/inventory?sort=best_selling" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View all</a></div>{#if dashboard.best_sellers.length}<div class="divide-y divide-slate-100">{#each dashboard.best_sellers as product, i}<div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"><span class="w-4 text-xs font-semibold text-slate-400">0{i + 1}</span><div class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-100 text-xs font-bold text-blue-700">{product.name.slice(0, 2).toUpperCase()}</div><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-800">{product.name}</p><p class="mt-0.5 text-[10px] text-slate-400">{product.sku} · {product.units_sold} sold</p></div><span class="text-xs font-semibold text-slate-700">{formatPrice(product.revenue)}</span></div>{/each}</div>{:else}<p class="py-10 text-center text-xs text-slate-500">No completed sale items yet.</p>{/if}</article>
                        <article class="rounded-lg border border-slate-200 bg-white p-6"><div class="mb-5 flex items-center justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Slow-moving products</h2><p class="mt-1 text-xs text-slate-500">No completed sale in the last 90 days; never-sold items included</p></div><a href="/inventory?status=slow_moving" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View inventory</a></div>{#if dashboard.slow_moving.length}<div class="overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="pb-3">Product</th><th class="pb-3">In stock</th><th class="pb-3 text-right">No sale for</th></tr></thead><tbody class="divide-y divide-slate-100">{#each dashboard.slow_moving as product}<tr><td class="py-3"><p class="text-xs font-semibold text-slate-700">{product.name}</p><p class="mt-0.5 text-[10px] text-slate-400">{product.sku}</p></td><td class="py-3 text-xs text-slate-600">{product.stock} units</td><td class="py-3 text-right text-xs font-medium text-amber-600">{product.days_without_sale === null ? "Never" : `${product.days_without_sale} days`}</td></tr>{/each}</tbody></table></div>{:else}<p class="py-10 text-center text-xs text-slate-500">No slow-moving products found.</p>{/if}</article>
                    </section>
                {/if}
                {#if auth?.user?.role !== "admin"}
                    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">
                                    {auth?.user?.role === "instructor" ? "Knowledge analysis" : auth?.user?.role === "inventory_clerk" ? "Inventory operations" : "Sales operations"}
                                </h2>
                                <p class="mt-1 text-xs text-slate-500">
                                    {auth?.user?.role === "instructor" ? "Review the available analysis workspace and datasets." : auth?.user?.role === "inventory_clerk" ? "Review stock levels and manage catalog products." : "Review completed transactions and record a new sale."}
                                </p>
                            </div>
                            <a class="w-fit rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" href={auth?.user?.role === "instructor" ? "/knowledge-analysis" : auth?.user?.role === "inventory_clerk" ? "/inventory" : "/sales"}>
                                Open workspace
                            </a>
                        </div>
                    </section>
                {/if}
            </div>
        </main>
    </div>
</div>
