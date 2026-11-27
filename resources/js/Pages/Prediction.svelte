<script>
    import axios from "axios";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Button from "../lib/components/ui/Button.svelte";

    export let products = [];
    export let categories = [];
    export let availableModels = [];
    export let canSeeMetrics = false;

    const workflows = [
        {
            id: "demand",
            label: "Product Demand",
            description: "Estimate units needed for a selected part.",
            icon: "box",
        },
        {
            id: "sales",
            label: "Sales Prediction",
            description: "Forecast future sales revenue.",
            icon: "sales",
        },
        {
            id: "slow",
            label: "Slow-Moving Products",
            description: "Identify parts at risk of slow movement.",
            icon: "warning",
        },
        {
            id: "best",
            label: "Best-Selling Products",
            description: "Rank likely top-performing parts.",
            icon: "star",
        },
    ];
    const models = [
        {
            value: "simple_linear_regression",
            label: "Simple Linear Regression",
            helper: "A lightweight trend-based estimate.",
        },
        {
            value: "random_forest_regression",
            label: "Random Forest Regression",
            helper: "A tree-based estimate for richer historical patterns.",
        },
        {
            value: "both",
            label: "Both Models (Combined)",
            helper: "Runs Simple Linear Regression and Random Forest Regression together and averages their forecasts.",
        },
    ];
    const riskStyles = {
        high: "bg-rose-50 text-rose-700",
        medium: "bg-amber-50 text-amber-700",
        low: "bg-slate-100 text-slate-600",
    };

    let workflow = "demand";
    let productId = "";
    let modelName = "random_forest_regression";
    let horizon = "30";
    let historicalPeriod = "6";
    let category = "all";
    let errors = {};
    let status = "initial";
    let errorMessage = "";
    let result = null;
    let mobileMenuOpen = false;

    $: selectedProduct = products.find(
        (product) => String(product.id) === String(productId),
    );
    $: selectedModel = models.find((model) => model.value === modelName);
    $: selectedWorkflow = workflows.find((item) => item.id === workflow);
    $: task = workflow === "sales" ? "sales" : "demand";
    $: modelReady = (
        modelName === "both"
            ? ["simple_linear_regression", "random_forest_regression"]
            : [modelName]
    ).every((algorithm) =>
        availableModels.some(
            (model) => model.task === task && model.algorithm === algorithm,
        ),
    );

    // Every run gets a number; a response that arrives after a newer run (or a workflow change) is ignored.
    let requestId = 0;

    function selectWorkflow(nextWorkflow) {
        if (status === "loading") return;
        requestId += 1;
        workflow = nextWorkflow;
        errors = {};
        result = null;
        status = "initial";
        if (nextWorkflow === "best") horizon = "30";
    }

    function validate() {
        const nextErrors = {};
        if (workflow === "demand" && !productId)
            nextErrors.product = "Select a product to forecast.";
        if (
            (workflow === "demand" || workflow === "sales") &&
            (!horizon ||
                !Number.isInteger(Number(horizon)) ||
                Number(horizon) < 1 ||
                Number(horizon) > 365)
        ) {
            nextErrors.horizon = "Enter a whole number from 1 to 365 days.";
        }
        errors = nextErrors;
        return Object.keys(nextErrors).length === 0;
    }

    function buildPayload() {
        const payload = { workflow, model: modelName };
        if (workflow === "demand")
            Object.assign(payload, {
                product_id: Number(productId),
                horizon_days: Number(horizon),
            });
        if (workflow === "sales")
            Object.assign(payload, {
                horizon_days: Number(horizon),
                history_months: Number(historicalPeriod),
            });
        if (workflow === "slow")
            Object.assign(payload, {
                history_months: Number(historicalPeriod),
                category_id: category === "all" ? null : Number(category),
            });
        if (workflow === "best")
            Object.assign(payload, {
                period_days: Number(horizon),
                category_id: category === "all" ? null : Number(category),
            });
        return payload;
    }

    async function runPrediction() {
        if (status === "loading") return;
        result = null;
        errorMessage = "";
        if (!validate()) {
            status = "validation_error";
            return;
        }
        status = "loading";
        const current = ++requestId;
        const runWorkflow = workflow;
        try {
            const { data } = await axios.post(
                "/prediction/run",
                buildPayload(),
                { headers: { Accept: "application/json" } },
            );
            if (current !== requestId || runWorkflow !== workflow) return;
            if (!data || typeof data !== "object" || !data.result || data.workflow !== runWorkflow) {
                throw new Error("Unexpected response");
            }
            result = data;
            status = "result";
        } catch (error) {
            if (current !== requestId) return;
            const response = error.response;
            if (response?.status === 419 || response?.status === 401) {
                window.location.href = "/login";
                return;
            }
            if (response?.status === 422 && response.data?.errors) {
                const serverErrors = response.data.errors;
                errors = {
                    product: serverErrors.product_id?.[0],
                    horizon:
                        serverErrors.horizon_days?.[0] ??
                        serverErrors.period_days?.[0],
                };
                status = "validation_error";
            } else {
                errorMessage =
                    response?.data?.message ??
                    "The prediction could not be completed. Please try again.";
                status = "failure";
            }
        }
    }

    function reset() {
        requestId += 1;
        productId = "";
        modelName = "random_forest_regression";
        horizon = "30";
        historicalPeriod = "6";
        category = "all";
        errors = {};
        result = null;
        errorMessage = "";
        status = "initial";
    }

    function peso(value) {
        return `₱${Number(value).toLocaleString("en-PH", { maximumFractionDigits: 0 })}`;
    }

    function dateLabel(value) {
        return value
            ? new Date(value).toLocaleDateString("en-PH", {
                  dateStyle: "medium",
              })
            : "";
    }

    // Bars: recent history (grey) followed by the forecast (blue).
    function barSeries(history, forecast) {
        const all = [
            ...history.map((item) => ({ ...item, kind: "history" })),
            ...forecast.map((item) => ({ ...item, kind: "forecast" })),
        ];
        const max = Math.max(1, ...all.map((item) => item.value));
        const width = 480 / Math.max(all.length, 1);
        return all.map((item, index) => {
            const height = (item.value / max) * 104;
            return {
                x: index * width + width * 0.12,
                y: 112 - height,
                w: width * 0.76,
                h: Math.max(height, 1),
                kind: item.kind,
                label: item.label,
                value: item.value,
            };
        });
    }

    $: chart =
        result && (workflow === "demand" || workflow === "sales")
            ? barSeries(
                  workflow === "demand"
                      ? result.result.recent_weekly
                      : result.result.history_weekly,
                  workflow === "demand"
                      ? result.result.forecast_weekly
                      : result.result.forecast_weekly,
              )
            : [];
    $: metrics = result?.model?.metrics ?? null;
    $: labelStep = Math.max(1, Math.ceil(chart.length / 14));
</script>

<svelte:head><title>Prediction | One Prompt Away</title></svelte:head>

<div
    class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen"
>
    <AppSidebar activeItem="Prediction" bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Prediction" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="w-full px-4 py-6 sm:px-8 sm:py-7">
                <div
                    class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">
                            Workspace / Prediction
                        </p>
                        <h1
                            class="text-2xl font-semibold tracking-tight text-slate-950"
                        >
                            Prediction Workspace
                        </h1>
                        <p class="mt-1 max-w-3xl text-sm text-slate-500">
                            Run forecasts for demand, sales and product
                            performance using saved regression models.
                        </p>
                    </div>
                </div>
                {#if !modelReady}
                    <div
                        class="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3.5 text-xs text-amber-800"
                        role="status"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="mt-0.5 h-4 w-4 shrink-0"
                            aria-hidden="true"
                            ><path d="M12 3 3.5 19h17L12 3Z" /><path
                                d="M12 9v4M12 16h.01"
                            /></svg
                        >
                        <p>
                            {modelName === "both"
                                ? "Both trained models are needed, but at least one is missing"
                                : `No trained ${selectedModel.label} model is available`}
                            for {task === "sales" ? "sales" : "Product Demand"} predictions
                            yet. Choose the other model or ask an instructor to train
                            one.
                        </p>
                    </div>
                {/if}

                <section
                    class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Prediction Workflows"
                >
                    {#each workflows as item}
                        <button
                            type="button"
                            class={`rounded-lg border p-4 text-left transition-colors ${workflow === item.id ? "border-blue-300 bg-blue-50/60" : "border-slate-200 bg-white hover:border-slate-300"}`}
                            on:click={() => selectWorkflow(item.id)}
                        >
                            <span
                                class={`flex h-8 w-8 items-center justify-center rounded-md ${workflow === item.id ? "bg-blue-100 text-blue-700" : "bg-slate-100 text-slate-500"}`}
                            >
                                {#if item.icon === "sales"}<svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                        ><path d="M4 19V5m0 14h16" /><path
                                            d="m7 15 3-4 3 2 5-7"
                                        /></svg
                                    >{:else if item.icon === "warning"}<svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                        ><path d="M12 3 3.5 19h17L12 3Z" /><path
                                            d="M12 9v4M12 16h.01"
                                        /></svg
                                    >{:else if item.icon === "star"}<svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                        ><path
                                            d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z"
                                        /></svg
                                    >{:else}<svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                        ><path
                                            d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"
                                        /><path
                                            d="m4 7.5 8 4.5 8-4.5M12 12v9"
                                        /></svg
                                    >{/if}
                            </span>
                            <p
                                class="mt-3 text-xs font-semibold text-slate-900"
                            >
                                {item.label}
                            </p>
                            <p
                                class="mt-1 text-[11px] leading-4 text-slate-500"
                            >
                                {item.description}
                            </p>
                        </button>
                    {/each}
                </section>

                <section
                    class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
                >
                    <article
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="mb-6">
                            <h2 class="text-sm font-semibold text-slate-900">
                                {selectedWorkflow.label} inputs
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                {selectedWorkflow.description} Choose the inputs
                                and run the prediction.
                            </p>
                        </div>
                        {#if status === "validation_error"}<div
                                class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-700"
                                role="alert"
                            >
                                Review the highlighted fields before running a
                                prediction.
                            </div>{/if}
                        <form
                            class="space-y-5"
                            on:submit|preventDefault={runPrediction}
                        >
                            {#if workflow === "demand"}
                                <div>
                                    <label
                                        for="prediction-product"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Product <span class="text-rose-600"
                                            >*</span
                                        ></label
                                    ><select
                                        id="prediction-product"
                                        bind:value={productId}
                                        class:border-rose-300={errors.product}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        ><option value=""
                                            >Select A Product</option
                                        >{#each products as product}<option
                                                value={product.id}
                                                >{product.name} · {product.sku} ·
                                                {product.stock} in stock</option
                                            >{/each}</select
                                    >{#if errors.product}<p
                                            class="mt-1 text-[11px] text-rose-600"
                                        >
                                            {errors.product}
                                        </p>{/if}
                                </div>
                                <div>
                                    <label
                                        for="prediction-model"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Regression Model <span
                                            class="text-rose-600">*</span
                                        ></label
                                    ><select
                                        id="prediction-model"
                                        bind:value={modelName}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >{#each models as model}<option
                                                value={model.value}
                                                >{model.label}</option
                                            >{/each}</select
                                    >
                                    <p
                                        class="mt-1.5 text-[11px] text-slate-400"
                                    >
                                        {selectedModel.helper}
                                    </p>
                                </div>
                            {:else if workflow === "sales"}
                                <div>
                                    <label
                                        for="prediction-history"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Historical Sales Range <span
                                            class="text-rose-600">*</span
                                        ></label
                                    ><select
                                        id="prediction-history"
                                        bind:value={historicalPeriod}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        ><option value="12"
                                            >Last 12 Months</option
                                        ><option value="6">Last 6 Months</option
                                        ><option value="3">Last 3 Months</option
                                        ><option value="2">Last 2 Months</option
                                        ></select
                                    >{#if errors.period}<p
                                            class="mt-1 text-[11px] text-rose-600"
                                        >
                                            {errors.period}
                                        </p>{/if}
                                </div>
                                <div>
                                    <label
                                        for="prediction-sales-model"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Model</label
                                    ><select
                                        id="prediction-sales-model"
                                        bind:value={modelName}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >{#each models as model}<option
                                                value={model.value}
                                                >{model.label}</option
                                            >{/each}</select
                                    >
                                </div>
                            {:else if workflow === "slow"}
                                <div>
                                    <label
                                        for="prediction-slow-scope"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Product Scope</label
                                    ><select
                                        id="prediction-slow-scope"
                                        bind:value={category}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        ><option value="all"
                                            >All Active Products</option
                                        >{#each categories as item}<option
                                                value={item.id}
                                                >{item.name} category</option
                                            >{/each}</select
                                    >
                                </div>
                                <div>
                                    <label
                                        for="prediction-slow-period"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Historical Period</label
                                    ><select
                                        id="prediction-slow-period"
                                        bind:value={historicalPeriod}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        ><option value="12"
                                            >Last 12 Months</option
                                        ><option value="6">Last 6 Months</option
                                        ><option value="3">Last 3 Months</option
                                        ></select
                                    >
                                </div>
                            {:else}
                                <div>
                                    <label
                                        for="prediction-best-category"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Product Category</label
                                    ><select
                                        id="prediction-best-category"
                                        bind:value={category}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        ><option value="all"
                                            >All Categories</option
                                        >{#each categories as item}<option
                                                value={item.id}
                                                >{item.name}</option
                                            >{/each}</select
                                    >
                                </div>
                                <div>
                                    <label
                                        for="prediction-best-period"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Forecast Period</label
                                    ><select
                                        id="prediction-best-period"
                                        bind:value={horizon}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        ><option value="30">Next 30 Days</option
                                        ><option value="60">Next 60 Days</option
                                        ><option value="90">Next 90 Days</option
                                        ></select
                                    >
                                </div>
                            {/if}
                            {#if workflow === "slow" || workflow === "best"}<div
                                >
                                    <label
                                        for="prediction-rank-model"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Regression Model</label
                                    ><select
                                        id="prediction-rank-model"
                                        bind:value={modelName}
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >{#each models as model}<option
                                                value={model.value}
                                                >{model.label}</option
                                            >{/each}</select
                                    >
                                </div>{/if}
                            {#if workflow === "demand" || workflow === "sales"}<div
                                >
                                    <label
                                        for="prediction-horizon"
                                        class="mb-1.5 block text-xs font-medium text-slate-700"
                                        >Forecast Horizon <span
                                            class="text-rose-600">*</span
                                        ></label
                                    >
                                    <div class="relative">
                                        <input
                                            id="prediction-horizon"
                                            bind:value={horizon}
                                            type="number"
                                            min="1"
                                            max="365"
                                            step="1"
                                            class:border-rose-300={errors.horizon}
                                            class="h-10 w-full rounded-md border border-slate-200 px-3 pr-16 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        /><span
                                            class="pointer-events-none absolute right-3 top-2.5 text-xs text-slate-400"
                                            >Days</span
                                        >
                                    </div>
                                    {#if errors.horizon}<p
                                            class="mt-1 text-[11px] text-rose-600"
                                        >
                                            {errors.horizon}
                                        </p>{/if}
                                </div>{/if}
                            <div
                                class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                    on:click={reset}>Reset</button
                                ><Button
                                    type="submit"
                                    disabled={status === "loading"}
                                    className="text-xs"
                                    >{#if status === "loading"}<span
                                            class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                        ></span>Running prediction...{:else}Run
                                        prediction{/if}</Button
                                >
                            </div>
                        </form>
                    </article>

                    <article
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    Prediction Result
                                </h2>
                                <p class="mt-1 text-xs text-slate-500">
                                    {selectedWorkflow.label} output.
                                </p>
                            </div>
                            {#if status === "result"}<span
                                    class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700"
                                    >Model Prediction</span
                                >{/if}
                        </div>
                        {#if status === "initial" || status === "validation_error"}
                            <div
                                class="flex min-h-90 flex-col items-center justify-center px-4 text-center"
                            >
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-5 w-5"
                                        aria-hidden="true"
                                        ><path d="M4 19V5m0 14h16" /><path
                                            d="m7 15 3-4 3 2 5-7"
                                        /></svg
                                    >
                                </div>
                                <h3
                                    class="mt-3 text-sm font-semibold text-slate-800"
                                >
                                    No Prediction Yet
                                </h3>
                                <p
                                    class="mt-1 max-w-xs text-xs leading-5 text-slate-500"
                                >
                                    Configure this workflow and run the
                                    prediction to see the output.
                                </p>
                            </div>
                        {:else if status === "loading"}
                            <div
                                class="flex min-h-90 flex-col items-center justify-center text-center"
                                aria-live="polite"
                            >
                                <span
                                    class="h-7 w-7 animate-spin rounded-full border-2 border-blue-100 border-t-blue-600"
                                ></span>
                                <h3
                                    class="mt-4 text-sm font-semibold text-slate-800"
                                >
                                    Running The Saved Model
                                </h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    This usually takes a few seconds.
                                </p>
                            </div>
                        {:else if status === "failure"}
                            <div
                                class="flex min-h-90 flex-col items-center justify-center px-4 text-center"
                                role="alert"
                            >
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-full bg-rose-50 text-rose-600"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-5 w-5"
                                        aria-hidden="true"
                                        ><path d="M12 3 3.5 19h17L12 3Z" /><path
                                            d="M12 9v4M12 16h.01"
                                        /></svg
                                    >
                                </div>
                                <h3
                                    class="mt-3 text-sm font-semibold text-slate-800"
                                >
                                    Prediction Unavailable
                                </h3>
                                <p
                                    class="mt-1 max-w-sm text-xs leading-5 text-slate-500"
                                >
                                    {errorMessage}
                                </p>
                            </div>
                        {:else if result}
                            <div class="mt-6 space-y-5">
                                {#if workflow === "demand"}
                                    <div class="rounded-md bg-slate-50 p-4">
                                        <p class="text-xs text-slate-500">
                                            {result.result.product.name} · {result
                                                .result.product.sku}
                                        </p>
                                        <p
                                            class="mt-2 text-3xl font-semibold tracking-tight text-slate-950"
                                        >
                                            {result.result.predicted_units} units
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-emerald-700"
                                        >
                                            Predicted demand for the next {horizon}
                                            days
                                        </p>
                                    </div>
                                    <div
                                        class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4"
                                    >
                                        <div
                                            class="rounded-md border border-slate-100 p-3"
                                        >
                                            <p class="text-slate-400">
                                                Lower Range
                                            </p>
                                            <strong
                                                class="mt-1 block text-slate-800"
                                                >{result.result.lower} units</strong
                                            >
                                        </div>
                                        <div
                                            class="rounded-md border border-slate-100 p-3"
                                        >
                                            <p class="text-slate-400">
                                                Upper Range
                                            </p>
                                            <strong
                                                class="mt-1 block text-slate-800"
                                                >{result.result.upper} units</strong
                                            >
                                        </div>
                                        <div
                                            class="rounded-md border border-slate-100 p-3"
                                        >
                                            <p class="text-slate-400">
                                                Current Stock
                                            </p>
                                            <strong
                                                class="mt-1 block text-slate-800"
                                                >{result.result.current_stock} units</strong
                                            >
                                        </div>
                                        <div
                                            class="rounded-md border border-slate-100 p-3"
                                        >
                                            <p class="text-slate-400">
                                                Suggested Restock
                                            </p>
                                            <strong
                                                class:text-rose-700={result
                                                    .result.suggested_restock >
                                                    0}
                                                class="mt-1 block text-slate-800"
                                                >{result.result
                                                    .suggested_restock} units</strong
                                            >
                                        </div>
                                    </div>
                                {:else if workflow === "sales"}
                                    <div class="rounded-md bg-slate-50 p-4">
                                        <p class="text-xs text-slate-500">
                                            Predicted Sales Total
                                        </p>
                                        <p
                                            class="mt-2 text-3xl font-semibold tracking-tight text-slate-950"
                                        >
                                            {peso(
                                                result.result.predicted_total,
                                            )}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-emerald-700"
                                        >
                                            For The Next {horizon} days ({dateLabel(
                                                result.result.forecast_start,
                                            )} – {dateLabel(
                                                result.result.forecast_end,
                                            )})
                                        </p>
                                    </div>
                                    <div
                                        class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4"
                                    >
                                        <div
                                            class="rounded-md border border-slate-100 p-3"
                                        >
                                            <p class="text-slate-400">
                                                Lower Range
                                            </p>
                                            <strong
                                                class="mt-1 block text-slate-800"
                                                >{peso(
                                                    result.result.lower,
                                                )}</strong
                                            >
                                        </div>
                                        <div
                                            class="rounded-md border border-slate-100 p-3"
                                        >
                                            <p class="text-slate-400">
                                                Upper Range
                                            </p>
                                            <strong
                                                class="mt-1 block text-slate-800"
                                                >{peso(
                                                    result.result.upper,
                                                )}</strong
                                            >
                                        </div>
                                        <div
                                            class="rounded-md border border-slate-100 p-3"
                                        >
                                            <p class="text-slate-400">
                                                Daily Average
                                            </p>
                                            <strong
                                                class="mt-1 block text-slate-800"
                                                >{peso(
                                                    result.result.daily_average,
                                                )}</strong
                                            >
                                        </div>
                                        <div
                                            class="rounded-md border border-slate-100 p-3"
                                        >
                                            <p class="text-slate-400">
                                                Vs Previous Period
                                            </p>
                                            <strong
                                                class:text-rose-600={result
                                                    .result.change_percent < 0}
                                                class:text-emerald-700={result
                                                    .result.change_percent >= 0}
                                                class="mt-1 block"
                                                >{result.result
                                                    .change_percent === null
                                                    ? "n/a"
                                                    : `${result.result.change_percent > 0 ? "+" : ""}${result.result.change_percent}%`}</strong
                                            >
                                        </div>
                                    </div>
                                {/if}
                                {#if result.components?.length}
                                    <div
                                        class="rounded-md border border-blue-100 bg-blue-50/60 p-4 text-xs"
                                    >
                                        <p class="font-semibold text-blue-900">
                                            Combined Prediction
                                        </p>
                                        <p class="mt-1 text-blue-800">
                                            The headline numbers are the average
                                            of both models, so each model
                                            corrects the other’s weaknesses.
                                            Here is what each one predicts on
                                            its own:
                                        </p>
                                        <div
                                            class="mt-3 grid gap-2 sm:grid-cols-2"
                                        >
                                            {#each result.components as part}
                                                <div
                                                    class="rounded-md bg-white p-3"
                                                >
                                                    <p class="text-slate-500">
                                                        {part.algorithm_label}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-sm font-semibold text-slate-900"
                                                    >
                                                        {workflow === "demand"
                                                            ? `${part.result.predicted_units} Units`
                                                            : workflow ===
                                                                "sales"
                                                              ? peso(
                                                                    part.result
                                                                        .predicted_total,
                                                                )
                                                              : workflow ===
                                                                  "slow"
                                                                ? `${part.result.flagged_total} Products Flagged`
                                                                : (part.result
                                                                      .items[0]
                                                                      ?.name ??
                                                                  "No Result")}
                                                    </p>
                                                </div>
                                            {/each}
                                        </div>
                                    </div>
                                {/if}
                                {#if workflow === "demand" || workflow === "sales"}
                                    <div>
                                        <svg
                                            viewBox="0 0 480 120"
                                            preserveAspectRatio="none"
                                            class="mb-10 h-36 w-full overflow-visible border-b border-slate-200"
                                            role="img"
                                            aria-label={workflow === "demand"
                                                ? "Weekly demand: recent history and forecast"
                                                : "Weekly Sales: History And Forecast"}
                                        >
                                            {#each chart as bar, index}{#if index % labelStep === 0}<text
                                                        x={bar.x + bar.w / 2}
                                                        y="130"
                                                        font-size="7"
                                                        fill="#64748b"
                                                        text-anchor="end"
                                                        transform={`rotate(-40 ${bar.x + bar.w / 2} 130)`}
                                                        >{bar.label}</text
                                                    >{/if}<rect
                                                    x={bar.x}
                                                    y={bar.y}
                                                    width={bar.w}
                                                    height={bar.h}
                                                    rx="1.5"
                                                    fill={bar.kind ===
                                                    "forecast"
                                                        ? "#2563eb"
                                                        : "#cbd5e1"}
                                                    ><title
                                                        >{bar.label}: {Number(
                                                            bar.value,
                                                        ).toLocaleString(
                                                            "en-PH",
                                                        )}</title
                                                    ></rect
                                                >{/each}
                                        </svg>
                                        <div
                                            class="mt-2 flex items-center gap-4 text-[11px] text-slate-500"
                                        >
                                            <span
                                                class="flex items-center gap-1.5"
                                                ><i
                                                    class="h-2 w-2 rounded-sm bg-slate-300"
                                                ></i>Recent Weeks (Actual)</span
                                            ><span
                                                class="flex items-center gap-1.5"
                                                ><i
                                                    class="h-2 w-2 rounded-sm bg-blue-600"
                                                ></i>Forecast Weeks</span
                                            >
                                        </div>
                                    </div>
                                {:else if workflow === "slow"}
                                    {#if result.result.items.length === 0}
                                        <div
                                            class="rounded-md bg-emerald-50 p-4 text-xs text-emerald-800"
                                        >
                                            No slow-moving products were
                                            detected in this scope. All {result
                                                .result.products_evaluated} evaluated
                                            products are predicted to sell at a healthy
                                            pace.
                                        </div>
                                    {:else}
                                        <p class="text-xs text-slate-500">
                                            {result.result.flagged_total} of {result
                                                .result.products_evaluated} products
                                            flagged. Showing the {result.result
                                                .items.length} highest-risk items.
                                        </p>
                                        <div
                                            class="max-h-80 space-y-3 overflow-y-auto pr-2"
                                        >
                                            {#each result.result.items as item}<div
                                                    class="flex items-center gap-3 border-b border-slate-100 pb-3 last:border-0"
                                                >
                                                    <span
                                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-amber-50 text-xs font-semibold text-amber-700"
                                                        >!</span
                                                    >
                                                    <div class="min-w-0 flex-1">
                                                        <p
                                                            class="truncate text-xs font-semibold text-slate-800"
                                                        >
                                                            {item.name}
                                                        </p>
                                                        <p
                                                            class="mt-1 text-[10px] text-slate-400"
                                                        >
                                                            {item.sku} · {item.stock}
                                                            in stock · {item.predicted_units_30d}
                                                            units / 30 days · about
                                                            {item.cover_days >=
                                                            999
                                                                ? "no"
                                                                : item.cover_days}
                                                            days of cover
                                                        </p>
                                                    </div>
                                                    <span
                                                        class={`rounded-full px-2 py-1 text-[10px] font-semibold capitalize ${riskStyles[item.risk]}`}
                                                        >{item.risk} risk</span
                                                    >
                                                </div>{/each}
                                        </div>
                                        <p
                                            class="text-[11px] leading-4 text-slate-400"
                                        >
                                            {result.result.rules}
                                        </p>
                                    {/if}
                                {:else}
                                    <div class="space-y-3">
                                        {#each result.result.items as item}<div
                                                class="flex items-center gap-3 border-b border-slate-100 pb-3 last:border-0"
                                            >
                                                <span
                                                    class="w-5 text-xs font-semibold text-slate-400"
                                                    >{String(
                                                        item.rank,
                                                    ).padStart(2, "0")}</span
                                                >
                                                <div class="min-w-0 flex-1">
                                                    <p
                                                        class="truncate text-xs font-semibold text-slate-800"
                                                    >
                                                        {item.name}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-[10px] text-slate-400"
                                                    >
                                                        {item.sku} · {item.share_percent}%
                                                        of predicted units
                                                    </p>
                                                </div>
                                                <div
                                                    class="shrink-0 text-right"
                                                >
                                                    <strong
                                                        class="block text-xs text-slate-800"
                                                        >{item.predicted_units}
                                                        units</strong
                                                    >
                                                    <strong
                                                        class="mt-1 block text-xs text-slate-800"
                                                        >{peso(
                                                            item.predicted_revenue,
                                                        )}</strong
                                                    >
                                                </div>
                                            </div>{/each}
                                    </div>
                                {/if}
                                <div
                                    class="border-t border-slate-100 pt-4 text-[11px] text-slate-500"
                                >
                                    <p>
                                        Generated: <strong
                                            class="text-slate-700"
                                            >{new Date(
                                                result.generated_at,
                                            ).toLocaleString("en-PH", {
                                                dateStyle: "medium",
                                                timeStyle: "short",
                                            })}</strong
                                        >
                                    </p>
                                    <p class="mt-1">
                                        Model: <strong class="text-slate-700"
                                            >{result.model
                                                .algorithm_label}</strong
                                        >{#if result.model.version}
                                            · v{result.model.version}{/if} · trained
                                        on data through {dateLabel(
                                            result.model.trained_through,
                                        )}
                                    </p>
                                    {#if canSeeMetrics && result.model.components}
                                        {#each result.model.components as part}<p
                                                class="mt-1"
                                            >
                                                {part.algorithm_label} (Hold-Out):
                                                MAE
                                                <strong class="text-slate-700"
                                                    >{part.metrics.mae}</strong
                                                >
                                                · RMSE
                                                <strong class="text-slate-700"
                                                    >{part.metrics.rmse}</strong
                                                >
                                                · MAPE
                                                <strong class="text-slate-700"
                                                    >{part.metrics.mape === null
                                                        ? "n/a"
                                                        : `${part.metrics.mape}%`}</strong
                                                >
                                                · WAPE
                                                <strong class="text-slate-700"
                                                    >{part.metrics.wape === null
                                                        ? "n/a"
                                                        : `${part.metrics.wape}%`}</strong
                                                >
                                                · R²
                                                <strong class="text-slate-700"
                                                    >{part.metrics.r2 ??
                                                        "n/a"}</strong
                                                >
                                            </p>{/each}
                                    {:else if canSeeMetrics && metrics}
                                        <p class="mt-1">
                                            Evaluation (Hold-Out): MAE <strong
                                                class="text-slate-700"
                                                >{metrics.mae}</strong
                                            >
                                            · RMSE
                                            <strong class="text-slate-700"
                                                >{metrics.rmse}</strong
                                            >
                                            · MAPE
                                            <strong class="text-slate-700"
                                                >{metrics.mape === null
                                                    ? "n/a"
                                                    : `${metrics.mape}%`}</strong
                                            >
                                            · WAPE
                                            <strong class="text-slate-700"
                                                >{metrics.wape === null
                                                    ? "n/a"
                                                    : `${metrics.wape}%`}</strong
                                            >
                                            · R²
                                            <strong class="text-slate-700"
                                                >{metrics.r2 ?? "n/a"}</strong
                                            >
                                        </p>
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    </article>
                </section>
                <p class="mt-6 text-[11px] text-slate-400">
                    Predictions come from saved models trained on historical
                    store data. Ranges are approximate 80% intervals based on
                    hold-out errors; results are estimates, not guarantees.
                </p>
            </div>
        </main>
    </div>
</div>
