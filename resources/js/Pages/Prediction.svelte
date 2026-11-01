<script>
    import { onMount } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Button from "../lib/components/ui/Button.svelte";
    import Input from "../lib/components/ui/Input.svelte";
    import Label from "../lib/components/ui/Label.svelte";
    import NativeSelect from "../lib/components/ui/Select.svelte";

    export let auth = { user: null };
    let data = { datasets: [], algorithms: [] };
    let models = [];
    let businessProducts = [];
    let selectedProductId = "";
    let businessMessage = "";
    let datasetId = "product_demand";
    let algorithm = "simple_linear_regression";
    let features = {
        day_index: 600, stock_quantity: 30, unit_price: 1250, product_index: 0,
        promotion: 0, day_of_week: 2, recent_demand: 10, discount_rate: 0.05,
        customer_factor: 1,
    };
    let state = "loading";
    let message = "";
    let result = null;
    let mobileMenuOpen = false;
    $: selectedDataset = data.datasets.find((item) => item.id === datasetId);
    $: selectedModel = models.find((item) => item.dataset_id === datasetId && item.algorithm === algorithm);

    async function load() {
        state = "loading";
        try {
            const [datasetResponse, modelResponse] = await Promise.all([
                fetch("/analysis/datasets"),
                fetch("/analysis/models"),
            ]);
            if (!datasetResponse.ok || !modelResponse.ok) {
                throw new Error("Analysis services are unavailable.");
            }
            data = await datasetResponse.json();
            models = (await modelResponse.json()).models || [];
            const businessResponse = await fetch("/analysis/business-features?dataset_id=product_demand");
            if (businessResponse.ok) {
                businessProducts = (await businessResponse.json()).products || [];
            }
            state = "ready";
        } catch (error) {
            state = "error";
            message = error.message || "Analysis services are unavailable.";
        }
    }

    async function loadBusinessFeatures() {
        if (!selectedProductId || datasetId !== "product_demand") {
            businessMessage = "Select a product for a database-backed demand prediction.";
            return;
        }
        const response = await fetch(`/analysis/business-features?dataset_id=product_demand&product_id=${selectedProductId}`);
        const payload = await response.json();
        if (!response.ok || !payload.available || !payload.features) {
            businessMessage = payload.message || "Current product data is unavailable.";
            return;
        }
        features = { ...features, ...payload.features };
        businessMessage = "Current inventory and completed sales data loaded.";
    }

    async function train() {
        state = "training";
        message = "";
        result = null;
        const response = await fetch("/analysis/train", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-XSRF-TOKEN": csrfToken(), Accept: "application/json" },
            body: JSON.stringify({ dataset_id: datasetId, algorithm }),
        });
        const payload = await response.json();
        if (!response.ok) {
            message = payload.message || "Training failed.";
            state = "error";
            return;
        }
        models = [payload.model, ...models];
        result = payload.model;
        state = "ready";
    }

    async function predict() {
        const model = selectedModel;
        if (!model) {
            message = "Train a model for this dataset and algorithm first.";
            return;
        }
        const predictionFeatures = Object.fromEntries(selectedDataset.features.map((feature) => [feature, features[feature]]));
        const response = await fetch("/analysis/predict", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-XSRF-TOKEN": csrfToken(), Accept: "application/json" },
            body: JSON.stringify({ model_id: model.id, features: predictionFeatures }),
        });
        const payload = await response.json();
        if (!response.ok) {
            message = payload.message || "Prediction failed.";
            state = "error";
            return;
        }
        result = payload.result;
        message = "";
    }

    function csrfToken() {
        return decodeURIComponent(document.cookie.split("; ").find((cookie) => cookie.startsWith("XSRF-TOKEN="))?.split("=")[1] || "");
    }

    onMount(load);
</script>

<svelte:head><title>Prediction | One Prompt Away</title></svelte:head>
<div class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen">
    <AppSidebar activeItem="Prediction" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Prediction" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1180px] px-4 py-6 sm:px-8 sm:py-7">
                <p class="mb-2 text-xs font-medium text-slate-500">Workspace / Prediction</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Prediction workspace</h1>
                <p class="mt-1 text-sm text-slate-500">Train reproducible regression models and run predictions from validated datasets.</p>
                <div class="mt-6 rounded-lg border border-blue-100 bg-blue-50/70 px-4 py-3 text-xs text-blue-800">Datasets are deterministic synthetic development data and are not actual store transactions.</div>
                {#if state === "loading"}<p class="mt-8 text-sm text-slate-500">Loading analysis data...</p>{:else}
                    <section class="mt-6 grid gap-6 lg:grid-cols-[1fr_1fr]">
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-sm font-semibold">Model training</h2>
                            <Label className="mt-5 block text-xs">Dataset
                                <NativeSelect bind:value={datasetId} className="mt-2 w-full"><option value="product_demand">Product Demand</option><option value="sales_history">Sales History</option></NativeSelect>
                            </Label>
                            <Label className="mt-4 block text-xs">Algorithm
                                <NativeSelect bind:value={algorithm} className="mt-2 w-full">{#each data.algorithms as item}<option value={item.id}>{item.name}</option>{/each}</NativeSelect>
                            </Label>
                            <Button className="mt-5 text-xs" onclick={train} disabled={state === "training"}>{state === "training" ? "Training..." : "Train model"}</Button>
                            {#if result?.metrics}<div class="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-600"><div class="rounded border border-slate-200 p-2">MAE <strong class="block text-slate-900">{Number(result.metrics.mae).toFixed(3)}</strong></div><div class="rounded border border-slate-200 p-2">MSE <strong class="block text-slate-900">{Number(result.metrics.mse).toFixed(3)}</strong></div><div class="rounded border border-slate-200 p-2">RMSE <strong class="block text-slate-900">{Number(result.metrics.rmse).toFixed(3)}</strong></div><div class="rounded border border-slate-200 p-2">R² <strong class="block text-slate-900">{Number(result.metrics.r2).toFixed(3)}</strong></div></div>{/if}
                            {#if message}<p class="mt-3 text-xs text-rose-600">{message}</p>{/if}
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-sm font-semibold">Run prediction</h2>
                            {#if datasetId === "product_demand"}
                                <Label className="mt-4 block text-xs">Product from current inventory
                                    <NativeSelect bind:value={selectedProductId} className="mt-2 w-full"><option value="">Select a product</option>{#each businessProducts as product}<option value={String(product.id)}>{product.name} · {product.sku}</option>{/each}</NativeSelect>
                                </Label>
                                <Button variant="outline" className="mt-4 text-xs" onclick={loadBusinessFeatures}>Load current product data</Button>
                                {#if businessMessage}<p class="mt-2 text-xs text-slate-500">{businessMessage}</p>{/if}
                            {:else}
                                <p class="mt-4 text-xs text-amber-700">Current sales records do not contain all features required by this trained dataset. Use synthetic dataset inputs only.</p>
                            {/if}
                            {#if selectedDataset}{#each selectedDataset.features as feature}<Label className="mt-4 block text-xs">{feature}<Input type="number" bind:value={features[feature]} className="mt-2" /></Label>{/each}{/if}
                            <Button variant="outline" className="mt-5 text-xs" onclick={predict}>Run prediction</Button>
                            {#if result?.prediction !== undefined}<p class="mt-4 text-sm text-slate-700">Predicted {result.target}: <strong>{Number(result.prediction).toFixed(2)}</strong> {result.target === "units_sold" ? "units" : "currency units"}</p>{/if}
                            {#if result?.warnings?.length}<div class="mt-3 space-y-1 text-xs text-amber-700">{#each result.warnings as warning}<p>{warning}</p>{/each}</div>{/if}
                        </div>
                    </section>
                    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold">Validated datasets</h2>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">{#each data.datasets as item}<div class="rounded-md border border-slate-200 p-4"><p class="text-sm font-medium">{item.name}</p><p class="mt-1 text-xs text-slate-500">{item.description}</p><p class="mt-3 text-xs text-slate-600">{item.record_count} records · {item.valid ? "Valid" : "Invalid"}</p></div>{/each}</div>
                    </section>
                    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-semibold">Saved models</h2>{#if !models.length}<p class="mt-4 text-sm text-slate-500">No trained models have been saved yet.</p>{:else}<div class="mt-4 space-y-2">{#each models as model}<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 py-2 text-xs last:border-0"><span><strong>{model.dataset_id}</strong> · {model.algorithm}</span><span class="text-slate-500">R² {Number(model.metrics.r2).toFixed(3)}</span></div>{/each}</div>{/if}</section>
                {/if}
            </div>
        </main>
    </div>
</div>
