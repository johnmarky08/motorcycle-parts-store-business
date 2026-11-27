<script>
    import { Link, page, router } from "@inertiajs/svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import StatCard from "../Components/Shared/StatCard.svelte";
    import TracktorSalesChart from "../Components/Shared/TracktorSalesChart.svelte";
    import { movementLabel, roleLabel } from "../lib/labels.js";

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
        sections: {
            sales: true,
            inventory: true,
            rankings: false,
            analysis: false,
        },
        recent_sales: [],
        recent_movements: [],
        low_stock_list: [],
        model_evaluations: [],
        analysis: null,
        period_sales: 0,
        period_transactions: 0,
        average_sale: 0,
        data_as_of: null,
    };

    let mobileMenuOpen = false;
    let selectedRange = dashboard.range ?? 30;
    let loading = false;

    $: totalInventory = dashboard.products || 0;
    $: healthyPercent = totalInventory
        ? Math.round(
              (dashboard.inventory_health.healthy / totalInventory) * 100,
          )
        : 0;
    $: lowStockPercent = totalInventory
        ? Math.round(
              (dashboard.inventory_health.low_stock / totalInventory) * 100,
          )
        : 0;
    $: outOfStockPercent = totalInventory
        ? Math.max(100 - healthyPercent - lowStockPercent, 0)
        : 0;
    $: inventory = [
        {
            label: "Healthy Stock",
            count: dashboard.inventory_health.healthy,
            percent: healthyPercent,
            color: "bg-emerald-500",
            text: "text-emerald-700",
        },
        {
            label: "Needs Attention",
            count: dashboard.inventory_health.low_stock,
            percent: lowStockPercent,
            color: "bg-amber-400",
            text: "text-amber-700",
        },
        {
            label: "Out Of Stock",
            count: dashboard.inventory_health.out_of_stock,
            percent: outOfStockPercent,
            color: "bg-rose-500",
            text: "text-rose-700",
        },
    ];
    $: sections = dashboard.sections ?? {};
    // Exact days covered by the period cards, e.g. "Oct 29 – Nov 27, 2026".
    $: periodLabel = dateRange(dashboard.period_start, dashboard.data_as_of);

    function dateRange(start, end) {
        if (!start || !end) return "Selected Period";
        const from = new Date(`${start}T00:00:00`);
        const to = new Date(`${end}T00:00:00`);
        const sameYear = from.getFullYear() === to.getFullYear();
        const fromText = from.toLocaleDateString("en-PH", { month: "short", day: "numeric", ...(sameYear ? {} : { year: "numeric" }) });
        const toText = to.toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
        return `${fromText} – ${toText}`;
    }
    $: stats =
        auth?.user?.role === "sales_personnel"
            ? [
                  {
                      label: "Total Sales",
                      value: formatPrice(dashboard.total_sales),
                      change: "All Completed Sales",
                      trend: "neutral",
                      icon: "sales",
                  },
                  {
                      label: `Sales (${dashboard.range} days)`,
                      value: formatPrice(dashboard.period_sales),
                      change: periodLabel,
                      trend: "neutral",
                      icon: "sales",
                  },
                  {
                      label: `Transactions (${dashboard.range} days)`,
                      value: dashboard.period_transactions.toLocaleString(),
                      change: periodLabel,
                      trend: "neutral",
                      icon: "transactions",
                  },
                  {
                      label: "Average Sale",
                      value: formatPrice(dashboard.average_sale),
                      change: "Per Transaction",
                      trend: "neutral",
                      icon: "chart",
                  },
              ]
            : auth?.user?.role === "inventory_clerk"
              ? [
                    {
                        label: "Products",
                        value: dashboard.products.toLocaleString(),
                        change: "Catalog Items",
                        trend: "neutral",
                        icon: "products",
                    },
                    {
                        label: "Healthy Stock",
                        value: dashboard.inventory_health.healthy.toLocaleString(),
                        change: `${healthyPercent}% of catalog`,
                        trend: "up",
                        icon: "stock",
                    },
                    {
                        label: "Low Stock",
                        value: dashboard.low_stock.toLocaleString(),
                        change: "Above Zero Stock",
                        trend: "down",
                        icon: "stock",
                    },
                    {
                        label: "Out Of Stock",
                        value: dashboard.out_of_stock.toLocaleString(),
                        change: "Need Restocking",
                        trend: "down",
                        icon: "transactions",
                    },
                ]
              : [
                    {
                        label: "Total Sales",
                        value: formatPrice(dashboard.total_sales),
                        change: "Sales",
                        trend: "neutral",
                        icon: "sales",
                    },
                    {
                        label: "Products",
                        value: dashboard.products.toLocaleString(),
                        change: "Catalog Items",
                        trend: "neutral",
                        icon: "products",
                    },
                    {
                        label: "Low Stock",
                        value: dashboard.low_stock.toLocaleString(),
                        change: `${dashboard.out_of_stock} out of stock`,
                        trend: "down",
                        icon: "stock",
                    },
                    {
                        label: "Transactions",
                        value: dashboard.transactions.toLocaleString(),
                        change: "Sales",
                        trend: "neutral",
                        icon: "transactions",
                    },
                ];

    function formatDateTime(value) {
        return new Date(value).toLocaleString("en-PH", {
            dateStyle: "medium",
            timeStyle: "short",
        });
    }

    function formatPrice(value) {
        return `₱${Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    // The chart passes a Date object while other callers pass "YYYY-MM-DD"; accept both.
    function formatDate(value) {
        const date =
            value instanceof Date
                ? value
                : new Date(`${String(value).slice(0, 10)}T00:00:00`);
        return Number.isNaN(date.getTime())
            ? ""
            : date.toLocaleDateString("en-PH", {
                  month: "short",
                  day: "numeric",
                  year: "numeric",
              });
    }

    // Short x-axis labels: the day for the 30-day view, the month and year for the 365-day view.
    // Reactive so the chart redraws its axis when the range changes.
    $: formatAxisDate = ((yearly) => (value) => {
        const date = value instanceof Date ? value : new Date(`${String(value).slice(0, 10)}T00:00:00`);
        if (Number.isNaN(date.getTime())) return "";
        return yearly
            ? date.toLocaleDateString("en-PH", { month: "short", year: "numeric" })
            : date.toLocaleDateString("en-PH", { month: "short", day: "numeric" });
    })(dashboard.range === 365);

    // "Good morning / afternoon / evening" from the viewer's own clock.
    function greeting() {
        const hour = new Date().getHours();
        if (hour < 12) return "Good morning";
        if (hour < 18) return "Good afternoon";
        return "Good evening";
    }

    // Only offer links to pages this role may open (otherwise the server answers 403).
    $: allowed = (page.props.navigation ?? []).map((item) => item.href);
    function canOpen(path) {
        return allowed.includes(path);
    }

    function openProductForm() {
        router.visit("/inventory?create=1");
    }

    function selectRange(range) {
        if (range === selectedRange) return;
        selectedRange = range;
        loading = true;
        router.get(
            "/dashboard",
            { range },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => (loading = false),
            },
        );
    }
</script>

<svelte:head><title>Dashboard | One Prompt Away</title></svelte:head>

<div
    class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen"
>
    <AppSidebar {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="w-full px-4 py-6 sm:px-8 sm:py-7">
                <div
                    class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">
                            Store Workspace · {auth?.user?.role_label ?? roleLabel(auth?.user?.role)}
                        </p>
                        <h1
                            class="text-2xl font-bold tracking-tight text-slate-950"
                        >
                            {greeting()}, {auth?.user?.name ?? "there"}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Here’s an overview of your workspace.{#if dashboard.data_as_of && sections.sales}{" "}Sales data as of {formatDate(dashboard.data_as_of)}.{/if}
                        </p>
                    </div>
                    {#if auth?.user?.role === "inventory_clerk"}
                        <button
                            type="button"
                            class="flex w-fit items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700"
                            onclick={openProductForm}
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
                            Add Product
                        </button>
                    {/if}
                </div>

                <section
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Key Metrics"
                >
                    {#each stats as stat}<StatCard {...stat} />{/each}
                </section>

                <section
                    class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.9fr)]"
                >
                    {#if sections.sales}
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Sales Overview
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Sales From The Selected Period
                                    </p>
                                </div>
                                <div
                                    class="flex items-center gap-1 rounded-md border border-slate-200 p-1 text-[11px]"
                                >
                                    <button
                                        type="button"
                                        class:font-semibold={selectedRange ===
                                            365}
                                        class:bg-slate-100={selectedRange ===
                                            365}
                                        class="rounded px-2.5 py-1 text-slate-700"
                                        onclick={() => selectRange(365)}
                                        >12 Months</button
                                    >
                                    <button
                                        type="button"
                                        class:font-semibold={selectedRange ===
                                            30}
                                        class:bg-slate-100={selectedRange ===
                                            30}
                                        class="rounded px-2.5 py-1 text-slate-700"
                                        onclick={() => selectRange(30)}
                                        >30 Days</button
                                    >
                                </div>
                            </div>
                            {#if dashboard.sales_trend.length}
                                <div class="mt-5 flex items-end gap-3">
                                    <p
                                        class="text-2xl font-semibold tracking-tight text-slate-950"
                                    >
                                        {formatPrice(dashboard.total_sales)}
                                    </p>
                                    <span class="mb-1 text-xs text-slate-500"
                                        >All Completed Sales</span
                                    >
                                </div>
                                <TracktorSalesChart
                                    rows={dashboard.sales_trend}
                                    formatValue={formatPrice}
                                    {formatDate}
                                    {formatAxisDate}
                                />
                                <div
                                    class="mt-5 flex items-center gap-5 text-[11px] text-slate-500"
                                >
                                    <span class="flex items-center gap-2"
                                        ><i
                                            class="h-2 w-2 rounded-full bg-blue-600"
                                        ></i>Historical Sales</span
                                    ><span class="text-slate-400"
                                        >No Forecast Configured</span
                                    >
                                </div>
                            {:else}
                                <div
                                    class="flex h-64 items-center justify-center text-center"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-slate-700"
                                        >
                                            No Completed Sales Yet
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Sales history will appear here after
                                            the first completed transaction.
                                        </p>
                                    </div>
                                </div>
                            {/if}
                        </article>
                    {:else if auth?.user?.role === "inventory_clerk"}
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Inventory Activity
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Latest Stock Movements
                                    </p>
                                </div>
                                {#if canOpen("/inventory")}<Link
                                        href="/inventory"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                                        >Open Inventory</Link
                                    >{/if}
                            </div>
                            <div class="mt-5 space-y-3">
                                {#each dashboard.recent_movements as movement}<div
                                        class="flex items-center gap-3 border-b border-slate-100 pb-3 last:border-0"
                                    >
                                        <span
                                            class:bg-emerald-50={movement.quantity >
                                                0}
                                            class:text-emerald-700={movement.quantity >
                                                0}
                                            class:bg-rose-50={movement.quantity <
                                                0}
                                            class:text-rose-700={movement.quantity <
                                                0}
                                            class="flex h-8 w-11 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >{movement.quantity > 0
                                                ? "+"
                                                : ""}{movement.quantity}</span
                                        >
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-xs font-semibold text-slate-700"
                                            >
                                                {movement.product}
                                            </p>
                                            <p
                                                class="mt-0.5 truncate text-[10px] text-slate-400"
                                            >
                                                {movementLabel(
                                                    movement.type,
                                                )}{movement.user
                                                    ? ` · ${movement.user}`
                                                    : ""} · {movement.note}
                                            </p>
                                        </div>
                                        <span
                                            class="shrink-0 text-[10px] text-slate-400"
                                            >{movement.at}</span
                                        >
                                    </div>{:else}<p
                                        class="py-8 text-center text-xs text-slate-500"
                                    >
                                        No stock movements yet.
                                    </p>{/each}
                            </div>
                        </article>
                    {/if}
                    {#if sections.inventory}
                        <article
                            class="rounded-lg border border-slate-200 bg-white p-6"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Inventory Health
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Current Stock Distribution
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700"
                                    >{totalInventory
                                        ? "Current"
                                        : "No Products"}</span
                                >
                            </div>
                            <div class="mt-7 flex items-center justify-center">
                                <div
                                    class="relative flex h-36 w-36 items-center justify-center rounded-full"
                                    style={`background: conic-gradient(#10b981 0 ${healthyPercent}%, #fbbf24 ${healthyPercent}% ${healthyPercent + lowStockPercent}%, #f43f5e ${healthyPercent + lowStockPercent}% 100%);`}
                                >
                                    <div
                                        class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white"
                                    >
                                        <span
                                            class="text-2xl font-semibold text-slate-950"
                                            >{healthyPercent}%</span
                                        ><span
                                            class="text-[10px] text-slate-500"
                                            >Healthy Stock</span
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="mt-7 space-y-3">
                                {#each inventory as item}<div
                                        class="flex items-center gap-2.5 text-xs"
                                    >
                                        <i
                                            class={`h-2 w-2 rounded-full ${item.color}`}
                                        ></i><span class="flex-1 text-slate-600"
                                            >{item.label}</span
                                        ><span
                                            class={`font-semibold ${item.text}`}
                                            >{item.count}</span
                                        ><span
                                            class="w-8 text-right text-slate-400"
                                            >{item.percent}%</span
                                        >
                                    </div>{/each}
                            </div>
                        </article>
                    {:else}
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Recent Transactions
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Latest Completed Sales
                                    </p>
                                </div>
                                {#if canOpen("/sales")}<Link
                                        href="/sales"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                                        >Open Sales</Link
                                    >{/if}
                            </div>
                            <div class="mt-5 space-y-3">
                                {#each dashboard.recent_sales as sale}<div
                                        class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3 last:border-0"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-semibold text-slate-700"
                                            >
                                                {sale.transaction_number}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[10px] text-slate-400"
                                            >
                                                {formatDateTime(sale.date)} · {sale.payment_method}
                                            </p>
                                        </div>
                                        <p
                                            class="text-xs font-semibold text-slate-800"
                                        >
                                            {formatPrice(sale.total)}
                                        </p>
                                    </div>{:else}<p
                                        class="py-8 text-center text-xs text-slate-500"
                                    >
                                        No transactions yet.
                                    </p>{/each}
                            </div>
                        </article>
                    {/if}
                </section>

                {#if sections.rankings}
                    <section
                        class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]"
                    >
                        <article
                            class="rounded-lg border border-slate-200 bg-white p-6"
                        >
                            <div class="mb-5 flex items-center justify-between">
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Best-Selling Parts
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Completed Sale Quantities Across The
                                        Catalog
                                    </p>
                                </div>
                                {#if canOpen("/inventory")}<Link
                                        href="/inventory?sort=best_selling"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                                        >View All</Link
                                    >{/if}
                            </div>
                            {#if dashboard.best_sellers.length}<div
                                    class="divide-y divide-slate-100"
                                >
                                    {#each dashboard.best_sellers as product, i}<div
                                            class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                        >
                                            <span
                                                class="w-4 text-xs font-semibold text-slate-400"
                                                >0{i + 1}</span
                                            >
                                            <div
                                                class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-100 text-xs font-bold text-blue-700"
                                            >
                                                {product.name
                                                    .slice(0, 2)
                                                    .toUpperCase()}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="truncate text-xs font-semibold text-slate-800"
                                                >
                                                    {product.name}
                                                </p>
                                                <p
                                                    class="mt-0.5 text-[10px] text-slate-400"
                                                >
                                                    {product.sku} · {product.units_sold}
                                                    sold
                                                </p>
                                            </div>
                                            <span
                                                class="text-xs font-semibold text-slate-700"
                                                >{formatPrice(
                                                    product.revenue,
                                                )}</span
                                            >
                                        </div>{/each}
                                </div>{:else}<p
                                    class="py-10 text-center text-xs text-slate-500"
                                >
                                    No completed sale items yet.
                                </p>{/if}
                        </article>
                        <article
                            class="rounded-lg border border-slate-200 bg-white p-6"
                        >
                            <div class="mb-5 flex items-center justify-between">
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Slow-Moving Products
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        No completed sale in the last 90 days;
                                        never-sold items included
                                    </p>
                                </div>
                                {#if canOpen("/inventory")}<Link
                                        href="/inventory?status=slow_moving"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                                        >View Inventory</Link
                                    >{/if}
                            </div>
                            {#if dashboard.slow_moving.length}<div
                                    class="overflow-x-auto"
                                >
                                    <table class="w-full text-left">
                                        <thead
                                            ><tr
                                                class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"
                                                ><th class="pb-3">Product</th
                                                ><th class="pb-3">In Stock</th
                                                ><th class="pb-3 text-right"
                                                    >No Sale For</th
                                                ></tr
                                            ></thead
                                        ><tbody
                                            class="divide-y divide-slate-100"
                                            >{#each dashboard.slow_moving as product}<tr
                                                    ><td class="py-3"
                                                        ><p
                                                            class="text-xs font-semibold text-slate-700"
                                                        >
                                                            {product.name}
                                                        </p>
                                                        <p
                                                            class="mt-0.5 text-[10px] text-slate-400"
                                                        >
                                                            {product.sku}
                                                        </p></td
                                                    ><td
                                                        class="py-3 text-xs text-slate-600"
                                                        >{product.stock} units</td
                                                    ><td
                                                        class="py-3 text-right text-xs font-medium text-amber-600"
                                                        >{product.days_without_sale ===
                                                        null
                                                            ? "Never"
                                                            : `${product.days_without_sale} days`}</td
                                                    ></tr
                                                >{/each}</tbody
                                        >
                                    </table>
                                </div>{:else}<p
                                    class="py-10 text-center text-xs text-slate-500"
                                >
                                    No slow-moving products found.
                                </p>{/if}
                        </article>
                    </section>
                {/if}
                {#if sections.analysis && dashboard.analysis}
                    <section
                        class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]"
                    >
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Knowledge Analysis
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Datasets, Models And Predictions
                                    </p>
                                </div>
                                {#if canOpen("/knowledge-analysis")}<Link
                                        href="/knowledge-analysis"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                                        >Open Workspace</Link
                                    >{/if}
                            </div>
                            <dl class="mt-5 grid grid-cols-2 gap-3 text-xs">
                                {#each [["Datasets", dashboard.analysis.datasets], ["Records", dashboard.analysis.records.toLocaleString()], ["Saved models", dashboard.analysis.models], ["Predictions run", dashboard.analysis.predictions]] as tile}<div
                                        class="rounded-md border border-slate-100 p-3"
                                    >
                                        <dt class="text-slate-400">
                                            {tile[0]}
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm font-semibold text-slate-900"
                                        >
                                            {tile[1]}
                                        </dd>
                                    </div>{/each}
                            </dl>
                        </article>
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <h2 class="text-sm font-semibold text-slate-900">
                                Model Evaluation
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                Hold-Out Results Of The Active Models
                            </p>
                            <div class="mt-5 space-y-4">
                                {#each dashboard.model_evaluations as model}<div
                                    >
                                        <p
                                            class="text-xs font-semibold text-slate-800"
                                        >
                                            {model.name}
                                            <span
                                                class="font-normal text-slate-400"
                                                >· {model.task === "sales"
                                                    ? "Sales"
                                                    : "Demand"} · v{model.version}</span
                                            >
                                        </p>
                                        <div
                                            class="mt-2 grid grid-cols-4 gap-2"
                                        >
                                            {#each [["MAE", model.mae], ["RMSE", model.rmse], ["MAPE", model.mape === null ? "n/a" : `${model.mape}%`], ["R²", model.r2 ?? "n/a"]] as row}<div
                                                    class="rounded-md border border-slate-100 p-2"
                                                >
                                                    <p
                                                        class="text-[10px] uppercase tracking-wide text-slate-400"
                                                    >
                                                        {row[0]}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs font-semibold text-slate-900"
                                                    >
                                                        {row[1]}
                                                    </p>
                                                </div>{/each}
                                        </div>
                                    </div>{:else}<p
                                        class="py-6 text-center text-xs text-slate-500"
                                    >
                                        No trained models yet. Train one in
                                        Knowledge Analysis.
                                    </p>{/each}
                            </div>
                        </article>
                    </section>
                {/if}
            </div>
        </main>
    </div>
</div>
