<script>
    import { router } from "@inertiajs/svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Badge from "../lib/components/ui/Badge.svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import Input from "../lib/components/ui/Input.svelte";

    export let transactions = { data: [], links: [], from: 0, to: 0, total: 0 };
    export let products = [];
    export let filters = {};
    export let auth = { user: null };
    export let flash = {};

    const paymentMethods = ["Cash", "GCash", "Card", "Bank Transfer"];
    let mobileMenuOpen = false;
    let search = filters.search ?? "";
    let date = filters.date ?? "";
    let paymentMethodFilter = filters.payment_method ?? "";
    let statusFilter = filters.status ?? "";
    let showSaleForm = false;
    let showDetail = false;
    let detailTransaction = null;
    let saleSearch = "";
    let cart = [];
    let paymentMethod = "Cash";
    let saleError = "";
    let successMessage = flash.status ?? "";
    let processing = false;

    $: rows = transactions.data ?? [];
    $: completedRows = rows.filter((sale) => sale.status === "completed");
    $: totalSales = completedRows.reduce((sum, sale) => sum + Number(sale.total_amount ?? transactionTotal(sale)), 0);
    $: transactionCount = transactions.total ?? 0;
    $: averageTransaction = completedRows.length ? totalSales / completedRows.length : 0;
    $: saleProducts = products.filter((product) => product.stock_quantity > 0 && (!saleSearch.trim() || `${product.name} ${product.sku}`.toLowerCase().includes(saleSearch.trim().toLowerCase())));
    $: saleTotal = cart.reduce((sum, item) => sum + item.quantity * item.unit_price, 0);

    function formatPrice(value) {
        return `₱${Number(value ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function formatDate(value) {
        return new Date(value).toLocaleString("en-PH", { dateStyle: "medium", timeStyle: "short" });
    }

    function transactionTotal(transaction) {
        return (transaction.items ?? []).reduce((sum, item) => sum + Number(item.line_total), 0);
    }

    function applyFilters() {
        router.get("/sales", { search, date, payment_method: paymentMethodFilter, status: statusFilter }, { preserveState: true, preserveScroll: true });
    }

    function openDetails(transaction) {
        detailTransaction = transaction;
        showDetail = true;
    }

    function openSaleForm() {
        cart = [];
        saleSearch = "";
        paymentMethod = "Cash";
        saleError = "";
        showSaleForm = true;
    }

    function addToCart(product) {
        const existing = cart.find((item) => item.product_id === product.id);
        if (existing) {
            if (existing.quantity >= product.stock_quantity) {
                saleError = `Only ${product.stock_quantity} units of ${product.name} are available.`;
                return;
            }
            cart = cart.map((item) => item.product_id === product.id ? { ...item, quantity: item.quantity + 1 } : item);
            return;
        }
        cart = [...cart, { product_id: product.id, product_name: product.name, sku: product.sku, quantity: 1, unit_price: Number(product.selling_price) }];
    }

    function changeQuantity(item, value) {
        const product = products.find((candidate) => candidate.id === item.product_id);
        const quantity = Number(value);
        if (!Number.isInteger(quantity) || quantity < 1 || quantity > product.stock_quantity) {
            saleError = `Quantity must be between 1 and ${product.stock_quantity}.`;
            return;
        }
        saleError = "";
        cart = cart.map((cartItem) => cartItem.product_id === item.product_id ? { ...cartItem, quantity } : cartItem);
    }

    function incrementQuantity(item) {
        addToCart(products.find((product) => product.id === item.product_id));
    }

    function decrementQuantity(item) {
        if (item.quantity === 1) {
            cart = cart.filter((cartItem) => cartItem.product_id !== item.product_id);
            return;
        }
        changeQuantity(item, item.quantity - 1);
    }

    function completeSale() {
        if (!cart.length) {
            saleError = "Add at least one product before completing the sale.";
            return;
        }
        processing = true;
        saleError = "";
        router.post("/sales", {
            idempotency_key: crypto.randomUUID(),
            payment_method: paymentMethod,
            items: cart.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
        }, {
            preserveScroll: true,
            onSuccess: () => { showSaleForm = false; successMessage = "Sale completed successfully."; },
            onError: (errors) => (saleError = errors.items ?? errors.idempotency_key ?? errors.payment_method ?? "The sale could not be completed."),
            onFinish: () => (processing = false),
        });
    }

    function goToPage(url) {
        if (url) router.visit(url, { preserveState: true, preserveScroll: true });
    }
</script>

<svelte:head><title>Sales Management | One Prompt Away</title></svelte:head>

<div class="flex min-h-screen overflow-x-hidden bg-slate-50 font-sans text-slate-900 lg:h-screen">
    <AppSidebar activeItem="Sales Management" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col"><AppHeader title="Sales Management" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto"><div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
            <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="mb-2 text-xs font-medium text-slate-500">Workspace / Sales</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Sales Management</h1><p class="mt-1 text-sm text-slate-500">Record completed sales and review persisted transaction history.</p></div><Button size="sm" on:click={openSaleForm}>+ New Sale</Button></div>
            {#if successMessage}<div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{successMessage}</div>{/if}
            <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3"><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">Completed sales shown</p><p class="mt-2 text-2xl font-semibold">{formatPrice(totalSales)}</p></article><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">Transactions</p><p class="mt-2 text-2xl font-semibold">{transactionCount}</p></article><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">Average on this page</p><p class="mt-2 text-2xl font-semibold">{formatPrice(averageTransaction)}</p></article></section>
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm"><form class="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row" on:submit|preventDefault={applyFilters}><input bind:value={search} type="search" placeholder="Transaction number" class="h-9 flex-1 rounded-md border border-slate-200 px-3 text-xs" /><input bind:value={date} type="date" class="h-9 rounded-md border border-slate-200 px-3 text-xs" /><select bind:value={paymentMethodFilter} class="h-9 rounded-md border border-slate-200 px-3 text-xs"><option value="">All payments</option>{#each paymentMethods as method}<option value={method}>{method}</option>{/each}</select><select bind:value={statusFilter} class="h-9 rounded-md border border-slate-200 px-3 text-xs"><option value="">All statuses</option><option value="completed">Completed</option><option value="voided">Voided</option></select><button class="h-9 rounded-md bg-blue-600 px-4 text-xs font-semibold text-white">Apply</button></form>{#if !rows.length}<div class="px-6 py-16 text-center"><p class="text-sm font-semibold text-slate-700">No transactions found</p><p class="mt-1 text-xs text-slate-500">Completed transactions will appear here after a sale is recorded.</p></div>{:else}<div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="px-5 py-3">Transaction</th><th class="px-5 py-3">Date</th><th class="px-5 py-3">Items</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3">Payment</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">{#each rows as transaction}<tr><td class="px-5 py-3 text-xs font-semibold">{transaction.transaction_number}</td><td class="px-5 py-3 text-xs text-slate-500">{formatDate(transaction.transaction_date)}</td><td class="px-5 py-3 text-xs">{(transaction.items ?? []).reduce((sum, item) => sum + item.quantity, 0)}</td><td class="px-5 py-3 text-right text-xs font-semibold">{formatPrice(transaction.total_amount)}</td><td class="px-5 py-3 text-xs">{transaction.payment_method}</td><td class="px-5 py-3"><span class={`rounded-full px-2 py-1 text-[10px] font-semibold ${transaction.status === "completed" ? "bg-emerald-50 text-emerald-700" : "bg-slate-100 text-slate-600"}`}>{transaction.status}</span></td><td class="px-5 py-3 text-right"><button type="button" class="text-[11px] font-semibold text-blue-600" on:click={() => openDetails(transaction)}>View details</button></td></tr>{/each}</tbody></table></div>{/if}<div class="flex justify-between border-t border-slate-100 px-5 py-4 text-xs text-slate-500"><span>{transactions.from ?? 0}-{transactions.to ?? 0} of {transactions.total ?? 0}</span><div class="flex gap-1">{#each transactions.links ?? [] as link}{#if link.url}<button type="button" class="rounded border border-slate-200 px-2 py-1" on:click={() => goToPage(link.url)}>{@html link.label}</button>{/if}{/each}</div></div></section>
        </div></main>
    </div>
</div>

{#if showSaleForm}<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4"><dialog open class="m-0 max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-xl"><div class="flex justify-between"><div><h2 class="text-base font-semibold">New sale</h2><p class="mt-1 text-xs text-slate-500">Prices and stock are verified by the server when submitted.</p></div><button type="button" on:click={() => (showSaleForm = false)} class="text-slate-400">×</button></div><div class="mt-5 grid gap-6 lg:grid-cols-[1fr_1fr]"><div><input bind:value={saleSearch} placeholder="Search products by name or SKU" class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm" /><div class="mt-3 max-h-72 overflow-y-auto divide-y divide-slate-100">{#each saleProducts as product}<div class="flex w-full items-center justify-between py-3 text-left"><span><span class="block text-xs font-semibold">{product.name}</span><span class="text-[10px] text-slate-400">{product.sku} · {product.stock_quantity} available</span></span><div class="flex items-center gap-3"><span class="text-xs font-semibold">{formatPrice(product.selling_price)}</span><button type="button" aria-label={`Add ${product.name}`} class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-lg font-semibold leading-none text-white" on:click={() => addToCart(product)}>+</button></div></div>{/each}</div></div><div><h3 class="text-xs font-semibold">Sale items</h3>{#if !cart.length}<p class="py-8 text-center text-xs text-slate-500">Select products to begin.</p>{:else}<div class="mt-2 divide-y divide-slate-100">{#each cart as item}<div class="flex items-center gap-2 py-3"><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold">{item.product_name}</p><p class="text-[10px] text-slate-400">{formatPrice(item.unit_price)} each</p></div><div class="flex items-center rounded border border-slate-200 bg-white"><button type="button" aria-label={`Decrease ${item.product_name}`} class="px-2 py-1 text-sm" on:click={() => decrementQuantity(item)}>-</button><span class="min-w-7 px-1 text-center text-xs font-semibold">{item.quantity}</span><button type="button" aria-label={`Increase ${item.product_name}`} class="px-2 py-1 text-sm" disabled={item.quantity >= products.find((product) => product.id === item.product_id).stock_quantity} on:click={() => incrementQuantity(item)}>+</button></div><span class="w-20 text-right text-xs font-semibold">{formatPrice(item.quantity * item.unit_price)}</span><button type="button" class="text-xs text-rose-600" on:click={() => (cart = cart.filter((line) => line.product_id !== item.product_id))}>Remove</button></div>{/each}</div>{/if}<div class="mt-4 border-t border-slate-200 pt-4"><label class="text-xs font-medium">Payment method<select bind:value={paymentMethod} class="mt-1 h-10 w-full rounded-md border border-slate-200 px-3 text-sm">{#each paymentMethods as method}<option value={method}>{method}</option>{/each}</select></label><div class="mt-4 flex justify-between text-sm font-semibold"><span>Total</span><span>{formatPrice(saleTotal)}</span></div>{#if saleError}<p class="mt-3 text-xs text-rose-600">{saleError}</p>{/if}<button type="button" disabled={processing || !cart.length} class="mt-4 w-full rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-50" on:click={completeSale}>{processing ? "Saving..." : "Complete sale"}</button></div></div></div></dialog></div>{/if}
{#if showDetail && detailTransaction}<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4"><dialog open class="m-0 max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-xl"><div class="flex justify-between"><div><h2 class="text-base font-semibold">Transaction details</h2><p class="mt-1 text-xs text-slate-500">{detailTransaction.transaction_number} · {formatDate(detailTransaction.transaction_date)}</p></div><button type="button" on:click={() => (showDetail = false)} class="text-slate-400">×</button></div><div class="mt-5 divide-y divide-slate-100">{#each detailTransaction.items ?? [] as item}<div class="flex justify-between py-3 text-xs"><span>{item.product_name} × {item.quantity}</span><span class="font-semibold">{formatPrice(item.line_total)}</span></div>{/each}</div><div class="mt-4 flex justify-between border-t border-slate-200 pt-4 text-sm font-semibold"><span>Total</span><span>{formatPrice(detailTransaction.total_amount)}</span></div></dialog></div>{/if}
