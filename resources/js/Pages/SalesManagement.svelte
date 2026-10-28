<script>
    import { onMount } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";

    // Preview-only catalog and transactions. Replace with Inertia props when sales contracts are confirmed.
    const paymentMethods = ["Cash", "GCash", "Card", "Bank Transfer"];
    const catalog = [
        { id: 1, name: "Kawasaki Ninja Brake Pad", sku: "BP-KN-004", selling_price: 1250, stock_quantity: 128 },
        { id: 2, name: "Yamaha MT-15 Air Filter", sku: "AF-YM-021", selling_price: 680, stock_quantity: 8 },
        { id: 3, name: "Honda Click Drive Belt", sku: "DB-HC-018", selling_price: 1450, stock_quantity: 0 },
        { id: 4, name: "NGK Iridium Spark Plug", sku: "SP-NGK-009", selling_price: 520, stock_quantity: 42 },
        { id: 5, name: "DID 428H Chain Set", sku: "CH-DID-428", selling_price: 2380, stock_quantity: 16 },
        { id: 6, name: "Michelin City Pro Tire", sku: "TR-MCP-017", selling_price: 3150, stock_quantity: 4 },
    ];
    const initialTransactions = [
        { id: 9001, transaction_number: "TX-20261028-0001", transaction_date: "2026-10-28T09:30:00+08:00", status: "completed", payment_method: "Cash", items: [{ product_id: 1, product_name: "Kawasaki Ninja Brake Pad", quantity: 2, unit_price: 1250, line_total: 2500 }] },
        { id: 9002, transaction_number: "TX-20261027-0002", transaction_date: "2026-10-27T14:12:00+08:00", status: "completed", payment_method: "GCash", items: [{ product_id: 4, product_name: "NGK Iridium Spark Plug", quantity: 2, unit_price: 520, line_total: 1040 }, { product_id: 2, product_name: "Yamaha MT-15 Air Filter", quantity: 1, unit_price: 680, line_total: 680 }] },
        { id: 9003, transaction_number: "TX-20261026-0003", transaction_date: "2026-10-26T11:05:00+08:00", status: "completed", payment_method: "Card", items: [{ product_id: 5, product_name: "DID 428H Chain Set", quantity: 1, unit_price: 2380, line_total: 2380 }] },
        { id: 9004, transaction_number: "TX-20261024-0004", transaction_date: "2026-10-24T16:48:00+08:00", status: "completed", payment_method: "Bank Transfer", items: [{ product_id: 6, product_name: "Michelin City Pro Tire", quantity: 2, unit_price: 3150, line_total: 6300 }] },
    ];

    let transactions = initialTransactions.map((transaction) => ({ ...transaction, items: transaction.items.map((item) => ({ ...item })) }));
    let products = catalog.map((product) => ({ ...product }));
    let search = "";
    let dateFilter = "";
    let paymentFilter = "";
    let statusFilter = "";
    let loading = true;
    let showSaleForm = false;
    let showDetail = false;
    let detailTransaction = null;
    let saleSearch = "";
    let cart = [];
    let paymentMethod = "Cash";
    let saleError = "";
    let successMessage = "";
    let confirmingSale = false;
    let mobileMenuOpen = false;

    $: filteredTransactions = transactions.filter((transaction) => {
        const query = search.trim().toLowerCase();
        const matchesSearch = !query || transaction.transaction_number.toLowerCase().includes(query);
        const matchesDate = !dateFilter || transaction.transaction_date.slice(0, 10) === dateFilter;
        return matchesSearch && matchesDate && (!paymentFilter || transaction.payment_method === paymentFilter) && (!statusFilter || transaction.status === statusFilter);
    });
    $: totalSales = transactions.reduce((sum, transaction) => sum + transactionTotal(transaction), 0);
    $: transactionCount = transactions.length;
    $: averageTransaction = transactionCount ? totalSales / transactionCount : 0;
    $: saleProducts = products.filter((product) => {
        const query = saleSearch.trim().toLowerCase();
        return !query || product.name.toLowerCase().includes(query) || product.sku.toLowerCase().includes(query);
    });
    $: saleTotal = cart.reduce((sum, item) => sum + item.line_total, 0);

    onMount(() => {
        const timer = window.setTimeout(() => (loading = false), 250);
        return () => window.clearTimeout(timer);
    });

    function formatPrice(value) {
        return `₱${Number(value).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function transactionTotal(transaction) {
        return transaction.items.reduce((sum, item) => sum + item.line_total, 0);
    }

    function formatDate(value) {
        return new Date(value).toLocaleString("en-PH", { dateStyle: "medium", timeStyle: "short" });
    }

    function openDetails(transaction) {
        detailTransaction = transaction;
        showDetail = true;
    }

    function closeDetails() {
        showDetail = false;
        detailTransaction = null;
    }

    function openSaleForm() {
        cart = [];
        saleSearch = "";
        paymentMethod = "Cash";
        saleError = "";
        confirmingSale = false;
        showSaleForm = true;
    }

    function closeSaleForm() {
        showSaleForm = false;
        confirmingSale = false;
        saleError = "";
    }

    function addToCart(product) {
        const existing = cart.find((item) => item.product_id === product.id);
        if (existing) {
            if (existing.quantity >= product.stock_quantity) {
                saleError = `Only ${product.stock_quantity} units of ${product.name} are available.`;
                return;
            }
            cart = cart.map((item) => item.product_id === product.id ? { ...item, quantity: item.quantity + 1, line_total: (item.quantity + 1) * item.unit_price } : item);
        } else if (product.stock_quantity > 0) {
            cart = [...cart, { product_id: product.id, product_name: product.name, sku: product.sku, quantity: 1, unit_price: product.selling_price, line_total: product.selling_price }];
        } else {
            saleError = `${product.name} is out of stock.`;
        }
    }

    function changeQuantity(item, value) {
        const product = products.find((candidate) => candidate.id === item.product_id);
        const quantity = Number(value);
        if (!Number.isInteger(quantity) || quantity < 1) {
            saleError = "Quantity must be a whole number greater than zero.";
            return;
        }
        if (quantity > product.stock_quantity) {
            saleError = `Only ${product.stock_quantity} units of ${product.name} are available.`;
            return;
        }
        saleError = "";
        cart = cart.map((cartItem) => cartItem.product_id === item.product_id ? { ...cartItem, quantity, line_total: quantity * cartItem.unit_price } : cartItem);
    }

    function removeFromCart(productId) {
        cart = cart.filter((item) => item.product_id !== productId);
        saleError = "";
    }

    function requestSaleConfirmation() {
        saleError = "";
        if (!cart.length) {
            saleError = "Add at least one product before completing the sale.";
            return;
        }
        confirmingSale = true;
    }

    function completeSale() {
        if (!cart.length || !confirmingSale) return;
        const nextId = Math.max(...transactions.map((transaction) => transaction.id), 9000) + 1;
        const nextNumber = `TX-${new Date().toISOString().slice(0, 10).replaceAll("-", "")}-${String(nextId - 9000).padStart(4, "0")}`;
        const items = cart.map((item) => ({ product_id: item.product_id, product_name: item.product_name, quantity: item.quantity, unit_price: item.unit_price, line_total: item.line_total }));
        transactions = [{ id: nextId, transaction_number: nextNumber, transaction_date: new Date().toISOString(), status: "completed", payment_method: paymentMethod, items }, ...transactions];
        products = products.map((product) => {
            const sold = cart.find((item) => item.product_id === product.id);
            return sold ? { ...product, stock_quantity: product.stock_quantity - sold.quantity } : product;
        });
        successMessage = `${nextNumber} was added to this frontend preview.`;
        closeSaleForm();
    }
</script>

<svelte:head><title>Sales Management | One Prompt Away</title></svelte:head>

<div class="flex h-screen min-h-[720px] overflow-hidden bg-slate-50 font-sans text-slate-900">
    <AppSidebar activeItem="Sales Management" bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Sales Management" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
                <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="mb-2 text-xs font-medium text-slate-500">Workspace / Sales</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Sales Management</h1><p class="mt-1 max-w-2xl text-sm text-slate-500">Review transaction history and record motorcycle-parts sales in this preview.</p></div><button type="button" class="flex w-fit items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700" on:click={openSaleForm}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>New Sale</button></div>
                <div class="mb-6 flex flex-wrap items-center gap-3 text-xs"><span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">Preview data only</span><span class="text-slate-400">Total sales and average value use completed mock transactions only; no taxes, discounts or refunds are included.</span></div>
                {#if successMessage}<div class="mb-5 flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800" role="status"><span>{successMessage}</span><button type="button" class="font-semibold" on:click={() => (successMessage = "")}>Dismiss</button></div>{/if}
                <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3" aria-label="Sales summary"><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-medium text-slate-500">Total Sales</p><p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{formatPrice(totalSales)}</p><p class="mt-3 text-[11px] text-slate-400">Completed preview transactions</p></article><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-medium text-slate-500">Transactions</p><p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{transactionCount}</p><p class="mt-3 text-[11px] text-slate-400">Derived from transaction records</p></article><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-medium text-slate-500">Average Transaction Value</p><p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{formatPrice(averageTransaction)}</p><p class="mt-3 text-[11px] text-slate-400">Total sales ÷ transactions</p></article></section>
                <section class="rounded-lg border border-slate-200 bg-white"><div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="text-sm font-semibold text-slate-900">Transaction history</h2><p class="mt-1 text-xs text-slate-500">{filteredTransactions.length} of {transactions.length} transactions shown</p></div><div class="flex flex-col gap-2 sm:flex-row"><input bind:value={search} type="search" placeholder="Search transaction number" aria-label="Search transaction number" class="h-9 w-full rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-52" /><input bind:value={dateFilter} type="date" aria-label="Filter by date" class="h-9 rounded-md border border-slate-200 px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /><select bind:value={paymentFilter} aria-label="Filter by payment method" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="">All payments</option>{#each paymentMethods as method}<option value={method}>{method}</option>{/each}</select><select bind:value={statusFilter} aria-label="Filter by status" class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="">All statuses</option><option value="completed">Completed</option></select></div></div>{#if loading}<div class="flex min-h-56 items-center justify-center text-xs text-slate-500">Loading preview transactions…</div>{:else if !filteredTransactions.length}<div class="flex min-h-56 flex-col items-center justify-center px-6 text-center"><p class="text-sm font-semibold text-slate-700">No transactions found</p><p class="mt-1 max-w-sm text-xs text-slate-500">Try changing your search or filters to find another transaction.</p></div>{:else}<div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="px-5 py-3">Transaction</th><th class="px-5 py-3">Date and time</th><th class="px-5 py-3">Items</th><th class="px-5 py-3 text-right">Total amount</th><th class="px-5 py-3">Payment method</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">{#each filteredTransactions as transaction (transaction.id)}<tr><td class="px-5 py-3.5 text-xs font-semibold text-slate-800">{transaction.transaction_number}</td><td class="px-5 py-3.5 text-xs text-slate-500">{formatDate(transaction.transaction_date)}</td><td class="px-5 py-3.5 text-xs text-slate-600">{transaction.items.reduce((sum, item) => sum + item.quantity, 0)} item{transaction.items.reduce((sum, item) => sum + item.quantity, 0) === 1 ? "" : "s"}</td><td class="px-5 py-3.5 text-right text-xs font-semibold text-slate-700">{formatPrice(transactionTotal(transaction))}</td><td class="px-5 py-3.5 text-xs text-slate-600">{transaction.payment_method}</td><td class="px-5 py-3.5"><span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">Completed</span></td><td class="px-5 py-3.5 text-right"><button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-blue-600 hover:bg-blue-50" on:click={() => openDetails(transaction)}>View details</button></td></tr>{/each}</tbody></table></div>{/if}</section>
            </div>
        </main>
    </div>
</div>

{#if showSaleForm}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4" role="presentation" on:click={(event) => event.target === event.currentTarget && closeSaleForm()}><dialog open class="static m-0 max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-xl" aria-labelledby="sale-form-title"><div class="flex items-start justify-between"><div><h2 id="sale-form-title" class="text-base font-semibold text-slate-950">New sale</h2><p class="mt-1 text-xs text-slate-500">Preview workflow · stock changes stay in local browser state only.</p></div><button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50" aria-label="Close sale form" on:click={closeSaleForm}>×</button></div>{#if saleError}<div class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-700" role="alert">{saleError}</div>{/if}<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_1.1fr]"><div><label for="sale-search" class="mb-1.5 block text-xs font-medium text-slate-700">Search products</label><input id="sale-search" bind:value={saleSearch} type="search" placeholder="Name or SKU" class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /><div class="mt-3 max-h-72 overflow-y-auto rounded-md border border-slate-200">{#if !saleProducts.length}<p class="p-4 text-xs text-slate-500">No matching products.</p>{:else}{#each saleProducts as product}<div class="flex items-center justify-between gap-3 border-b border-slate-100 p-3 last:border-0"><div class="min-w-0"><p class="truncate text-xs font-semibold text-slate-800">{product.name}</p><p class="mt-1 text-[10px] text-slate-400">{product.sku} · {formatPrice(product.selling_price)} · {product.stock_quantity} available</p></div><button type="button" disabled={product.stock_quantity === 0} class="shrink-0 rounded-md border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" on:click={() => addToCart(product)}>Add</button></div>{/each}{/if}</div></div><div><h3 class="text-xs font-semibold text-slate-800">Sale cart</h3>{#if !cart.length}<div class="mt-3 flex min-h-40 items-center justify-center rounded-md border border-dashed border-slate-200 px-4 text-center text-xs text-slate-500">Your cart is empty. Add products to begin.</div>{:else}<div class="mt-3 divide-y divide-slate-100 rounded-md border border-slate-200">{#each cart as item}<div class="flex items-center gap-3 p-3"><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-800">{item.product_name}</p><p class="mt-1 text-[10px] text-slate-400">{formatPrice(item.unit_price)} each</p></div><input aria-label={`Quantity for ${item.product_name}`} type="number" min="1" value={item.quantity} class="h-8 w-16 rounded-md border border-slate-200 px-2 text-xs text-center" on:change={(event) => changeQuantity(item, event.currentTarget.value)} /><span class="w-20 text-right text-xs font-semibold text-slate-700">{formatPrice(item.line_total)}</span><button type="button" class="rounded-md px-2 py-1 text-xs text-rose-600 hover:bg-rose-50" aria-label={`Remove ${item.product_name}`} on:click={() => removeFromCart(item.product_id)}>Remove</button></div>{/each}</div>{/if}<div class="mt-4"><label for="payment-method" class="mb-1.5 block text-xs font-medium text-slate-700">Payment method <span class="text-rose-600">*</span></label><select id="payment-method" bind:value={paymentMethod} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{#each paymentMethods as method}<option value={method}>{method}</option>{/each}</select></div><div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4"><span class="text-xs font-semibold text-slate-600">Transaction total</span><span class="text-lg font-semibold text-slate-950">{formatPrice(saleTotal)}</span></div></div></div>{#if confirmingSale}<div class="mt-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800" role="alert"><p class="font-semibold">Confirm mock sale?</p><p class="mt-1">This creates a completed preview transaction and reduces local mock stock. No backend record is created.</p></div>{/if}<div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5"><button type="button" class="rounded-md border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" on:click={closeSaleForm}>Cancel</button>{#if confirmingSale}<button type="button" class="rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700" on:click={completeSale}>Confirm sale</button>{:else}<button type="button" class="rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700" on:click={requestSaleConfirmation}>Review sale</button>{/if}</div></dialog></div>
{/if}

{#if showDetail && detailTransaction}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4" role="presentation" on:click={(event) => event.target === event.currentTarget && closeDetails()}><dialog open class="static m-0 max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-xl" aria-labelledby="transaction-detail-title"><div class="flex items-start justify-between"><div><h2 id="transaction-detail-title" class="text-base font-semibold text-slate-950">Transaction details</h2><p class="mt-1 text-xs text-slate-500">{detailTransaction.transaction_number} · {formatDate(detailTransaction.transaction_date)}</p></div><button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50" aria-label="Close transaction details" on:click={closeDetails}>×</button></div><div class="mt-6 divide-y divide-slate-100 border-y border-slate-100">{#each detailTransaction.items as item}<div class="flex items-center justify-between gap-4 py-3"><div><p class="text-xs font-semibold text-slate-800">{item.product_name}</p><p class="mt-1 text-[11px] text-slate-400">{item.quantity} × {formatPrice(item.unit_price)}</p></div><span class="text-xs font-semibold text-slate-700">{formatPrice(item.line_total)}</span></div>{/each}</div><div class="mt-4 flex items-center justify-between"><span class="text-xs font-semibold text-slate-600">Total</span><span class="text-lg font-semibold text-slate-950">{formatPrice(transactionTotal(detailTransaction))}</span></div><p class="mt-4 text-[11px] text-slate-400">Payment method: {detailTransaction.payment_method}. Taxes, discounts and refunds are not represented in this preview.</p></dialog></div>
{/if}
