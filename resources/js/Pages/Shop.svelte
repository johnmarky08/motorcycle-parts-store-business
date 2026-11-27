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
    const MAX_PER_LINE = 1000; // same limit as the server's cart validation

    // Product ids with a cart request in flight: their buttons show "Adding…" and ignore extra clicks.
    let busy = new Set();

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

    $: counts = products.reduce(
        (map, product) => ({
            ...map,
            [product.category]: (map[product.category] ?? 0) + 1,
        }),
        {},
    );
    $: filtered = products
        .filter((product) => {
            const query = search.trim().toLowerCase();
            const matchesSearch =
                !query ||
                `${product.name} ${product.sku} ${product.category} ${product.description ?? ""}`
                    .toLowerCase()
                    .includes(query);
            return (
                matchesSearch &&
                (category === "All" || product.category === category)
            );
        })
        .sort((a, b) => {
            if (sort === "price_asc") return a.price - b.price;
            if (sort === "price_desc") return b.price - a.price;
            if (sort === "stock")
                return (
                    (b.stock > 0) - (a.stock > 0) ||
                    a.name.localeCompare(b.name)
                );
            return a.name.localeCompare(b.name);
        });
    $: shown = filtered.slice(0, visible);
    $: inCart = Object.fromEntries(
        cart.items.map((item) => [item.product_id, item.quantity]),
    );

    // Messages that arrive with a full page load (for example after checkout fails) appear as a toast.
    onMount(() => {
        if (status || error) showFlash({ status, error });
    });

    function showFlash(props) {
        if (props?.error) showToast({ type: "error", text: props.error });
        else if (props?.status) showToast({ type: "success", text: props.status });
    }

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

    // Cart changes reload only the cart and its message, without the page-wide loading bar:
    // the button that was clicked shows its own progress instead.
    function cartRequest(method, url, data, key, onDone = null) {
        if (busy.has(key)) return;
        router.visit(url, {
            method,
            data,
            only: ["cart", "status", "error"],
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            onStart: () => (busy = new Set(busy).add(key)),
            onFinish: () => {
                const next = new Set(busy);
                next.delete(key);
                busy = next;
                onDone?.();
            },
            onSuccess: (result) => showFlash(result.props),
            onError: () => showToast({ type: "error", text: "The cart could not be updated. Please try again." }),
        });
    }

    function addToCart(product, quantity = 1) {
        cartRequest("post", "/cart", { product_id: product.id, quantity }, product.id);
    }

    function changeQuantity(item, quantity, onDone = null) {
        if (quantity < 1) return;
        cartRequest("patch", `/cart/${item.product_id}`, { quantity }, item.product_id, onDone);
    }

    // Turns typed text into a whole number from 1 to the stock available (null when it is not a number).
    function cleanQuantity(raw, stock, name) {
        const typed = Math.floor(Number(String(raw).trim()));
        if (!Number.isFinite(typed) || String(raw).trim() === "") return null;
        const max = Math.max(1, Math.min(stock, MAX_PER_LINE));
        if (typed > max)
            showToast({
                type: "error",
                text: stock > MAX_PER_LINE
                    ? `You can order up to ${MAX_PER_LINE} units of one item at a time.`
                    : `Only ${stock} units of ${name} are available.`,
            });
        return Math.min(Math.max(typed, 1), max);
    }

    // A quantity typed in the cart is saved when the field loses focus or Enter is pressed.
    function commitCartQuantity(item, input) {
        const quantity = cleanQuantity(input.value, item.stock, item.name);
        if (quantity === null || quantity === item.quantity) {
            input.value = String(item.quantity);
            return;
        }
        input.value = String(quantity);
        // Afterwards show what the server really saved (it may refuse if stock changed meanwhile).
        changeQuantity(item, quantity, () => {
            const saved = cart.items.find((line) => line.product_id === item.product_id);
            if (saved) input.value = String(saved.quantity);
        });
    }

    function commitSelectedQuantity(input) {
        const quantity = cleanQuantity(input.value, selected.stock, selected.name) ?? selectedQuantity;
        selectedQuantity = quantity;
        input.value = String(quantity);
    }

    function removeItem(item) {
        cartRequest("delete", `/cart/${item.product_id}`, {}, item.product_id);
    }

    function clearCart() {
        cartRequest("delete", "/cart", {}, "all");
    }

    function placeOrder() {
        if (placing || cart.items.length === 0) return;
        router.post(
            "/checkout",
            { payment_method: paymentMethod, idempotency_key: idempotencyKey },
            {
                onStart: () => (placing = true),
                onFinish: () => (placing = false),
                onSuccess: (result) => {
                    // A refused checkout (for example not enough stock) comes back to the shop with an error.
                    if (result.props?.error) {
                        showFlash(result.props);
                        return;
                    }
                    idempotencyKey = crypto.randomUUID(); // a new order needs a new key
                    cartOpen = false;
                },
            },
        );
    }
</script>

<svelte:head><title>Shop | One Prompt Away</title></svelte:head>

{#snippet cartPanel(onClose = null)}
    <div class="flex h-full min-h-0 flex-col">
        <!-- Title, "Clear Cart" and the close button share one row in normal flow, so they can never overlap. -->
        <div
            class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"
        >
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-slate-950">Your Cart</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {cart.count}
                    {cart.count === 1 ? "Item" : "Items"}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                {#if cart.items.length}<button
                        type="button"
                        class="rounded-md px-2 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                        on:click={clearCart}>Clear Cart</button
                    >{/if}
                {#if onClose}<button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close Cart"
                        on:click={onClose}
                        ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button
                    >{/if}
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5">
            {#each cart.items as item (item.product_id)}
                <div
                    class="flex gap-3 border-b border-slate-100 py-4 last:border-0"
                >
                    <img
                        src={item.image}
                        alt={item.name}
                        class="h-16 w-16 shrink-0 rounded-md border border-slate-100 bg-slate-50 object-cover"
                        loading="lazy"
                    />
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-xs font-semibold text-slate-800"
                        >
                            {item.name}
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-400">
                            {formatPrice(item.unit_price)} Each
                        </p>
                        <div class="mt-2 flex items-center justify-between">
                            <div
                                class="flex items-center rounded-md border border-slate-200"
                            >
                                <button
                                    type="button"
                                    class="h-7 w-7 text-sm text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                    aria-label={`Decrease ${item.name}`}
                                    disabled={item.quantity <= 1 || busy.has(item.product_id)}
                                    on:click={() =>
                                        changeQuantity(item, item.quantity - 1)}
                                    >−</button
                                >
                                <input
                                    type="number"
                                    inputmode="numeric"
                                    min="1"
                                    max={item.stock}
                                    step="1"
                                    value={item.quantity}
                                    disabled={busy.has(item.product_id)}
                                    aria-label={`Quantity of ${item.name}`}
                                    class="h-7 w-12 border-x border-slate-200 bg-white text-center text-xs font-semibold text-slate-900 outline-none [appearance:textfield] focus:ring-2 focus:ring-inset focus:ring-blue-200 disabled:opacity-60 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                    on:focus={(event) => event.currentTarget.select()}
                                    on:change={(event) => commitCartQuantity(item, event.currentTarget)}
                                    on:keydown={(event) => {
                                        if (event.key === "Enter") event.currentTarget.blur();
                                        if (event.key === "Escape") {
                                            event.currentTarget.value = String(item.quantity);
                                            event.currentTarget.blur();
                                        }
                                    }}
                                />
                                <button
                                    type="button"
                                    class="h-7 w-7 text-sm text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                    aria-label={`Increase ${item.name}`}
                                    disabled={item.quantity >= item.stock || busy.has(item.product_id)}
                                    on:click={() =>
                                        changeQuantity(item, item.quantity + 1)}
                                    >+</button
                                >
                            </div>
                            <span class="text-xs font-semibold text-slate-900"
                                >{formatPrice(item.line_total)}</span
                            >
                        </div>
                    </div>
                    <button
                        type="button"
                        class="self-start rounded-md p-1 text-slate-300 hover:bg-rose-50 hover:text-rose-600"
                        aria-label={`Remove ${item.name}`}
                        on:click={() => removeItem(item)}
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="h-4 w-4"
                            aria-hidden="true"
                            ><path
                                d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"
                            /></svg
                        >
                    </button>
                </div>
            {:else}
                <div
                    class="flex h-full min-h-48 flex-col items-center justify-center text-center"
                >
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            class="h-5 w-5"
                            aria-hidden="true"
                            ><path
                                d="M3 4h2l2.4 11h10.2L20 7H6.5M9 20h.01M17 20h.01"
                            /></svg
                        >
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-800">
                        Your Cart Is Empty
                    </p>
                    <p class="mt-1 max-w-56 text-xs text-slate-500">
                        Add parts from the catalog and they will appear here.
                    </p>
                </div>
            {/each}
        </div>

        <div class="space-y-4 border-t border-slate-200 px-5 py-4">
            <fieldset>
                <legend class="mb-2 text-xs font-medium text-slate-700"
                    >Payment Method</legend
                >
                <div class="grid grid-cols-2 gap-2">
                    {#each paymentMethods as method}
                        <label
                            class:border-blue-500={paymentMethod === method}
                            class:bg-blue-50={paymentMethod === method}
                            class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs text-slate-700"
                        >
                            <input
                                type="radio"
                                name="payment_method"
                                value={method}
                                bind:group={paymentMethod}
                                class="accent-blue-600"
                            />
                            {method}
                        </label>
                    {/each}
                </div>
            </fieldset>
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-600">Subtotal</span><span
                    class="text-lg font-semibold text-slate-950"
                    >{formatPrice(cart.subtotal)}</span
                >
            </div>
            <Button
                type="button"
                className="h-10 w-full"
                disabled={placing || cart.items.length === 0}
                onclick={placeOrder}
                >{placing ? "Placing Order…" : "Place Order"}</Button
            >
            <p class="text-center text-[11px] text-slate-400">
                Prices and stock are confirmed again when you place the order.
            </p>
        </div>
    </div>
{/snippet}

<div
    class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen"
>
    <AppSidebar activeItem="Shop" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Shop" bind:mobileMenuOpen />
        <div class="flex min-h-0 flex-1">
            <main class="min-h-0 min-w-0 flex-1 overflow-y-auto">
                <div class="w-full px-4 py-6 sm:px-8 sm:py-7">
                    <div
                        class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <p class="mb-2 text-xs font-medium text-slate-500">
                                Welcome, {auth.user?.name?.split(" ")[0]}
                            </p>
                            <h1
                                class="text-2xl font-semibold tracking-tight text-slate-950"
                            >
                                Motorcycle Parts Shop
                            </h1>
                            <p class="mt-1 text-sm text-slate-500">
                                Browse {products.length} genuine and aftermarket
                                parts, add them to your cart and place your order.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex w-fit items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700 xl:hidden"
                            on:click={() => (cartOpen = true)}
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-4 w-4"
                                aria-hidden="true"
                                ><path
                                    d="M3 4h2l2.4 11h10.2L20 7H6.5M9 20h.01M17 20h.01"
                                /></svg
                            >
                            View Cart ({cart.count})
                        </button>
                    </div>

                    <div
                        class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem]"
                    >
                        <input
                            bind:value={search}
                            on:input={() => (visible = PAGE_SIZE)}
                            type="search"
                            placeholder="Search Parts, SKU Or Category"
                            aria-label="Search Products"
                            class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                        <select
                            bind:value={sort}
                            aria-label="Sort Products"
                            class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="name">Name: A To Z</option><option
                                value="price_asc">Price: Low To High</option
                            ><option value="price_desc"
                                >Price: High To Low</option
                            ><option value="stock">In Stock First</option>
                        </select>
                    </div>

                    <div
                        class="mb-5 flex flex-wrap gap-2"
                        role="group"
                        aria-label="Categories"
                    >
                        {#each ["All", ...categories.map((item) => item.name)] as name}
                            <button
                                type="button"
                                class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors {category === name
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-900'}"
                                aria-pressed={category === name}
                                on:click={() => setCategory(name)}
                            >
                                {name}
                                <span class="ml-1 opacity-60"
                                    >{name === "All"
                                        ? products.length
                                        : (counts[name] ?? 0)}</span
                                >
                            </button>
                        {/each}
                    </div>

                    <p class="mb-3 text-xs text-slate-500">
                        Showing {shown.length} Of {filtered.length} Products
                    </p>

                    {#if filtered.length === 0}
                        <div
                            class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500"
                        >
                            No parts match your search. Try another keyword or
                            category.
                        </div>
                    {:else}
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            {#each shown as product (product.id)}
                                <article
                                    class="group flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md"
                                >
                                    <button
                                        type="button"
                                        class="block overflow-hidden bg-slate-50"
                                        aria-label={`View ${product.name}`}
                                        on:click={() => openProduct(product)}
                                    >
                                        <img
                                            src={product.image}
                                            alt={product.name}
                                            class="aspect-4/3 w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                                            loading="lazy"
                                        />
                                    </button>
                                    <div class="flex flex-1 flex-col p-4">
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <span
                                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                                                >{product.category}</span
                                            ><span
                                                class:text-emerald-700={product.stock >
                                                    5}
                                                class:text-amber-600={product.stock >
                                                    0 && product.stock <= 5}
                                                class:text-rose-600={product.stock ===
                                                    0}
                                                class="text-[10px] font-semibold"
                                                >{stockLabel(product)}</span
                                            >
                                        </div>
                                        <button
                                            type="button"
                                            class="mt-2 text-left text-sm font-semibold leading-5 text-slate-900 hover:text-blue-700"
                                            on:click={() =>
                                                openProduct(product)}
                                            >{product.name}</button
                                        >
                                        <p
                                            class="mt-0.5 text-[11px] text-slate-400"
                                        >
                                            {product.sku}
                                        </p>
                                        <div
                                            class="mt-auto flex items-center justify-between gap-3 pt-4"
                                        >
                                            <span
                                                class="text-base font-semibold text-slate-950"
                                                >{formatPrice(
                                                    product.price,
                                                )}</span
                                            >
                                            <Button
                                                type="button"
                                                className="h-8 text-xs"
                                                disabled={product.stock === 0 ||
                                                    busy.has(product.id) ||
                                                    (inCart[product.id] ?? 0) >=
                                                        product.stock}
                                                onclick={() =>
                                                    addToCart(product)}
                                                >{product.stock === 0
                                                    ? "Sold Out"
                                                    : busy.has(product.id)
                                                      ? "Adding…"
                                                      : inCart[product.id]
                                                      ? `In Cart (${inCart[product.id]})`
                                                      : "Add To Cart"}</Button
                                            >
                                        </div>
                                    </div>
                                </article>
                            {/each}
                        </div>
                        {#if shown.length < filtered.length}
                            <div class="mt-6 text-center">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-9 text-xs"
                                    onclick={() => (visible += PAGE_SIZE)}
                                    >Show More Parts</Button
                                >
                            </div>
                        {/if}
                    {/if}
                </div>
            </main>

            <aside
                class="hidden w-95 shrink-0 border-l border-slate-200 bg-white xl:block"
                aria-label="Shopping Cart"
            >
                {@render cartPanel()}
            </aside>
        </div>
    </div>
</div>

{#if cartOpen}
    <div class="fixed inset-0 z-50 xl:hidden" role="presentation">
        <button
            type="button"
            transition:fade={{ duration: 160 }}
            class="absolute inset-0 bg-slate-950/30"
            aria-label="Close Cart"
            on:click={() => (cartOpen = false)}
        ></button>
        <aside
            transition:fly={{ x: 380, duration: 220 }}
            class="absolute inset-y-0 right-0 w-full max-w-100 bg-white shadow-xl"
            aria-label="Shopping Cart"
        >
            {@render cartPanel(() => (cartOpen = false))}
        </aside>
    </div>
{/if}

{#if selected}
    <div
        transition:fade={{ duration: 160 }}
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4"
        role="presentation"
        on:click={(event) =>
            event.target === event.currentTarget && (selected = null)}
    >
        <dialog
            open
            transition:pop|global
            class="m-0 max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 shadow-xl"
            aria-labelledby="product-title"
        >
            <div class="grid md:grid-cols-2">
                <img
                    src={selected.image}
                    alt={selected.name}
                    class="aspect-4/3 w-full bg-slate-50 object-cover md:aspect-auto md:h-full"
                />
                <div class="p-6">
                    <div class="flex items-start justify-between gap-3">
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                            >{selected.category}</span
                        ><button
                            type="button"
                            class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700"
                            aria-label="Close Product"
                            on:click={() => (selected = null)}>×</button
                        >
                    </div>
                    <h2
                        id="product-title"
                        class="mt-3 text-lg font-semibold leading-6 text-slate-950"
                    >
                        {selected.name}
                    </h2>
                    <p class="mt-1 text-xs text-slate-400">
                        SKU {selected.sku}
                    </p>
                    <p class="mt-4 text-sm leading-6 text-slate-600">
                        {selected.description ||
                            "A quality replacement part for your motorcycle."}
                    </p>
                    <p class="mt-4 text-2xl font-semibold text-slate-950">
                        {formatPrice(selected.price)}
                    </p>
                    <p
                        class:text-emerald-700={selected.stock > 5}
                        class:text-amber-600={selected.stock > 0 &&
                            selected.stock <= 5}
                        class:text-rose-600={selected.stock === 0}
                        class="mt-1 text-xs font-semibold"
                    >
                        {stockLabel(selected)}
                    </p>
                    {#if selected.stock > 0}
                        <div class="mt-5 flex items-center gap-3">
                            <div
                                class="flex items-center rounded-md border border-slate-200"
                            >
                                <button
                                    type="button"
                                    class="h-9 w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                    aria-label="Decrease Quantity"
                                    disabled={selectedQuantity <= 1}
                                    on:click={() => (selectedQuantity -= 1)}
                                    >−</button
                                >
                                <input
                                    type="number"
                                    inputmode="numeric"
                                    min="1"
                                    max={selected.stock}
                                    step="1"
                                    value={selectedQuantity}
                                    aria-label="Quantity"
                                    class="h-9 w-14 border-x border-slate-200 bg-white text-center text-sm font-semibold text-slate-900 outline-none [appearance:textfield] focus:ring-2 focus:ring-inset focus:ring-blue-200 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                    on:focus={(event) => event.currentTarget.select()}
                                    on:change={(event) => commitSelectedQuantity(event.currentTarget)}
                                    on:keydown={(event) => event.key === "Enter" && event.currentTarget.blur()}
                                />
                                <button
                                    type="button"
                                    class="h-9 w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                    aria-label="Increase Quantity"
                                    disabled={selectedQuantity >=
                                        selected.stock}
                                    on:click={() => (selectedQuantity += 1)}
                                    >+</button
                                >
                            </div>
                            <Button
                                type="button"
                                className="h-9 flex-1"
                                onclick={() => {
                                    addToCart(selected, selectedQuantity);
                                    selected = null;
                                }}>Add To Cart</Button
                            >
                        </div>
                    {/if}
                </div>
            </div>
        </dialog>
    </div>
{/if}

{#if toast}
    <div
        transition:fly={{ y: -16, duration: 180 }}
        class:border-emerald-200={toast.type === "success"}
        class:bg-emerald-50={toast.type === "success"}
        class:text-emerald-800={toast.type === "success"}
        class:border-rose-200={toast.type === "error"}
        class:bg-rose-50={toast.type === "error"}
        class:text-rose-800={toast.type === "error"}
        class="pointer-events-none fixed left-1/2 top-20 z-60 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 rounded-md border px-4 py-3 text-xs shadow-lg"
        role={toast.type === "error" ? "alert" : "status"}
    >
        {toast.text}
    </div>
{/if}
