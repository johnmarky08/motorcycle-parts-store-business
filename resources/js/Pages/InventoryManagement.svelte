<script>
    import { onMount } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";

    // Preview-only data. Replace with Inertia props when product and inventory contracts are confirmed.
    const LOW_STOCK_THRESHOLD = 10;
    const categories = ["Brake System", "Engine", "Electrical", "Drivetrain", "Tires"];
    const emptyProduct = () => ({
        name: "",
        sku: "",
        category: "Brake System",
        description: "",
        selling_price: "",
        stock_quantity: "",
        reorder_threshold: String(LOW_STOCK_THRESHOLD),
    });
    const initialProducts = [
        { id: 1, name: "Kawasaki Ninja Brake Pad", sku: "BP-KN-004", category: "Brake System", description: "Front brake pad set for Kawasaki Ninja models.", selling_price: 1250, stock_quantity: 128, reorder_threshold: 10 },
        { id: 2, name: "Yamaha MT-15 Air Filter", sku: "AF-YM-021", category: "Engine", description: "Replacement air filter for Yamaha MT-15.", selling_price: 680, stock_quantity: 8, reorder_threshold: 10 },
        { id: 3, name: "Honda Click Drive Belt", sku: "DB-HC-018", category: "Drivetrain", description: "CVT drive belt for Honda Click scooters.", selling_price: 1450, stock_quantity: 0, reorder_threshold: 10 },
        { id: 4, name: "NGK Iridium Spark Plug", sku: "SP-NGK-009", category: "Electrical", description: "Long-life iridium spark plug for common motorcycle engines.", selling_price: 520, stock_quantity: 42, reorder_threshold: 10 },
        { id: 5, name: "DID 428H Chain Set", sku: "CH-DID-428", category: "Drivetrain", description: "Heavy-duty chain and sprocket set.", selling_price: 2380, stock_quantity: 16, reorder_threshold: 8 },
        { id: 6, name: "Michelin City Pro Tire", sku: "TR-MCP-017", category: "Tires", description: "Urban commuter tire with wet-road grip.", selling_price: 3150, stock_quantity: 4, reorder_threshold: 10 },
    ];

    let products = initialProducts.map((product) => ({ ...product }));
    let search = "";
    let categoryFilter = "";
    let statusFilter = "";
    let showProductForm = false;
    let editingProductId = null;
    let productForm = emptyProduct();
    let productErrors = {};
    let showStockForm = false;
    let stockProduct = null;
    let stockForm = { adjustment_type: "received", quantity: "", note: "", confirmed: false };
    let stockErrors = {};
    let successMessage = "";
    let errorMessage = "";
    let loading = true;
    let mobileMenuOpen = false;

    $: filteredProducts = products.filter((product) => {
        const query = search.trim().toLowerCase();
        const matchesSearch = !query || [product.name, product.sku, product.category].some((value) => value.toLowerCase().includes(query));
        return matchesSearch && (!categoryFilter || product.category === categoryFilter) && (!statusFilter || stockStatus(product) === statusFilter);
    });
    $: totalProducts = products.length;
    $: inStockCount = products.filter((product) => stockStatus(product) === "in_stock").length;
    $: lowStockCount = products.filter((product) => stockStatus(product) === "low_stock").length;
    $: outOfStockCount = products.filter((product) => stockStatus(product) === "out_of_stock").length;

    onMount(() => {
        const timer = window.setTimeout(() => (loading = false), 250);
        return () => window.clearTimeout(timer);
    });

    function stockStatus(product) {
        if (product.stock_quantity === 0) return "out_of_stock";
        return product.stock_quantity <= product.reorder_threshold ? "low_stock" : "in_stock";
    }

    function statusLabel(status) {
        return { in_stock: "In Stock", low_stock: "Low Stock", out_of_stock: "Out of Stock" }[status];
    }

    function statusClasses(status) {
        return {
            in_stock: "bg-emerald-50 text-emerald-700",
            low_stock: "bg-amber-50 text-amber-700",
            out_of_stock: "bg-rose-50 text-rose-700",
        }[status];
    }

    function formatPrice(value) {
        return `₱${Number(value).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function openCreateForm() {
        editingProductId = null;
        productForm = emptyProduct();
        productErrors = {};
        errorMessage = "";
        showProductForm = true;
    }

    function openEditForm(product) {
        editingProductId = product.id;
        productForm = { ...product, selling_price: String(product.selling_price), stock_quantity: String(product.stock_quantity), reorder_threshold: String(product.reorder_threshold) };
        productErrors = {};
        errorMessage = "";
        showProductForm = true;
    }

    function closeProductForm() {
        showProductForm = false;
        productErrors = {};
    }

    function validateProduct() {
        const errors = {};
        const sku = productForm.sku.trim().toUpperCase();
        const price = Number(productForm.selling_price);
        const quantity = Number(productForm.stock_quantity);
        const threshold = Number(productForm.reorder_threshold);
        if (!productForm.name.trim()) errors.name = "Product name is required.";
        if (!sku) errors.sku = "SKU or product code is required.";
        else if (products.some((product) => product.sku.toUpperCase() === sku && product.id !== editingProductId)) errors.sku = "This SKU already exists in the preview catalog.";
        if (!productForm.category) errors.category = "Select a category.";
        if (productForm.selling_price === "" || !Number.isFinite(price) || price < 0) errors.selling_price = "Enter a non-negative selling price.";
        if (productForm.stock_quantity === "" || !Number.isInteger(quantity) || quantity < 0) errors.stock_quantity = "Enter a whole number of zero or more.";
        if (productForm.reorder_threshold === "" || !Number.isInteger(threshold) || threshold < 0) errors.reorder_threshold = "Enter a whole number of zero or more.";
        productErrors = errors;
        return Object.keys(errors).length === 0;
    }

    function saveProduct() {
        successMessage = "";
        errorMessage = "";
        if (!validateProduct()) return;
        const payload = {
            name: productForm.name.trim(),
            sku: productForm.sku.trim().toUpperCase(),
            category: productForm.category,
            description: productForm.description.trim(),
            selling_price: Number(productForm.selling_price),
            stock_quantity: Number(productForm.stock_quantity),
            reorder_threshold: Number(productForm.reorder_threshold),
        };
        if (editingProductId) {
            products = products.map((product) => product.id === editingProductId ? { ...product, ...payload } : product);
            successMessage = "Product updated in this preview.";
        } else {
            const nextId = Math.max(...products.map((product) => product.id), 0) + 1;
            products = [...products, { id: nextId, ...payload }];
            successMessage = "Product added to this preview.";
        }
        closeProductForm();
    }

    function openStockForm(product) {
        stockProduct = product;
        stockForm = { adjustment_type: "received", quantity: "", note: "", confirmed: false };
        stockErrors = {};
        errorMessage = "";
        showStockForm = true;
    }

    function closeStockForm() {
        showStockForm = false;
        stockProduct = null;
        stockErrors = {};
    }

    function validateStock() {
        const errors = {};
        const quantity = Number(stockForm.quantity);
        if (stockForm.quantity === "" || !Number.isInteger(quantity) || quantity < 1) errors.quantity = "Enter a whole number greater than zero.";
        if (!stockForm.note.trim()) errors.note = "Add a reason or note for this adjustment.";
        if (stockForm.adjustment_type === "reduction" && !stockForm.confirmed) errors.confirmed = "Confirm this stock reduction before saving.";
        if (stockForm.adjustment_type === "reduction" && stockProduct && quantity > stockProduct.stock_quantity) errors.quantity = "Reduction cannot exceed the current stock.";
        stockErrors = errors;
        return Object.keys(errors).length === 0;
    }

    function saveStockAdjustment() {
        if (!validateStock()) return;
        const quantity = Number(stockForm.quantity);
        products = products.map((product) => {
            if (product.id !== stockProduct.id) return product;
            const nextQuantity = stockForm.adjustment_type === "received" ? product.stock_quantity + quantity : product.stock_quantity - quantity;
            return { ...product, stock_quantity: nextQuantity };
        });
        successMessage = `Stock adjusted for ${stockProduct.name} in this preview.`;
        closeStockForm();
    }
</script>

<svelte:head><title>Inventory Management | One Prompt Away</title></svelte:head>

<div class="flex h-screen min-h-[720px] overflow-hidden bg-slate-50 font-sans text-slate-900">
    <AppSidebar activeItem="Inventory Management" bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Inventory Management" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
                <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="mb-2 text-xs font-medium text-slate-500">Workspace / Inventory</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Inventory Management</h1><p class="mt-1 max-w-2xl text-sm text-slate-500">Manage motorcycle parts, stock quantities and product information in one place.</p></div>
                    <button type="button" class="flex w-fit items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700" on:click={openCreateForm}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>Add Product</button>
                </div>
                <div class="mb-6 flex flex-wrap items-center gap-3 text-xs"><span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">Preview data only</span><span class="text-slate-400">Low stock preview threshold: {LOW_STOCK_THRESHOLD} units · proposed rule, pending backend confirmation</span></div>
                {#if successMessage}<div class="mb-5 flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800" role="status"><span>{successMessage}</span><button type="button" class="font-semibold hover:text-emerald-950" on:click={() => (successMessage = "")}>Dismiss</button></div>{/if}
                {#if errorMessage}<div class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800" role="alert">{errorMessage}</div>{/if}

                <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Inventory summary">
                    {#each [{ label: "Total Products", value: totalProducts, color: "text-slate-950" }, { label: "In Stock", value: inStockCount, color: "text-emerald-700" }, { label: "Low Stock", value: lowStockCount, color: "text-amber-700" }, { label: "Out of Stock", value: outOfStockCount, color: "text-rose-700" }] as summary}
                        <article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-medium text-slate-500">{summary.label}</p><p class={`mt-2 text-2xl font-semibold tracking-tight ${summary.color}`}>{summary.value}</p><p class="mt-3 text-[11px] text-slate-400">Derived from preview products</p></article>
                    {/each}
                </section>

                <section class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="text-sm font-semibold text-slate-900">Product inventory</h2><p class="mt-1 text-xs text-slate-500">{filteredProducts.length} of {products.length} products shown</p></div><div class="flex flex-col gap-2 sm:flex-row"><label class="relative"><span class="sr-only">Search products</span><input bind:value={search} type="search" placeholder="Search products" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-52" /></label><select bind:value={categoryFilter} aria-label="Filter by category" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-40"><option value="">All categories</option>{#each categories as category}<option value={category}>{category}</option>{/each}</select><select bind:value={statusFilter} aria-label="Filter by stock status" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-36"><option value="">All statuses</option><option value="in_stock">In Stock</option><option value="low_stock">Low Stock</option><option value="out_of_stock">Out of Stock</option></select></div></div>
                    {#if loading}<div class="flex min-h-56 items-center justify-center px-6 text-center"><p class="text-xs text-slate-500">Loading preview inventory…</p></div>{:else if filteredProducts.length === 0}<div class="flex min-h-56 flex-col items-center justify-center px-6 text-center"><p class="text-sm font-semibold text-slate-700">No products found</p><p class="mt-1 max-w-sm text-xs text-slate-500">Try changing your search or filters to find another product.</p></div>{:else}<div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="px-5 py-3">Product</th><th class="px-5 py-3">SKU</th><th class="px-5 py-3">Category</th><th class="px-5 py-3 text-right">Selling price</th><th class="px-5 py-3 text-right">Stock</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">{#each filteredProducts as product (product.id)}<tr class="align-middle"><td class="px-5 py-3.5"><p class="text-xs font-semibold text-slate-800">{product.name}</p><p class="mt-0.5 max-w-xs truncate text-[10px] text-slate-400">{product.description}</p></td><td class="px-5 py-3.5 text-xs text-slate-500">{product.sku}</td><td class="px-5 py-3.5 text-xs text-slate-600">{product.category}</td><td class="px-5 py-3.5 text-right text-xs font-medium text-slate-700">{formatPrice(product.selling_price)}</td><td class="px-5 py-3.5 text-right text-xs font-semibold text-slate-700">{product.stock_quantity}</td><td class="px-5 py-3.5"><span class={`rounded-full px-2 py-1 text-[10px] font-semibold ${statusClasses(stockStatus(product))}`}>{statusLabel(stockStatus(product))}</span></td><td class="px-5 py-3.5"><div class="flex justify-end gap-1"><button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-blue-600 hover:bg-blue-50" on:click={() => openStockForm(product)}>Adjust Stock</button><button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900" on:click={() => openEditForm(product)}>Edit</button></div></td></tr>{/each}</tbody></table></div>{/if}
                </section>
            </div>
        </main>
    </div>
</div>

{#if showProductForm}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4" role="presentation" on:click={(event) => event.target === event.currentTarget && closeProductForm()}>
        <dialog open class="static m-0 max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-xl" aria-labelledby="product-form-title">
            <div class="flex items-start justify-between"><div><h2 id="product-form-title" class="text-base font-semibold text-slate-950">{editingProductId ? "Edit product" : "Add product"}</h2><p class="mt-1 text-xs text-slate-500">Preview form · no changes are sent to a backend.</p></div><button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close product form" on:click={closeProductForm}>×</button></div>
            <form class="mt-6 space-y-4" on:submit|preventDefault={saveProduct}>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="product-name" class="mb-1.5 block text-xs font-medium text-slate-700">Product name <span class="text-rose-600">*</span></label><input id="product-name" bind:value={productForm.name} class:border-rose-300={productErrors.name} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />{#if productErrors.name}<p class="mt-1 text-[11px] text-rose-600">{productErrors.name}</p>{/if}</div><div><label for="product-sku" class="mb-1.5 block text-xs font-medium text-slate-700">SKU / product code <span class="text-rose-600">*</span></label><input id="product-sku" bind:value={productForm.sku} class:border-rose-300={productErrors.sku} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm uppercase outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />{#if productErrors.sku}<p class="mt-1 text-[11px] text-rose-600">{productErrors.sku}</p>{/if}</div></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="product-category" class="mb-1.5 block text-xs font-medium text-slate-700">Category <span class="text-rose-600">*</span></label><select id="product-category" bind:value={productForm.category} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{#each categories as category}<option value={category}>{category}</option>{/each}</select>{#if productErrors.category}<p class="mt-1 text-[11px] text-rose-600">{productErrors.category}</p>{/if}</div><div><label for="product-price" class="mb-1.5 block text-xs font-medium text-slate-700">Selling price <span class="text-rose-600">*</span></label><input id="product-price" bind:value={productForm.selling_price} type="number" min="0" step="0.01" class:border-rose-300={productErrors.selling_price} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />{#if productErrors.selling_price}<p class="mt-1 text-[11px] text-rose-600">{productErrors.selling_price}</p>{/if}</div></div>
                <div><label for="product-description" class="mb-1.5 block text-xs font-medium text-slate-700">Description</label><textarea id="product-description" bind:value={productForm.description} rows="3" class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="product-stock" class="mb-1.5 block text-xs font-medium text-slate-700">Initial stock quantity <span class="text-rose-600">*</span></label><input id="product-stock" bind:value={productForm.stock_quantity} type="number" min="0" step="1" class:border-rose-300={productErrors.stock_quantity} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />{#if productErrors.stock_quantity}<p class="mt-1 text-[11px] text-rose-600">{productErrors.stock_quantity}</p>{/if}</div><div><label for="product-threshold" class="mb-1.5 block text-xs font-medium text-slate-700">Low-stock threshold <span class="text-rose-600">*</span></label><input id="product-threshold" bind:value={productForm.reorder_threshold} type="number" min="0" step="1" class:border-rose-300={productErrors.reorder_threshold} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />{#if productErrors.reorder_threshold}<p class="mt-1 text-[11px] text-rose-600">{productErrors.reorder_threshold}</p>{/if}</div></div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-5"><button type="button" class="rounded-md border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" on:click={closeProductForm}>Cancel</button><button type="submit" class="rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700">{editingProductId ? "Save changes" : "Add product"}</button></div>
            </form>
        </dialog>
    </div>
{/if}

{#if showStockForm && stockProduct}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4" role="presentation" on:click={(event) => event.target === event.currentTarget && closeStockForm()}>
        <dialog open class="static m-0 w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 shadow-xl" aria-labelledby="stock-form-title">
            <div class="flex items-start justify-between"><div><h2 id="stock-form-title" class="text-base font-semibold text-slate-950">Adjust stock</h2><p class="mt-1 text-xs text-slate-500">{stockProduct.name} · current stock {stockProduct.stock_quantity}</p></div><button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close stock form" on:click={closeStockForm}>×</button></div>
            <form class="mt-6 space-y-4" on:submit|preventDefault={saveStockAdjustment}>
                <div><label for="stock-adjustment-type" class="mb-1.5 block text-xs font-medium text-slate-700">Adjustment type <span class="text-rose-600">*</span></label><select id="stock-adjustment-type" bind:value={stockForm.adjustment_type} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="received">Add stock received</option><option value="reduction">Reduce or correct stock</option></select></div>
                <div><label for="stock-quantity" class="mb-1.5 block text-xs font-medium text-slate-700">Quantity <span class="text-rose-600">*</span></label><input id="stock-quantity" bind:value={stockForm.quantity} type="number" min="1" step="1" class:border-rose-300={stockErrors.quantity} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />{#if stockErrors.quantity}<p class="mt-1 text-[11px] text-rose-600">{stockErrors.quantity}</p>{/if}</div>
                <div><label for="stock-note" class="mb-1.5 block text-xs font-medium text-slate-700">Reason or note <span class="text-rose-600">*</span></label><textarea id="stock-note" bind:value={stockForm.note} rows="3" placeholder="e.g. Received supplier delivery" class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea>{#if stockErrors.note}<p class="mt-1 text-[11px] text-rose-600">{stockErrors.note}</p>{/if}</div>
                {#if stockForm.adjustment_type === "reduction"}<label class="flex items-start gap-2 text-xs text-slate-600"><input bind:checked={stockForm.confirmed} type="checkbox" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500" /><span>I confirm this stock reduction is intentional.</span></label>{#if stockErrors.confirmed}<p class="text-[11px] text-rose-600">{stockErrors.confirmed}</p>{/if}{/if}
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-5"><button type="button" class="rounded-md border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" on:click={closeStockForm}>Cancel</button><button type="submit" class="rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700">Save adjustment</button></div>
            </form>
        </dialog>
    </div>
{/if}
