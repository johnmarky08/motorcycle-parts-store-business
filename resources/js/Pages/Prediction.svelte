<script>
    import { onDestroy } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";

    const workflows = [
        { id: "demand", label: "Product Demand", description: "Estimate units needed for a selected part.", icon: "box" },
        { id: "sales", label: "Sales Prediction", description: "Preview a future sales trend.", icon: "sales" },
        { id: "slow", label: "Slow-Moving Products", description: "Identify parts at risk of slow movement.", icon: "warning" },
        { id: "best", label: "Best-Selling Products", description: "Rank likely top-performing parts.", icon: "star" },
    ];
    const products = [
        { id: "42", name: "Kawasaki Ninja Brake Pad", sku: "BP-KN-004" },
        { id: "27", name: "Yamaha MT-15 Air Filter", sku: "AF-YM-021" },
        { id: "18", name: "Honda Click Drive Belt", sku: "DB-HC-018" },
    ];
    const models = [
        { value: "simple_linear_regression", label: "Simple Linear Regression", helper: "A lightweight trend-based estimate." },
        { value: "random_forest_regression", label: "Random Forest Regression", helper: "A tree-based estimate for richer historical patterns." },
    ];
    const slowProducts = [
        { name: "Universal Chain Adjuster", sku: "CA-UNI-09", predicted: "4 units / 30 days", risk: "High risk" },
        { name: "Suzuki Raider Clutch Cable", sku: "CC-SR-015", predicted: "6 units / 30 days", risk: "Medium risk" },
        { name: "LED Signal Light Set", sku: "SL-LED-030", predicted: "8 units / 30 days", risk: "Medium risk" },
    ];
    const bestProducts = [
        { name: "Kawasaki Ninja Brake Pad", sku: "BP-KN-004", predicted: "145 units", value: "₱43,500" },
        { name: "Yamaha MT-15 Air Filter", sku: "AF-YM-021", predicted: "112 units", value: "₱33,600" },
        { name: "Honda Click Drive Belt", sku: "DB-HC-018", predicted: "89 units", value: "₱26,700" },
    ];

    let workflow = "demand";
    let productId = "";
    let modelName = "simple_linear_regression";
    let horizon = "30";
    let historicalPeriod = "12";
    let category = "all";
    let scope = "all";
    let errors = {};
    let status = "initial";
    let result = null;
    let runTimer;
    let mobileMenuOpen = false;

    $: selectedProduct = products.find((product) => product.id === productId);
    $: selectedModel = models.find((model) => model.value === modelName);
    $: selectedWorkflow = workflows.find((item) => item.id === workflow);

    function selectWorkflow(nextWorkflow) {
        workflow = nextWorkflow;
        errors = {};
        result = null;
        status = "initial";
    }

    function validate() {
        const nextErrors = {};
        if (workflow === "demand" && !productId) nextErrors.product = "Select a product to forecast.";
        if ((workflow === "demand" || workflow === "sales") && (!horizon || !Number.isInteger(Number(horizon)) || Number(horizon) < 1 || Number(horizon) > 365)) {
            nextErrors.horizon = "Enter a whole number from 1 to 365 days.";
        }
        if (workflow === "sales" && (!historicalPeriod || Number(historicalPeriod) < 3)) {
            nextErrors.period = "Use at least 3 months of illustrative history.";
        }
        errors = nextErrors;
        return Object.keys(nextErrors).length === 0;
    }

    function runPrediction() {
        if (runTimer) window.clearTimeout(runTimer);
        result = null;
        status = "initial";
        if (!validate()) {
            status = "validation_error";
            return;
        }
        status = "loading";
        runTimer = window.setTimeout(() => {
            result = { workflow, generatedAt: "Oct 28, 2026", model: selectedModel?.label || "Preview analysis" };
            status = workflow === "slow" ? "insufficient_data" : "result";
        }, 700);
    }

    function reset() {
        if (runTimer) window.clearTimeout(runTimer);
        productId = "";
        modelName = "simple_linear_regression";
        horizon = "30";
        historicalPeriod = "12";
        category = "all";
        scope = "all";
        errors = {};
        result = null;
        status = "initial";
    }

    function showFailure() {
        if (runTimer) window.clearTimeout(runTimer);
        result = null;
        errors = {};
        status = "failure";
    }

    onDestroy(() => {
        if (runTimer) window.clearTimeout(runTimer);
    });
</script>

<svelte:head><title>Prediction | One Prompt Away</title></svelte:head>

<div class="flex h-screen min-h-[720px] overflow-hidden bg-slate-50 font-sans text-slate-900">
    <AppSidebar activeItem="Prediction" bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Prediction" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
                <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="mb-2 text-xs font-medium text-slate-500">Workspace / Prediction</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Prediction workspace</h1><p class="mt-1 max-w-3xl text-sm text-slate-500">Run focused forecast previews for demand, sales and product performance.</p></div>
                    <span class="w-fit rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-700">Preview only</span>
                </div>
                <div class="mb-6 flex items-start gap-3 rounded-lg border border-blue-100 bg-blue-50/70 px-4 py-3.5 text-xs text-blue-800"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 10v6M12 7h.01" /></svg><p><span class="font-semibold">Preview only —</span> all results are illustrative and are not generated by a trained model. Final inputs, horizons and output definitions need backend confirmation.</p></div>

                <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Prediction workflows">
                    {#each workflows as item}
                        <button type="button" class={`rounded-lg border p-4 text-left transition-colors ${workflow === item.id ? "border-blue-300 bg-blue-50/60" : "border-slate-200 bg-white hover:border-slate-300"}`} on:click={() => selectWorkflow(item.id)}>
                            <span class={`flex h-8 w-8 items-center justify-center rounded-md ${workflow === item.id ? "bg-blue-100 text-blue-700" : "bg-slate-100 text-slate-500"}`}>
                                {#if item.icon === "sales"}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M4 19V5m0 14h16" /><path d="m7 15 3-4 3 2 5-7" /></svg>{:else if item.icon === "warning"}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M12 3 3.5 19h17L12 3Z" /><path d="M12 9v4M12 16h.01" /></svg>{:else if item.icon === "star"}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z" /></svg>{:else}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" /><path d="m4 7.5 8 4.5 8-4.5M12 12v9" /></svg>{/if}
                            </span>
                            <p class="mt-3 text-xs font-semibold text-slate-900">{item.label}</p><p class="mt-1 text-[11px] leading-4 text-slate-500">{item.description}</p>
                        </button>
                    {/each}
                </section>

                <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <article class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="mb-6"><h2 class="text-sm font-semibold text-slate-900">{selectedWorkflow.label} inputs</h2><p class="mt-1 text-xs text-slate-500">{selectedWorkflow.description} Configure a local preview run.</p></div>
                        {#if status === "validation_error"}<div class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-700" role="alert">Review the highlighted fields before running a prediction.</div>{/if}
                        <form class="space-y-5" on:submit|preventDefault={runPrediction}>
                            {#if workflow === "demand"}
                                <div><label for="prediction-product" class="mb-1.5 block text-xs font-medium text-slate-700">Product <span class="text-rose-600">*</span></label><select id="prediction-product" bind:value={productId} class:border-rose-300={errors.product} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="">Select a product</option>{#each products as product}<option value={product.id}>{product.name} · {product.sku}</option>{/each}</select>{#if errors.product}<p class="mt-1 text-[11px] text-rose-600">{errors.product}</p>{/if}</div>
                                <div><label for="prediction-model" class="mb-1.5 block text-xs font-medium text-slate-700">Regression model <span class="text-rose-600">*</span></label><select id="prediction-model" bind:value={modelName} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{#each models as model}<option value={model.value}>{model.label}</option>{/each}</select><p class="mt-1.5 text-[11px] text-slate-400">{selectedModel.helper}</p></div>
                            {:else if workflow === "sales"}
                                <div><label for="prediction-history" class="mb-1.5 block text-xs font-medium text-slate-700">Historical sales range <span class="text-rose-600">*</span></label><select id="prediction-history" bind:value={historicalPeriod} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="12">Last 12 months</option><option value="6">Last 6 months</option><option value="3">Last 3 months</option><option value="2">Last 2 months</option></select>{#if errors.period}<p class="mt-1 text-[11px] text-rose-600">{errors.period}</p>{/if}</div>
                                <div><label for="prediction-sales-model" class="mb-1.5 block text-xs font-medium text-slate-700">Model</label><select id="prediction-sales-model" bind:value={modelName} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{#each models as model}<option value={model.value}>{model.label}</option>{/each}</select></div>
                            {:else if workflow === "slow"}
                                <div><label for="prediction-slow-scope" class="mb-1.5 block text-xs font-medium text-slate-700">Product scope</label><select id="prediction-slow-scope" bind:value={scope} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="all">All active products</option><option value="engine">Engine category</option><option value="brakes">Brake System category</option></select></div>
                                <div><label for="prediction-slow-period" class="mb-1.5 block text-xs font-medium text-slate-700">Historical period</label><select id="prediction-slow-period" bind:value={historicalPeriod} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="12">Last 12 months</option><option value="6">Last 6 months</option><option value="3">Last 3 months</option></select></div>
                            {:else}
                                <div><label for="prediction-best-category" class="mb-1.5 block text-xs font-medium text-slate-700">Product category</label><select id="prediction-best-category" bind:value={category} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="all">All categories</option><option value="brakes">Brake System</option><option value="engine">Engine</option><option value="drivetrain">Drivetrain</option></select></div>
                                <div><label for="prediction-best-period" class="mb-1.5 block text-xs font-medium text-slate-700">Forecast period</label><select id="prediction-best-period" bind:value={horizon} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="30">Next 30 days</option><option value="60">Next 60 days</option><option value="90">Next 90 days</option></select></div>
                            {/if}
                            {#if workflow === "demand" || workflow === "sales"}<div><label for="prediction-horizon" class="mb-1.5 block text-xs font-medium text-slate-700">Forecast horizon <span class="text-rose-600">*</span></label><div class="relative"><input id="prediction-horizon" bind:value={horizon} type="number" min="1" max="365" step="1" class:border-rose-300={errors.horizon} class="h-10 w-full rounded-md border border-slate-200 px-3 pr-16 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /><span class="pointer-events-none absolute right-3 top-2.5 text-xs text-slate-400">days</span></div>{#if errors.horizon}<p class="mt-1 text-[11px] text-rose-600">{errors.horizon}</p>{/if}</div>{/if}
                            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end"><button type="button" class="rounded-md border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" on:click={reset}>Reset</button><button type="submit" disabled={status === "loading"} class="flex items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60">{#if status === "loading"}<span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>Preparing preview...{:else}Run prediction{/if}</button></div>
                        </form>
                    </article>

                    <article class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4"><div><h2 class="text-sm font-semibold text-slate-900">Prediction result</h2><p class="mt-1 text-xs text-slate-500">{selectedWorkflow.label} output preview.</p></div>{#if status === "result"}<span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">Sample result</span>{/if}</div>
                        {#if status === "initial" || status === "validation_error"}<div class="flex min-h-[360px] flex-col items-center justify-center px-4 text-center"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5" aria-hidden="true"><path d="M4 19V5m0 14h16" /><path d="m7 15 3-4 3 2 5-7" /></svg></div><h3 class="mt-3 text-sm font-semibold text-slate-800">No prediction yet</h3><p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">Configure this workflow and run the preview to see illustrative output.</p><button type="button" class="mt-5 text-[11px] font-semibold text-slate-400 underline decoration-slate-300 underline-offset-2 hover:text-slate-600" on:click={showFailure}>Preview unavailable state</button></div>
                        {:else if status === "loading"}<div class="flex min-h-[360px] flex-col items-center justify-center text-center" aria-live="polite"><span class="h-7 w-7 animate-spin rounded-full border-2 border-blue-100 border-t-blue-600"></span><h3 class="mt-4 text-sm font-semibold text-slate-800">Preparing sample result</h3><p class="mt-1 text-xs text-slate-500">No trained model is being called.</p></div>
                        {:else if status === "failure"}<div class="flex min-h-[360px] flex-col items-center justify-center px-4 text-center"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-rose-50 text-rose-600"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M12 3 3.5 19h17L12 3Z" /><path d="M12 9v4M12 16h.01" /></svg></div><h3 class="mt-3 text-sm font-semibold text-slate-800">Prediction unavailable</h3><p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">This is a preview failure state. Backend errors and unavailable models need final integration behavior.</p></div>
                        {:else if status === "insufficient_data"}<div class="flex min-h-[360px] flex-col items-center justify-center px-4 text-center"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-amber-50 text-amber-600">!</div><h3 class="mt-3 text-sm font-semibold text-slate-800">Insufficient preview history</h3><p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">The sample does not establish enough history for this workflow. Minimum-history rules require team confirmation.</p></div>
                        {:else}<div class="mt-6 space-y-5">
                            {#if workflow === "demand"}<div class="rounded-md bg-slate-50 p-4"><p class="text-xs text-slate-500">{selectedProduct?.name}</p><p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">14.5 units</p><p class="mt-1 text-xs text-emerald-700">Illustrative demand for the next {horizon} days</p></div><div class="grid grid-cols-2 gap-3 text-xs"><div class="rounded-md border border-slate-100 p-3"><p class="text-slate-400">Lower range</p><strong class="mt-1 block text-slate-800">10.0 units</strong></div><div class="rounded-md border border-slate-100 p-3"><p class="text-slate-400">Upper range</p><strong class="mt-1 block text-slate-800">19.0 units</strong></div></div>
                            {:else if workflow === "sales"}<div class="rounded-md bg-slate-50 p-4"><p class="text-xs text-slate-500">Predicted sales total</p><p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">₱58,400</p><p class="mt-1 text-xs text-emerald-700">Illustrative total for the next {horizon} days</p></div><div class="relative h-36 border-b border-l border-slate-200 px-2"><svg viewBox="0 0 480 120" preserveAspectRatio="none" class="h-full w-full" aria-label="Sample sales forecast trend" role="img"><polyline points="0,100 80,84 160,92 240,62 320,68 400,32 480,42" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg></div>
                            {:else if workflow === "slow"}<div class="space-y-3">{#each slowProducts as item}<div class="flex items-center gap-3 border-b border-slate-100 pb-3 last:border-0"><span class="flex h-8 w-8 items-center justify-center rounded-md bg-amber-50 text-xs font-semibold text-amber-700">!</span><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-800">{item.name}</p><p class="mt-1 text-[10px] text-slate-400">{item.sku} · {item.predicted}</p></div><span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">{item.risk}</span></div>{/each}</div>
                            {:else}<div class="space-y-3">{#each bestProducts as item, index}<div class="flex items-center gap-3 border-b border-slate-100 pb-3 last:border-0"><span class="text-xs font-semibold text-slate-400">0{index + 1}</span><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-800">{item.name}</p><p class="mt-1 text-[10px] text-slate-400">{item.sku} · {item.predicted}</p></div><strong class="text-xs text-slate-800">{item.value}</strong></div>{/each}</div>{/if}
                            <div class="border-t border-slate-100 pt-4 text-[11px] text-slate-500"><p>Generated: <strong class="text-slate-700">{result.generatedAt}</strong></p><p class="mt-1">Method: <strong class="text-slate-700">{result.model}</strong></p><p class="mt-1">Sample output only; no model inference occurred.</p></div>
                        </div>{/if}
                    </article>
                </section>
                <p class="mt-6 text-[11px] text-slate-400">Prediction scope, minimum history, model availability, confidence intervals and product-ranking definitions are proposed UI concepts pending backend and team confirmation.</p>
            </div>
        </main>
    </div>
</div>
