<script>
    import { onMount } from "svelte";
    import { router } from "@inertiajs/svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Badge from "../lib/components/ui/Badge.svelte";
    import Button from "../lib/components/ui/Button.svelte";

    export let products = { data: [], links: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0 };
    export let categories = [];
    export let filters = {};
    export let auth = { user: null };
    export let flash = {};

    let mobileMenuOpen = false;
    let showProductForm = false;
    let showCategoryForm = false;
    let showStockForm = false;
    let showHistory = false;
    let editingProduct = null;
    let stockProduct = null;
    let historyProduct = null;
    let history = [];
    let productErrors = {};
    let stockErrors = {};
    let categoryErrors = {};
    let processing = false;
    let search = filters.search ?? "";
    let categoryId = String(filters.category_id ?? "");
    let status = filters.status ?? "";
    let sort = filters.sort ?? "";
    let categoryName = "";
    let productForm = emptyProduct();
    let stockForm = { movement_type: "received", quantity: "", note: "" };
    let errorMessage = "";
    let successMessage = flash.status ?? "";

    $: rows = products.data ?? [];
    $: totalProducts = products.total ?? 0;
    $: lowStockCount = rows.filter((product) => product.stock_quantity > 0 && product.stock_quantity <= product.reorder_threshold).length;
    $: outOfStockCount = rows.filter((product) => product.stock_quantity === 0).length;

    onMount(() => {
        if (new URLSearchParams(window.location.search).get("create") === "1") openCreateForm();
    });

    function emptyProduct() {
        return { name: "", sku: "", category_id: categories[0]?.id ?? "", description: "", selling_price: "", stock_quantity: 0, reorder_threshold: 10 };
    }

    function statusFor(product) {
        if (product.stock_quantity === 0) return "out_of_stock";
        return product.stock_quantity <= product.reorder_threshold ? "low_stock" : "in_stock";
    }

    function statusLabel(value) {
        return { in_stock: "In Stock", low_stock: "Low Stock", out_of_stock: "Out of Stock" }[value];
    }

    function statusClass(value) {
        return { in_stock: "bg-emerald-50 text-emerald-700", low_stock: "bg-amber-50 text-amber-700", out_of_stock: "bg-rose-50 text-rose-700" }[value];
    }

    function formatPrice(value) {
        return `₱${Number(value ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function formatDate(value) {
        return new Date(value).toLocaleString("en-PH", { dateStyle: "medium", timeStyle: "short" });
    }

    function query(params = {}) {
        router.get("/inventory", { search, category_id: categoryId, status, sort, ...params }, { preserveState: true, preserveScroll: true });
    }

    function applyFilters() {
        query({ page: 1 });
    }

    function openCreateForm() {
        editingProduct = null;
        productForm = emptyProduct();
        productErrors = {};
        errorMessage = "";
        showProductForm = true;
    }

    function openEditForm(product) {
        editingProduct = product;
        productForm = { name: product.name, sku: product.sku, category_id: product.category_id, description: product.description ?? "", selling_price: product.selling_price, stock_quantity: product.stock_quantity, reorder_threshold: product.reorder_threshold };
        productErrors = {};
        errorMessage = "";
        showProductForm = true;
    }

    function submitProduct() {
        processing = true;
        const options = {
            preserveScroll: true,
            onSuccess: () => { showProductForm = false; successMessage = editingProduct ? "Product updated successfully." : "Product created successfully."; },
            onError: (errors) => { productErrors = errors; errorMessage = "Please correct the highlighted fields."; },
            onFinish: () => (processing = false),
        };
        const payload = { ...productForm, category_id: Number(productForm.category_id), selling_price: Number(productForm.selling_price), stock_quantity: Number(productForm.stock_quantity), reorder_threshold: Number(productForm.reorder_threshold) };
        editingProduct ? router.put(`/inventory/${editingProduct.id}`, payload, options) : router.post("/inventory", payload, options);
    }

    function deleteProduct(product) {
        if (!window.confirm(`Delete ${product.name}? Products with sales history cannot be deleted.`)) return;
        router.delete(`/inventory/${product.id}`, { preserveScroll: true, onSuccess: () => (successMessage = "Product deleted successfully."), onError: (errors) => (errorMessage = errors.message ?? "The product could not be deleted.") });
    }

    function submitCategory() {
        categoryErrors = {};
        router.post("/inventory/categories", { name: categoryName }, {
            preserveScroll: true,
            onSuccess: () => { showCategoryForm = false; categoryName = ""; successMessage = "Category created successfully."; },
            onError: (errors) => (categoryErrors = errors),
        });
    }

    function openStockForm(product) {
        stockProduct = product;
        stockForm = { movement_type: "received", quantity: "", note: "" };
        stockErrors = {};
        showStockForm = true;
    }

    function submitStock() {
        processing = true;
        router.post(`/inventory/${stockProduct.id}/stock`, { ...stockForm, quantity: Number(stockForm.quantity) }, {
            preserveScroll: true,
            onSuccess: () => { showStockForm = false; successMessage = "Stock adjusted successfully."; },
            onError: (errors) => (stockErrors = errors),
            onFinish: () => (processing = false),
        });
    }

    async function openHistory(product) {
        historyProduct = product;
        showHistory = true;
        history = [];
        const response = await fetch(`/inventory/${product.id}/movements`, { headers: { Accept: "application/json" } });
        if (response.ok) history = (await response.json()).movements;
    }

    function goToPage(url) {
        if (url) router.visit(url, { preserveState: true, preserveScroll: true });
    }
</script>

<svelte:head><title>Inventory Management | One Prompt Away</title></svelte:head>

<div class="flex min-h-screen overflow-x-hidden bg-slate-50 font-sans text-slate-900 lg:h-screen">
    <AppSidebar activeItem="Inventory Management" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Inventory Management" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto"><div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
            <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="mb-2 text-xs font-medium text-slate-500">Workspace / Inventory</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Inventory Management</h1><p class="mt-1 text-sm text-slate-500">Manage products, categories, stock quantities and movement history.</p></div><div class="flex gap-2"><Button variant="outline" size="sm" on:click={() => (showCategoryForm = true)}>New category</Button><Button size="sm" on:click={openCreateForm}>+ Add Product</Button></div></div>
            {#if successMessage}<div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800" role="status">{successMessage}</div>{/if}{#if errorMessage}<div class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800" role="alert">{errorMessage}</div>{/if}
            <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3"><article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Products shown</p><p class="mt-2 text-2xl font-bold tracking-tight">{totalProducts}</p></article><article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Low stock shown</p><p class="mt-2 text-2xl font-bold tracking-tight text-amber-700">{lowStockCount}</p></article><article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Out of stock shown</p><p class="mt-2 text-2xl font-bold tracking-tight text-rose-700">{outOfStockCount}</p></article></section>
            <section class="rounded-lg border border-slate-200 bg-white"><form class="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row" on:submit|preventDefault={applyFilters}><input bind:value={search} type="search" placeholder="Search name or SKU" class="h-9 flex-1 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-blue-500" /><select bind:value={categoryId} class="h-9 rounded-md border border-slate-200 px-3 text-xs"><option value="">All categories</option>{#each categories as category}<option value={category.id}>{category.name}</option>{/each}</select><select bind:value={status} class="h-9 rounded-md border border-slate-200 px-3 text-xs"><option value="">All stock statuses</option><option value="in_stock">In stock</option><option value="low_stock">Low stock</option><option value="out_of_stock">Out of stock</option><option value="slow_moving">Slow moving</option></select><select bind:value={sort} class="h-9 rounded-md border border-slate-200 px-3 text-xs"><option value="">Sort by name</option><option value="price_asc">Price: low to high</option><option value="price_desc">Price: high to low</option><option value="stock_asc">Stock: low to high</option><option value="stock_desc">Stock: high to low</option><option value="best_selling">Best selling</option></select><button class="h-9 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700">Apply</button></form>
                {#if rows.length === 0}<div class="px-6 py-16 text-center"><p class="text-sm font-semibold text-slate-700">No products found</p><p class="mt-1 text-xs text-slate-500">Adjust your search or filters, or add the first product.</p></div>{:else}<div class="overflow-x-auto"><table class="w-full min-w-[940px] text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="px-5 py-3">Product</th><th class="px-5 py-3">SKU</th><th class="px-5 py-3">Category</th><th class="px-5 py-3 text-right">Price</th><th class="px-5 py-3 text-right">Stock</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">{#each rows as product (product.id)}<tr><td class="px-5 py-3"><p class="text-xs font-semibold text-slate-800">{product.name}</p><p class="text-[10px] text-slate-400">{product.description}</p></td><td class="px-5 py-3 text-xs text-slate-500">{product.sku}</td><td class="px-5 py-3 text-xs text-slate-600">{product.category?.name}</td><td class="px-5 py-3 text-right text-xs">{formatPrice(product.selling_price)}</td><td class="px-5 py-3 text-right text-xs font-semibold">{product.stock_quantity}</td><td class="px-5 py-3">                <Badge variant={statusFor(product) === "in_stock" ? "success" : statusFor(product) === "low_stock" ? "warning" : "destructive"}>{statusLabel(statusFor(product))}</Badge></td><td class="px-5 py-3"><div class="flex justify-end gap-1"><button type="button" class="rounded px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50" on:click={() => openStockForm(product)}>Adjust</button><button type="button" class="rounded px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50" on:click={() => openHistory(product)}>History</button><button type="button" class="rounded px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50" on:click={() => openEditForm(product)}>Edit</button><button type="button" class="rounded px-2 py-1 text-[11px] font-semibold text-rose-600 hover:bg-rose-50" on:click={() => deleteProduct(product)}>Delete</button></div></td></tr>{/each}</tbody></table></div>{/if}
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-xs text-slate-500"><span>{products.from ?? 0}-{products.to ?? 0} of {products.total ?? 0}</span><div class="flex gap-1">{#each products.links ?? [] as link}{#if link.url}<button type="button" class={`rounded border px-2 py-1 ${link.active ? "border-blue-600 bg-blue-50 text-blue-700" : "border-slate-200"}`} on:click={() => goToPage(link.url)}>{@html link.label}</button>{/if}{/each}</div></div>
            </section>
        </div></main>
    </div>
</div>

{#if showProductForm}<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4"><dialog open class="m-0 w-full max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-xl"><h2 class="text-base font-semibold">{editingProduct ? "Edit product" : "Add product"}</h2><form class="mt-5 grid gap-4 sm:grid-cols-2" on:submit|preventDefault={submitProduct}>{#each [["name","Product name","text"],["sku","SKU","text"],["selling_price","Selling price","number"],["stock_quantity","Stock quantity","number"],["reorder_threshold","Reorder threshold","number"]] as field}<label class="text-xs font-medium text-slate-700">{field[1]}<input bind:value={productForm[field[0]]} type={field[2]} min={field[2] === "number" ? "0" : undefined} step={field[0] === "selling_price" ? "0.01" : "1"} class="mt-1 h-10 w-full rounded-md border border-slate-200 px-3 text-sm" />{#if productErrors[field[0]]}<span class="text-[11px] text-rose-600">{productErrors[field[0]]}</span>{/if}</label>{/each}<label class="text-xs font-medium text-slate-700">Category<select bind:value={productForm.category_id} class="mt-1 h-10 w-full rounded-md border border-slate-200 px-3 text-sm">{#each categories as category}<option value={category.id}>{category.name}</option>{/each}</select>{#if productErrors.category_id}<span class="text-[11px] text-rose-600">{productErrors.category_id}</span>{/if}</label><label class="text-xs font-medium text-slate-700 sm:col-span-2">Description<textarea bind:value={productForm.description} rows="3" class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm"></textarea></label><div class="flex justify-end gap-2 border-t border-slate-100 pt-4 sm:col-span-2"><button type="button" class="h-9 rounded-md border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50" on:click={() => (showProductForm = false)}>Cancel</button><button disabled={processing} class="h-9 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700">{processing ? "Saving..." : "Save product"}</button></div></form></dialog></div>{/if}
{#if showCategoryForm}<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4"><dialog open class="m-0 w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-xl"><h2 class="text-base font-semibold">New category</h2><form class="mt-5" on:submit|preventDefault={submitCategory}><input bind:value={categoryName} placeholder="Category name" class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm" />{#if categoryErrors.name}<p class="mt-1 text-xs text-rose-600">{categoryErrors.name}</p>{/if}<div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded border border-slate-200 px-4 py-2 text-xs" on:click={() => (showCategoryForm = false)}>Cancel</button><button class="rounded bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Create</button></div></form></dialog></div>{/if}
{#if showStockForm}<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4"><dialog open class="m-0 w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-xl"><h2 class="text-base font-semibold">Adjust stock</h2><p class="mt-1 text-xs text-slate-500">{stockProduct.name} · current {stockProduct.stock_quantity}</p><form class="mt-5 space-y-4" on:submit|preventDefault={submitStock}><select bind:value={stockForm.movement_type} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm"><option value="received">Receive stock</option><option value="reduction">Reduce stock</option><option value="correction">Correct stock (increase)</option></select><input bind:value={stockForm.quantity} type="number" min="1" step="1" placeholder="Quantity" class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm" />{#if stockErrors.quantity}<p class="text-xs text-rose-600">{stockErrors.quantity}</p>{/if}<textarea bind:value={stockForm.note} rows="3" placeholder="Reason or note" class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"></textarea>{#if stockErrors.note}<p class="text-xs text-rose-600">{stockErrors.note}</p>{/if}<div class="flex justify-end gap-2"><button type="button" class="rounded border border-slate-200 px-4 py-2 text-xs" on:click={() => (showStockForm = false)}>Cancel</button><button disabled={processing} class="rounded bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Save adjustment</button></div></form></dialog></div>{/if}
{#if showHistory}<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4"><dialog open class="m-0 max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-xl"><div class="flex justify-between"><div><h2 class="text-base font-semibold">Stock movement history</h2><p class="mt-1 text-xs text-slate-500">{historyProduct.name} · {historyProduct.sku}</p></div><button type="button" on:click={() => (showHistory = false)} class="text-slate-400">×</button></div>{#if history.length === 0}<p class="py-10 text-center text-xs text-slate-500">No stock movements recorded.</p>{:else}<div class="mt-5 overflow-x-auto"><table class="w-full text-left text-xs"><thead class="text-[10px] uppercase text-slate-400"><tr><th class="pb-2">Type</th><th class="pb-2">Quantity</th><th class="pb-2">Balance</th><th class="pb-2">Reason</th><th class="pb-2">By / time</th></tr></thead><tbody class="divide-y divide-slate-100">{#each history as movement}<tr><td class="py-3">{movement.movement_type}</td><td class="py-3">{movement.quantity}</td><td class="py-3">{movement.balance_after}</td><td class="py-3">{movement.note}</td><td class="py-3">{movement.user ?? "System"}<br /><span class="text-slate-400">{formatDate(movement.created_at)}</span></td></tr>{/each}</tbody></table></div>{/if}</dialog></div>{/if}
