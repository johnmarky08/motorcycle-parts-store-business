<script>
    import { router } from "@inertiajs/svelte";
    import { onMount } from "svelte";
    import { fade, fly } from "svelte/transition";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import { pop } from "../lib/transitions.js";

    export let products = [];
    export let categories = [];
    export let cart = { items: [], subtotal: 0, count: 0 };
    export let paymentMethods = [];
    export let status = null;
    export let error = null;
    export let auth = { user: null };

    const PAGE_SIZE = 24;
    const partial = { preserveScroll: true, preserveState: true, only: ["cart", "status", "error"] };

    let mobileMenuOpen = false;
    let cartOpen = false;
    let search = "";
    let category = "All";
    let sort = "name";
    let visible = PAGE_SIZE;
    let selected = null;
    let selectedQuantity = 1;
    let paymentMethod = paymentMethods[0] ?? "Cash";
    let idempotencyKey = "";
    let placing = false;
    let toast = null;
    let toastTimer;

    onMount(() => {
        idempotencyKey = crypto.randomUUID();
    });

    $: counts = products.reduce((map, product) => ({ ...map, [product.category]: (map[product.category] ?? 0) + 1 }), {});
    $: filtered = products
        .filter((product) => {
            const query = search.trim().toLowerCase();
            const matchesSearch = !query || `${product.name} ${product.sku} ${product.category} ${product.description ?? ""}`.toLowerCase().includes(query);
            return matchesSearch && (category === "All" || product.category === category);
        })
        .sort((a, b) => {
            if (sort === "price_asc") return a.price - b.price;
            if (sort === "price_desc") return b.price - a.price;
            if (sort === "stock") return (b.stock > 0) - (a.stock > 0) || a.name.localeCompare(b.name);
            return a.name.localeCompare(b.name);
        });
    $: shown = filtered.slice(0, visible);
    $: inCart = Object.fromEntries(cart.items.map((item) => [item.product_id, item.quantity]));

    // Show server messages (added to cart, errors) as a short toast.
    $: if (status || error) showToast(error ? { type: "error", text: error } : { type: "success", text: status });

    function showToast(next) {
        toast = next;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toast = null), 3500);
    }

    function formatPrice(value) {
        return `₱${Number(value ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function stockLabel(product) {
        if (product.stock === 0) return "Out Of Stock";
        return product.stock <= 5 ? `Only ${product.stock} Left` : "In Stock";
    }

    function setCategory(next) {
        category = next;
        visible = PAGE_SIZE;
    }

    function openProduct(product) {
        selected = product;
        selectedQuantity = 1;
    }

    function addToCart(product, quantity = 1) {
        router.post("/cart", { product_id: product.id, quantity }, partial);
    }

    function changeQuantity(item, quantity) {
        if (quantity < 1) return;
        router.patch(`/cart/${item.product_id}`, { quantity }, partial);
    }

    function removeItem(item) {
        router.delete(`/cart/${item.product_id}`, partial);
    }

    function clearCart() {
        router.delete("/cart", partial);
    }

    function placeOrder() {
        if (placing || cart.items.length === 0) return;
        router.post("/checkout", { payment_method: paymentMethod, idempotency_key: idempotencyKey }, {
            onStart: () => (placing = true),
            onFinish: () => (placing = false),
            onSuccess: () => {
                idempotencyKey = crypto.randomUUID(); // a new order needs a new key
                cartOpen = false;
            },
        });
    }
</script>

<svelte:head><title>Shop | One Prompt Away</title></svelte:head>

{#snippet cartPanel()}
    <div class="flex h-full min-h-0 flex-col">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div><h2 class="text-sm font-semibold text-slate-950">Your Cart</h2><p class="mt-0.5 text-xs text-slate-500">{cart.count} {cart.count === 1 ? "Item" : "Items"}</p></div>
            {#if cart.items.length}<button type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-700" on:click={clearCart}>Clear Cart</button>{/if}
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5">
            {#each cart.items as item (item.product_id)}
                <div class="flex gap-3 border-b border-slate-100 py-4 last:border-0">
                    <img src={item.image} alt={item.name} class="h-16 w-16 shrink-0 rounded-md border border-slate-100 bg-slate-50 object-cover" loading="lazy" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-slate-800">{item.name}</p>
                        <p class="mt-0.5 text-[11px] text-slate-400">{formatPrice(item.unit_price)} Each</p>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex items-center rounded-md border border-slate-200">
                                <button type="button" class="h-7 w-7 text-sm text-slate-600 hover:bg-slate-50 disabled:opacity-40" aria-label={`Decrease ${item.name}`} disabled={item.quantity <= 1} on:click={() => changeQuantity(item, item.quantity - 1)}>−</button>
                                <span class="w-8 text-center text-xs font-semibold" aria-live="polite">{item.quantity}</span>
                                <button type="button" class="h-7 w-7 text-sm text-slate-600 hover:bg-slate-50 disabled:opacity-40" aria-label={`Increase ${item.name}`} disabled={item.quantity >= item.stock} on:click={() => changeQuantity(item, item.quantity + 1)}>+</button>
                            </div>
                            <span class="text-xs font-semibold text-slate-900">{formatPrice(item.line_total)}</span>
                        </div>
                    </div>
                    <button type="button" class="self-start rounded-md p-1 text-slate-300 hover:bg-rose-50 hover:text-rose-600" aria-label={`Remove ${item.name}`} on:click={() => removeItem(item)}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></svg>
                    </button>
                </div>
            {:else}
                <div class="flex h-full min-h-48 flex-col items-center justify-center text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5" aria-hidden="true"><path d="M3 4h2l2.4 11h10.2L20 7H6.5M9 20h.01M17 20h.01" /></svg></div>
                    <p class="mt-3 text-sm font-semibold text-slate-800">Your Cart Is Empty</p>
                    <p class="mt-1 max-w-[14rem] text-xs text-slate-500">Add parts from the catalog and they will appear here.</p>
                </div>
            {/each}
        </div>

        <div class="space-y-4 border-t border-slate-200 px-5 py-4">
            <fieldset>
                <legend class="mb-2 text-xs font-medium text-slate-700">Payment Method</legend>
                <div class="grid grid-cols-2 gap-2">
                    {#each paymentMethods as method}
                        <label class:border-blue-500={paymentMethod === method} class:bg-blue-50={paymentMethod === method} class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs text-slate-700">
                            <input type="radio" name="payment_method" value={method} bind:group={paymentMethod} class="accent-blue-600" /> {method}
                        </label>
                    {/each}
                </div>
            </fieldset>
            <div class="flex items-center justify-between"><span class="text-sm text-slate-600">Subtotal</span><span class="text-lg font-semibold text-slate-950">{formatPrice(cart.subtotal)}</span></div>
            <Button type="button" className="h-10 w-full" disabled={placing || cart.items.length === 0} onclick={placeOrder}>{placing ? "Placing Order…" : "Place Order"}</Button>
            <p class="text-center text-[11px] text-slate-400">Prices and stock are confirmed again when you place the order.</p>
        </div>
    </div>
{/snippet}

<div class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen">
    <AppSidebar activeItem="Shop" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Shop" bind:mobileMenuOpen />
        <div class="flex min-h-0 flex-1">
            <main class="min-h-0 min-w-0 flex-1 overflow-y-auto">
                <div class="mx-auto max-w-[1180px] px-4 py-6 sm:px-8 sm:py-7">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="mb-2 text-xs font-medium text-slate-500">Welcome, {auth.user?.name?.split(" ")[0]}</p>
                            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Motorcycle Parts Shop</h1>
                            <p class="mt-1 text-sm text-slate-500">Browse {products.length} genuine and aftermarket parts, add them to your cart and place your order.</p>
                        </div>
                        <button type="button" class="flex w-fit items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700 xl:hidden" on:click={() => (cartOpen = true)}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M3 4h2l2.4 11h10.2L20 7H6.5M9 20h.01M17 20h.01" /></svg>
                            View Cart ({cart.count})
                        </button>
                    </div>

                    <div class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem]">
                        <input bind:value={search} on:input={() => (visible = PAGE_SIZE)} type="search" placeholder="Search Parts, SKU Or Category" aria-label="Search Products" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                        <select bind:value={sort} aria-label="Sort Products" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="name">Name: A To Z</option><option value="price_asc">Price: Low To High</option><option value="price_desc">Price: High To Low</option><option value="stock">In Stock First</option>
                        </select>
                    </div>

                    <div class="mb-5 flex flex-wrap gap-2" role="group" aria-label="Categories">
                        {#each ["All", ...categories.map((item) => item.name)] as name}
                            <button type="button" class:bg-slate-900={category === name} class:text-white={category === name} class:border-slate-900={category === name} class:text-slate-600={category !== name} class="rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold hover:border-slate-400" on:click={() => setCategory(name)}>
                                {name} <span class="ml-1 opacity-60">{name === "All" ? products.length : (counts[name] ?? 0)}</span>
                            </button>
                        {/each}
                    </div>

                    <p class="mb-3 text-xs text-slate-500">Showing {shown.length} Of {filtered.length} Products</p>

                    {#if filtered.length === 0}
                        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No parts match your search. Try another keyword or category.</div>
                    {:else}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {#each shown as product (product.id)}
                                <article class="group flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                                    <button type="button" class="block overflow-hidden bg-slate-50" aria-label={`View ${product.name}`} on:click={() => openProduct(product)}>
                                        <img src={product.image} alt={product.name} class="aspect-[4/3] w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]" loading="lazy" />
                                    </button>
                                    <div class="flex flex-1 flex-col p-4">
                                        <div class="flex items-center justify-between gap-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{product.category}</span><span class:text-emerald-700={product.stock > 5} class:text-amber-600={product.stock > 0 && product.stock <= 5} class:text-rose-600={product.stock === 0} class="text-[10px] font-semibold">{stockLabel(product)}</span></div>
                                        <button type="button" class="mt-2 text-left text-sm font-semibold leading-5 text-slate-900 hover:text-blue-700" on:click={() => openProduct(product)}>{product.name}</button>
                                        <p class="mt-0.5 text-[11px] text-slate-400">{product.sku}</p>
                                        <div class="mt-auto flex items-center justify-between gap-3 pt-4">
                                            <span class="text-base font-semibold text-slate-950">{formatPrice(product.price)}</span>
                                            <Button type="button" className="h-8 text-xs" disabled={product.stock === 0 || (inCart[product.id] ?? 0) >= product.stock} onclick={() => addToCart(product)}>{product.stock === 0 ? "Sold Out" : inCart[product.id] ? `In Cart (${inCart[product.id]})` : "Add To Cart"}</Button>
                                        </div>
                                    </div>
                                </article>
                            {/each}
                        </div>
                        {#if shown.length < filtered.length}
                            <div class="mt-6 text-center"><Button type="button" variant="outline" className="h-9 text-xs" onclick={() => (visible += PAGE_SIZE)}>Show More Parts</Button></div>
                        {/if}
                    {/if}
                </div>
            </main>

            <aside class="hidden w-[380px] shrink-0 border-l border-slate-200 bg-white xl:block" aria-label="Shopping Cart">{@render cartPanel()}</aside>
        </div>
    </div>
</div>

{#if cartOpen}
    <div class="fixed inset-0 z-50 xl:hidden" role="presentation">
        <button type="button" transition:fade={{ duration: 160 }} class="absolute inset-0 bg-slate-950/30" aria-label="Close Cart" on:click={() => (cartOpen = false)}></button>
        <aside transition:fly={{ x: 380, duration: 220 }} class="absolute inset-y-0 right-0 w-full max-w-[400px] bg-white shadow-xl" aria-label="Shopping Cart">
            <button type="button" class="absolute right-3 top-3 z-10 rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close Cart" on:click={() => (cartOpen = false)}>×</button>
            {@render cartPanel()}
        </aside>
    </div>
{/if}

{#if selected}
    <div transition:fade={{ duration: 160 }} class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4" role="presentation" on:click={(event) => event.target === event.currentTarget && (selected = null)}>
        <dialog open transition:pop|global class="m-0 max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 shadow-xl" aria-labelledby="product-title">
            <div class="grid md:grid-cols-2">
                <img src={selected.image} alt={selected.name} class="aspect-[4/3] w-full bg-slate-50 object-cover md:aspect-auto md:h-full" />
                <div class="p-6">
                    <div class="flex items-start justify-between gap-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{selected.category}</span><button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close Product" on:click={() => (selected = null)}>×</button></div>
                    <h2 id="product-title" class="mt-3 text-lg font-semibold leading-6 text-slate-950">{selected.name}</h2>
                    <p class="mt-1 text-xs text-slate-400">SKU {selected.sku}</p>
                    <p class="mt-4 text-sm leading-6 text-slate-600">{selected.description || "A quality replacement part for your motorcycle."}</p>
                    <p class="mt-4 text-2xl font-semibold text-slate-950">{formatPrice(selected.price)}</p>
                    <p class:text-emerald-700={selected.stock > 5} class:text-amber-600={selected.stock > 0 && selected.stock <= 5} class:text-rose-600={selected.stock === 0} class="mt-1 text-xs font-semibold">{stockLabel(selected)}</p>
                    {#if selected.stock > 0}
                        <div class="mt-5 flex items-center gap-3">
                            <div class="flex items-center rounded-md border border-slate-200">
                                <button type="button" class="h-9 w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40" aria-label="Decrease Quantity" disabled={selectedQuantity <= 1} on:click={() => (selectedQuantity -= 1)}>−</button>
                                <span class="w-10 text-center text-sm font-semibold">{selectedQuantity}</span>
                                <button type="button" class="h-9 w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40" aria-label="Increase Quantity" disabled={selectedQuantity >= selected.stock} on:click={() => (selectedQuantity += 1)}>+</button>
                            </div>
                            <Button type="button" className="h-9 flex-1" onclick={() => { addToCart(selected, selectedQuantity); selected = null; }}>Add To Cart</Button>
                        </div>
                    {/if}
                </div>
            </div>
        </dialog>
    </div>
{/if}

{#if toast}
    <div transition:fly={{ y: 16, duration: 180 }} class:border-emerald-200={toast.type === "success"} class:bg-emerald-50={toast.type === "success"} class:text-emerald-800={toast.type === "success"} class:border-rose-200={toast.type === "error"} class:bg-rose-50={toast.type === "error"} class:text-rose-800={toast.type === "error"} class="fixed bottom-5 left-1/2 z-[60] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 rounded-md border px-4 py-3 text-xs shadow-lg" role={toast.type === "error" ? "alert" : "status"}>{toast.text}</div>
{/if}
