<script>
    import { onMount } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import StatCard from "../Components/Shared/StatCard.svelte";
    import { getPreviewRole, previewRoles, PREVIEW_ROLE_CHANGE_EVENT } from "../Components/Config/previewRoles.js";

    // Replace this view model with Inertia props when dashboard endpoints are available.
    const stats = [
        { label: "Total Sales", value: "₱482,560", change: "12.8%", icon: "sales" },
        { label: "Products", value: "1,284", change: "4.6%", icon: "products" },
        { label: "Low Stock", value: "32", change: "8.2%", trend: "down", icon: "stock" },
        { label: "Transactions", value: "846", change: "16.4%", icon: "transactions" },
    ];

    const bestSellers = [
        { name: "Kawasaki Ninja Brake Pad", sku: "BP-KN-004", sold: "128 sold", value: "₱38,400", color: "bg-blue-100 text-blue-700" },
        { name: "Yamaha MT-15 Air Filter", sku: "AF-YM-021", sold: "96 sold", value: "₱28,800", color: "bg-emerald-100 text-emerald-700" },
        { name: "Honda Click Drive Belt", sku: "DB-HC-018", sold: "74 sold", value: "₱22,200", color: "bg-amber-100 text-amber-700" },
        { name: "Motul 7100 10W-40 1L", sku: "OL-MT-710", sold: "68 sold", value: "₱20,400", color: "bg-violet-100 text-violet-700" },
    ];

    const slowMoving = [
        { name: "Universal Chain Adjuster", sku: "CA-UNI-09", stock: 18, days: "124 days" },
        { name: "Suzuki Raider Clutch Cable", sku: "CC-SR-015", stock: 9, days: "98 days" },
        { name: "LED Signal Light Set", sku: "SL-LED-030", stock: 24, days: "87 days" },
    ];

    const inventory = [
        { label: "Healthy stock", count: 1018, percent: 79, color: "bg-emerald-500", text: "text-emerald-700" },
        { label: "Needs attention", count: 234, percent: 18, color: "bg-amber-400", text: "text-amber-700" },
        { label: "Out of stock", count: 32, percent: 3, color: "bg-rose-500", text: "text-rose-700" },
    ];

    const chartPoints = "0,132 44,118 88,125 132,91 176,102 220,71 264,84 308,58 352,69 396,42 440,53 484,27";
    const chartArea = "0,132 44,118 88,125 132,91 176,102 220,71 264,84 308,58 352,69 396,42 440,53 484,27 484,150 0,150";
    let mobileMenuOpen = false;
    let previewRole = "admin";

    $: role = previewRoles[previewRole];

    const roleStats = {
        instructor: [
            { label: "Datasets ready", value: "2", change: "100%", icon: "products" },
            { label: "Records reviewed", value: "4,350", change: "8.4%", icon: "transactions" },
            { label: "Models evaluated", value: "2", change: "1 new", icon: "chart" },
            { label: "Training status", value: "Ready", change: "0 pending", icon: "stock" },
        ],
        inventory_clerk: [
            { label: "Total Products", value: "1,284", change: "4.6%", icon: "products" },
            { label: "In Stock", value: "1,018", change: "79.3%", icon: "stock" },
            { label: "Low Stock", value: "234", change: "8.2%", trend: "down", icon: "stock" },
            { label: "Out of Stock", value: "32", change: "3.0%", trend: "down", icon: "transactions" },
        ],
        sales_personnel: [
            { label: "Total Sales", value: "₱482,560", change: "12.8%", icon: "sales" },
            { label: "Transactions", value: "846", change: "16.4%", icon: "transactions" },
            { label: "Average Transaction", value: "₱570", change: "3.1%", icon: "chart" },
        ],
    };

    const roleRecent = {
        instructor: ["Sales dataset validated", "Demand model evaluation completed", "Product demand preview refreshed"],
        inventory_clerk: ["Brake pad stock adjusted", "Low-stock review completed", "New air filter product added"],
        sales_personnel: ["TX-20261028-0001 completed", "Daily sales target reached", "New transaction recorded"],
    };

    function handleRoleChange(event) {
        previewRole = event.detail;
    }

    onMount(() => {
        previewRole = getPreviewRole();
        window.addEventListener(PREVIEW_ROLE_CHANGE_EVENT, handleRoleChange);
        return () => window.removeEventListener(PREVIEW_ROLE_CHANGE_EVENT, handleRoleChange);
    });
</script>

<svelte:head>
    <title>Dashboard | One Prompt Away</title>
</svelte:head>

<div class="flex h-screen min-h-[720px] overflow-hidden bg-slate-50 font-sans text-slate-900">
    <AppSidebar bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            {#if previewRole !== "admin"}
                <div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
                    <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="mb-2 text-xs font-medium text-slate-500">Wednesday, October 28, 2026 · {role.label} preview</p>
                            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">{role.label} dashboard</h1>
                            <p class="mt-1 text-sm text-slate-500">{role.dashboardDescription}</p>
                        </div>
                        <span class="w-fit rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-700">Preview only</span>
                    </div>
                    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label={`${role.label} key metrics`}>
                        {#each roleStats[previewRole] as stat}
                            <StatCard {...stat} />
                        {/each}
                    </section>
                    <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.9fr)]">
                        <article class="rounded-lg border border-slate-200 bg-white p-6">
                            <div class="flex items-start justify-between"><div><h2 class="text-sm font-semibold text-slate-900">{previewRole === "instructor" ? "Analysis activity" : previewRole === "inventory_clerk" ? "Inventory activity" : "Sales overview"}</h2><p class="mt-1 text-xs text-slate-500">{previewRole === "instructor" ? "Recent dataset and model workspace activity" : previewRole === "inventory_clerk" ? "Recent stock and product changes" : "Monthly revenue performance"}</p></div><a href={previewRole === "instructor" ? "/knowledge-analysis" : previewRole === "inventory_clerk" ? "/inventory" : "/sales"} class="text-xs font-semibold text-blue-600 hover:text-blue-700">Open workspace</a></div>
                            {#if previewRole === "sales_personnel"}
                                <div class="mt-5 flex items-end gap-3"><p class="text-2xl font-semibold tracking-tight text-slate-950">₱482,560</p><span class="mb-1 text-xs font-semibold text-emerald-600">+12.8%</span></div>
                                <div class="relative mt-5 h-52"><div class="absolute inset-0 flex flex-col justify-between text-[10px] text-slate-400"><div class="border-t border-dashed border-slate-200"></div><div class="border-t border-dashed border-slate-200"></div><div class="border-t border-dashed border-slate-200"></div><div class="border-t border-dashed border-slate-200"></div></div><svg viewBox="0 0 484 160" preserveAspectRatio="none" class="absolute inset-x-0 top-2 h-40 w-full" aria-label="Sales trend chart" role="img"><polyline points={chartArea} fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg><div class="absolute inset-x-0 bottom-0 flex justify-between text-[10px] text-slate-400"><span>Nov</span><span>Jan</span><span>Mar</span><span>May</span><span>Jul</span><span>Sep</span><span>Oct</span></div></div>
                            {:else}
                                <div class="mt-5 space-y-4">{#each roleRecent[previewRole] as activity, index}<div class="flex items-center gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-50 text-[10px] font-semibold text-blue-700">0{index + 1}</span><span class="text-xs text-slate-600">{activity}</span><span class="ml-auto text-[10px] text-slate-400">{index + 1}h ago</span></div>{/each}</div>
                            {/if}
                        </article>
                        <article class="rounded-lg border border-slate-200 bg-white p-6"><h2 class="text-sm font-semibold text-slate-900">{previewRole === "instructor" ? "Model evaluation" : previewRole === "inventory_clerk" ? "Stock status" : "Recent transactions"}</h2><p class="mt-1 text-xs text-slate-500">{previewRole === "instructor" ? "Latest illustrative evaluation results" : previewRole === "inventory_clerk" ? "Current stock distribution" : "Latest completed sales"}</p>
                            {#if previewRole === "instructor"}<div class="mt-6 space-y-4">{#each [{ label: "MAE", value: "4.2", description: "Average absolute error" }, { label: "RMSE", value: "6.8", description: "Weighted error score" }, { label: "MAPE", value: "12.4%", description: "Average percentage error" }] as metric}<div class="flex items-center justify-between border-b border-slate-100 pb-3 last:border-0"><div><p class="text-xs font-semibold text-slate-800">{metric.label}</p><p class="mt-1 text-[10px] text-slate-400">{metric.description}</p></div><span class="text-sm font-semibold text-slate-950">{metric.value}</span></div>{/each}</div>
                            {:else if previewRole === "inventory_clerk"}<div class="mt-7 flex items-center justify-center"><div class="relative flex h-36 w-36 items-center justify-center rounded-full" style="background: conic-gradient(#10b981 0 79%, #fbbf24 79% 97%, #f43f5e 97% 100%);"><div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white"><span class="text-2xl font-semibold text-slate-950">79%</span><span class="text-[10px] text-slate-500">healthy stock</span></div></div></div>
                            {:else}<div class="mt-5 space-y-3">{#each ["TX-20261028-0001 · ₱2,500", "TX-20261027-0002 · ₱1,040", "TX-20261026-0003 · ₱2,380"] as transaction}<div class="flex items-center justify-between border-b border-slate-100 pb-3 last:border-0"><span class="text-xs font-semibold text-slate-700">{transaction}</span><span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">Completed</span></div>{/each}</div>{/if}
                        </article>
                    </section>
                    <section class="mt-6 rounded-lg border border-blue-100 bg-blue-50/60 p-5"><p class="text-xs text-blue-800"><span class="font-semibold">Role preview —</span> This dashboard content is illustrative and does not enforce permissions. Backend authentication and authorization will define the final access policy.</p></section>
                </div>
            {:else}
            <div class="mx-auto max-w-[1480px] px-8 py-8">
                <div class="mb-7 flex items-end justify-between">
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">Wednesday, October 28, 2026</p>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Good morning, John</h1>
                        <p class="mt-1 text-sm text-slate-500">Here’s an overview of your store’s performance today.</p>
                    </div>
                    <button class="flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Add product
                    </button>
                </div>

                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key metrics">
                    {#each stats as stat}
                        <StatCard {...stat} />
                    {/each}
                </section>

                <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.9fr)]">
                    <article class="rounded-lg border border-slate-200 bg-white p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Sales overview</h2>
                                <p class="mt-1 text-xs text-slate-500">Monthly revenue performance</p>
                            </div>
                            <div class="flex items-center gap-1 rounded-md border border-slate-200 p-1 text-[11px]">
                                <button class="rounded bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">12 months</button>
                                <button class="px-2.5 py-1 text-slate-400">30 days</button>
                            </div>
                        </div>
                        <div class="mt-5 flex items-end gap-3">
                            <p class="text-2xl font-semibold tracking-tight text-slate-950">₱482,560</p>
                            <span class="mb-1 text-xs font-semibold text-emerald-600">+12.8%</span>
                        </div>
                        <div class="relative mt-5 h-52">
                            <div class="absolute inset-0 flex flex-col justify-between text-[10px] text-slate-400">
                                <div class="border-t border-dashed border-slate-200"></div><div class="border-t border-dashed border-slate-200"></div><div class="border-t border-dashed border-slate-200"></div><div class="border-t border-dashed border-slate-200"></div>
                            </div>
                            <svg viewBox="0 0 484 160" preserveAspectRatio="none" class="absolute inset-x-0 top-2 h-40 w-full overflow-visible" aria-label="Sales trend chart" role="img">
                                <defs><linearGradient id="salesFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#2563eb" stop-opacity=".16" /><stop offset="100%" stop-color="#2563eb" stop-opacity="0" /></linearGradient></defs>
                                <polyline points={chartArea} fill="url(#salesFill)" stroke="none" />
                                <polyline points={chartPoints} fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="396" cy="42" r="4" fill="white" stroke="#2563eb" stroke-width="2" />
                            </svg>
                            <div class="absolute inset-x-0 bottom-0 flex justify-between text-[10px] text-slate-400"><span>Nov</span><span>Jan</span><span>Mar</span><span>May</span><span>Jul</span><span>Sep</span><span>Oct</span></div>
                        </div>
                        <div class="mt-5 flex items-center gap-5 text-[11px] text-slate-500"><span class="flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-blue-600"></i>Historical sales</span><span class="flex items-center gap-2"><i class="h-2 w-2 rounded-full border border-dashed border-slate-400"></i>Forecast</span></div>
                    </article>

                    <article class="rounded-lg border border-slate-200 bg-white p-6">
                        <div class="flex items-start justify-between">
                            <div><h2 class="text-sm font-semibold text-slate-900">Inventory health</h2><p class="mt-1 text-xs text-slate-500">Current stock distribution</p></div>
                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">Good standing</span>
                        </div>
                        <div class="mt-7 flex items-center justify-center">
                            <div class="relative flex h-36 w-36 items-center justify-center rounded-full" style="background: conic-gradient(#10b981 0 79%, #fbbf24 79% 97%, #f43f5e 97% 100%);">
                                <div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white"><span class="text-2xl font-semibold text-slate-950">79%</span><span class="text-[10px] text-slate-500">healthy stock</span></div>
                            </div>
                        </div>
                        <div class="mt-7 space-y-3">
                            {#each inventory as item}
                                <div class="flex items-center gap-2.5 text-xs"><i class={`h-2 w-2 rounded-full ${item.color}`}></i><span class="flex-1 text-slate-600">{item.label}</span><span class={`font-semibold ${item.text}`}>{item.count}</span><span class="w-8 text-right text-slate-400">{item.percent}%</span></div>
                            {/each}
                        </div>
                    </article>
                </section>

                <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
                    <article class="rounded-lg border border-slate-200 bg-white p-6">
                        <div class="mb-5 flex items-center justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Best-selling parts</h2><p class="mt-1 text-xs text-slate-500">Top performers this month</p></div><button type="button" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View all</button></div>
                        <div class="divide-y divide-slate-100">
                            {#each bestSellers as product, i}
                                <div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                    <span class="w-4 text-xs font-semibold text-slate-400">0{i + 1}</span><div class={`flex h-9 w-9 items-center justify-center rounded-md text-xs font-bold ${product.color}`}>{product.name.slice(0, 2).toUpperCase()}</div><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-800">{product.name}</p><p class="mt-0.5 text-[10px] text-slate-400">{product.sku} · {product.sold}</p></div><span class="text-xs font-semibold text-slate-700">{product.value}</span>
                                </div>
                            {/each}
                        </div>
                    </article>

                    <article class="rounded-lg border border-slate-200 bg-white p-6">
                        <div class="mb-5 flex items-center justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Slow-moving products</h2><p class="mt-1 text-xs text-slate-500">Products needing attention</p></div><button type="button" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View inventory</button></div>
                        <div class="overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="pb-3">Product</th><th class="pb-3">In stock</th><th class="pb-3 text-right">No sale for</th></tr></thead><tbody class="divide-y divide-slate-100">
                            {#each slowMoving as product}<tr><td class="py-3"><p class="text-xs font-semibold text-slate-700">{product.name}</p><p class="mt-0.5 text-[10px] text-slate-400">{product.sku}</p></td><td class="py-3 text-xs text-slate-600">{product.stock} units</td><td class="py-3 text-right text-xs font-medium text-amber-600">{product.days}</td></tr>{/each}
                        </tbody></table></div>
                    </article>
                </section>
            </div>
            {/if}
        </main>
    </div>
</div>
