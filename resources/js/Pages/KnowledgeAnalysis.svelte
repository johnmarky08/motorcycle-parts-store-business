<script>
    import { onDestroy } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";

    // Preview-only analysis state. Dataset files, metrics and models are not connected to a backend.
    const datasets = [
        {
            id: "sales-history",
            name: "Motorcycle Sales History",
            description: "Illustrative transaction-level sales records for trend and revenue analysis.",
            records: 2486,
            columns: 8,
            coverage: "Jan 1, 2025 – Oct 28, 2026",
            status: "validated",
            missing: 12,
            duplicates: 3,
            target: "total_amount",
            features: ["transaction_date", "product_id", "quantity", "payment_method"],
            rows: [
                { transaction_date: "2026-10-28", product_name: "Kawasaki Ninja Brake Pad", quantity: 2, unit_price: "₱1,250.00", total_amount: "₱2,500.00" },
                { transaction_date: "2026-10-27", product_name: "NGK Iridium Spark Plug", quantity: 2, unit_price: "₱520.00", total_amount: "₱1,040.00" },
                { transaction_date: "2026-10-26", product_name: "DID 428H Chain Set", quantity: 1, unit_price: "₱2,380.00", total_amount: "₱2,380.00" },
                { transaction_date: "2026-10-24", product_name: "Michelin City Pro Tire", quantity: 2, unit_price: "₱3,150.00", total_amount: "₱6,300.00" },
            ],
        },
        {
            id: "product-demand",
            name: "Product Demand Dataset",
            description: "Illustrative daily product demand records for forecasting experiments.",
            records: 1864,
            columns: 7,
            coverage: "Apr 1, 2025 – Oct 28, 2026",
            status: "warning",
            missing: 28,
            duplicates: 0,
            target: "units_sold",
            features: ["date", "product_id", "category", "stock_quantity"],
            rows: [
                { transaction_date: "2026-10-28", product_name: "Kawasaki Ninja Brake Pad", quantity: 12, unit_price: "Brake System", total_amount: "Healthy" },
                { transaction_date: "2026-10-27", product_name: "Yamaha MT-15 Air Filter", quantity: 8, unit_price: "Engine", total_amount: "Low stock" },
                { transaction_date: "2026-10-26", product_name: "NGK Iridium Spark Plug", quantity: 9, unit_price: "Electrical", total_amount: "Healthy" },
                { transaction_date: "2026-10-24", product_name: "DID 428H Chain Set", quantity: 4, unit_price: "Drivetrain", total_amount: "Healthy" },
            ],
        },
    ];
    const savedModels = [
        { id: "model-2026-10-1", name: "Product sales regression", type: "Simple Linear Regression", version: "2026.10.1", dataset: "Motorcycle Sales History", date: "Oct 27, 2026", status: "Ready", mae: "4.2", rmse: "6.8", mape: "12.4%" },
        { id: "model-2026-09-2", name: "Demand forest baseline", type: "Random Forest Regression", version: "2026.09.2", dataset: "Product Demand Dataset", date: "Sep 30, 2026", status: "Ready", mae: "3.7", rmse: "5.9", mape: "10.8%" },
    ];
    const trendPoints = "0,112 45,105 90,110 135,82 180,88 225,64 270,72 315,48 360,57 405,30 450,40 500,18";
    const metricDescriptions = {
        mae: "Average absolute difference between predicted and observed values.",
        rmse: "Error measure that gives larger mistakes more weight.",
        mape: "Average percentage error; useful when actual values are not zero.",
    };

    let datasetId = "sales-history";
    let selectedModelId = "";
    let previewSearch = "";
    let trainingStatus = "ready";
    let trainingProgress = 0;
    let trainingTimer;
    let formErrors = {};
    let uploadState = "idle";
    let uploadMessage = "";
    let successMessage = "";
    let errorMessage = "";
    let targetVariable = "total_amount";
    let featureSelection = ["transaction_date", "product_id", "quantity"];
    let split = "80/20";
    let horizon = "30";
    let modelType = "simple_linear_regression";
    let mobileMenuOpen = false;

    $: dataset = datasets.find((item) => item.id === datasetId) || datasets[0];
    $: schemaColumns = dataset.id === "sales-history"
        ? [["transaction_date", "date"], ["product_id", "integer"], ["quantity", "integer"], ["unit_price", "decimal"], ["total_amount", "decimal"]]
        : [["date", "date"], ["product_id", "integer"], ["category", "string"], ["units_sold", "integer"], ["stock_quantity", "integer"]];
    $: selectedModel = savedModels.find((model) => model.id === selectedModelId);
    $: previewRows = dataset.rows.filter((row) => {
        const query = previewSearch.trim().toLowerCase();
        return !query || Object.values(row).some((value) => String(value).toLowerCase().includes(query));
    });
    $: sampleMetrics = modelType === "random_forest_regression"
        ? { mae: "3.7", rmse: "5.9", mape: "10.8%" }
        : { mae: "4.2", rmse: "6.8", mape: "12.4%" };

    function selectDataset() {
        const nextDataset = datasets.find((item) => item.id === datasetId);
        targetVariable = nextDataset.target;
        featureSelection = nextDataset.features.slice(0, 3);
        trainingStatus = "ready";
        formErrors = {};
    }

    function toggleFeature(feature) {
        featureSelection = featureSelection.includes(feature)
            ? featureSelection.filter((item) => item !== feature)
            : [...featureSelection, feature];
    }

    function simulateUpload(state) {
        uploadState = state;
        uploadMessage = state === "invalid"
            ? "The selected preview file could not be validated. CSV schema and required columns still need confirmation."
            : "No file was uploaded. This is a preview-only replacement control.";
    }

    function validateTraining() {
        const errors = {};
        const days = Number(horizon);
        if (!targetVariable) errors.target = "Select a target variable.";
        if (!featureSelection.length) errors.features = "Select at least one input feature.";
        if (!Number.isInteger(days) || days < 1 || days > 365) errors.horizon = "Enter a whole number from 1 to 365 days.";
        formErrors = errors;
        return Object.keys(errors).length === 0;
    }

    function runTraining() {
        successMessage = "";
        errorMessage = "";
        if (!validateTraining()) {
            trainingStatus = "ready";
            return;
        }
        if (dataset.records < 1000) {
            trainingStatus = "insufficient_data";
            return;
        }
        window.clearInterval(trainingTimer);
        trainingProgress = 0;
        trainingStatus = "running";
        trainingTimer = window.setInterval(() => {
            trainingProgress += 25;
            if (trainingProgress >= 100) {
                window.clearInterval(trainingTimer);
                trainingStatus = "completed";
                successMessage = "Preview training completed. No real model was trained or saved.";
            }
        }, 220);
    }

    function showFailure() {
        window.clearInterval(trainingTimer);
        trainingStatus = "failed";
        errorMessage = "The preview run failed intentionally. Backend error and retry behavior still need confirmation.";
    }

    function showInsufficientData() {
        window.clearInterval(trainingTimer);
        trainingStatus = "insufficient_data";
        errorMessage = "";
        successMessage = "";
    }

    function loadModel(model) {
        selectedModelId = model.id;
        modelType = model.type === "Random Forest Regression" ? "random_forest_regression" : "simple_linear_regression";
        datasetId = datasets.find((item) => item.name === model.dataset)?.id || datasetId;
        successMessage = `${model.name} selected in the preview workflow.`;
    }

    onDestroy(() => window.clearInterval(trainingTimer));
</script>

<svelte:head><title>Knowledge Analysis | One Prompt Away</title></svelte:head>

<div class="flex h-screen min-h-[720px] overflow-hidden bg-slate-50 font-sans text-slate-900">
    <AppSidebar activeItem="Knowledge Analysis" bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Knowledge Analysis" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1480px] px-4 py-6 sm:px-8 sm:py-8">
                <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="mb-2 text-xs font-medium text-slate-500">Workspace / Knowledge Analysis</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Knowledge Analysis</h1><p class="mt-1 max-w-3xl text-sm text-slate-500">Inspect sales and product-demand datasets, review quality, and run an illustrative forecasting workflow.</p></div>
                    <span class="w-fit rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-700">Preview only</span>
                </div>
                <div class="mb-6 flex items-start gap-3 rounded-lg border border-blue-100 bg-blue-50/70 px-4 py-3.5 text-xs text-blue-800"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 10v6M12 7h.01" /></svg><p><span class="font-semibold">Illustrative workspace —</span> mock metadata represents datasets with at least 1,000 CSV records. No file has been processed and no machine-learning model is trained by this page.</p></div>
                {#if successMessage}<div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800" role="status">{successMessage}</div>{/if}
                {#if errorMessage}<div class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800" role="alert">{errorMessage}</div>{/if}

                <section class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
                    <article class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Dataset overview</h2><p class="mt-1 text-xs text-slate-500">Select a preview dataset for analysis.</p></div><div class="flex gap-2"><button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-600 hover:bg-slate-50" on:click={() => simulateUpload("invalid")}>Simulate invalid file</button><button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-[11px] font-semibold text-white hover:bg-slate-700" on:click={() => simulateUpload("idle")}>Upload / replace CSV</button></div></div>
                        <label for="dataset-select" class="mt-6 mb-1.5 block text-xs font-medium text-slate-700">Dataset</label><select id="dataset-select" bind:value={datasetId} on:change={selectDataset} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{#each datasets as item}<option value={item.id}>{item.name}</option>{/each}</select>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><div class="rounded-md bg-slate-50 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Records</p><p class="mt-1 text-sm font-semibold text-slate-900">{dataset.records.toLocaleString()}</p></div><div class="rounded-md bg-slate-50 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Columns</p><p class="mt-1 text-sm font-semibold text-slate-900">{dataset.columns}</p></div><div class="rounded-md bg-slate-50 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Coverage</p><p class="mt-1 text-xs font-semibold text-slate-900">{dataset.coverage}</p></div><div class="rounded-md bg-slate-50 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Status</p><p class={`mt-1 text-xs font-semibold ${dataset.status === "validated" ? "text-emerald-700" : "text-amber-700"}`}>{dataset.status === "validated" ? "Validated preview" : "Validation warning"}</p></div></div>
                        <p class="mt-5 text-xs text-slate-600">{dataset.description}</p><div class="mt-4 flex flex-wrap gap-3 text-[11px] text-slate-500"><span>Missing values: <strong class="text-slate-700">{dataset.missing}</strong></span><span>Duplicate rows: <strong class="text-slate-700">{dataset.duplicates}</strong></span><span>Required record minimum: <strong class="text-slate-700">1,000</strong> <em>(proposed assignment rule)</em></span></div>
                        {#if uploadMessage}<div class={`mt-4 rounded-md border px-3 py-2.5 text-xs ${uploadState === "invalid" ? "border-rose-200 bg-rose-50 text-rose-700" : "border-slate-200 bg-slate-50 text-slate-600"}`} role={uploadState === "invalid" ? "alert" : "status"}>{uploadMessage}</div>{/if}
                    </article>
                    <article class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6"><h2 class="text-sm font-semibold text-slate-900">Data quality summary</h2><p class="mt-1 text-xs text-slate-500">Illustrative checks for the selected dataset.</p><div class="mt-6 space-y-4"><div><div class="flex justify-between text-xs"><span class="text-slate-600">Schema readiness</span><strong class="text-emerald-700">{dataset.status === "validated" ? "Ready" : "Review"}</strong></div><div class="mt-2 h-2 rounded-full bg-slate-100"><div class={`h-2 rounded-full ${dataset.status === "validated" ? "w-[92%] bg-emerald-500" : "w-[76%] bg-amber-400"}`}></div></div></div><div class="flex items-center justify-between border-t border-slate-100 pt-4 text-xs"><span class="text-slate-500">Missing values</span><span class="font-semibold text-slate-800">{dataset.missing} rows flagged</span></div><div class="flex items-center justify-between border-t border-slate-100 pt-4 text-xs"><span class="text-slate-500">Duplicate rows</span><span class="font-semibold text-slate-800">{dataset.duplicates} found</span></div><div class="rounded-md border border-amber-100 bg-amber-50 px-3 py-2.5 text-[11px] leading-5 text-amber-800">Validation rules, required columns and missing-value handling need backend confirmation.</div></div></article>
                </section>

                <section class="mt-6 rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="text-sm font-semibold text-slate-900">Dataset preview</h2><p class="mt-1 text-xs text-slate-500">Representative sample rows only; not the complete CSV.</p></div><input bind:value={previewSearch} type="search" placeholder="Search sample rows" aria-label="Search dataset preview" class="h-9 w-full rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-52" /></div>
                    {#if !previewRows.length}<div class="flex min-h-40 items-center justify-center px-6 text-xs text-slate-500">No sample rows match this search.</div>{:else}<div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="px-5 py-3">Date</th><th class="px-5 py-3">Product</th><th class="px-5 py-3">Quantity</th><th class="px-5 py-3">Unit / category</th><th class="px-5 py-3">Target / status</th></tr></thead><tbody class="divide-y divide-slate-100">{#each previewRows as row}<tr><td class="px-5 py-3.5 text-xs text-slate-600">{row.transaction_date}</td><td class="px-5 py-3.5 text-xs font-semibold text-slate-800">{row.product_name}</td><td class="px-5 py-3.5 text-xs text-slate-600">{row.quantity}</td><td class="px-5 py-3.5 text-xs text-slate-600">{row.unit_price}</td><td class="px-5 py-3.5 text-xs text-slate-600">{row.total_amount}</td></tr>{/each}</tbody></table></div>{/if}
                    <div class="border-t border-slate-100 px-5 py-3 text-[11px] text-slate-400">Illustrative columns: {dataset.columns} total columns · displayed sample is limited to {dataset.rows.length} rows.</div>
                    <div class="border-t border-slate-100 px-5 py-4"><p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Preview schema · names and proposed types</p><div class="flex flex-wrap gap-2">{#each schemaColumns as column}<span class="rounded-md bg-slate-50 px-2.5 py-1.5 text-[11px] text-slate-600"><strong class="text-slate-800">{column[0]}</strong> <span class="text-slate-400">({column[1]})</span></span>{/each}</div></div>
                </section>

                <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)]">
                    <article class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6"><div class="flex items-start justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Analysis summary</h2><p class="mt-1 text-xs text-slate-500">Illustrative trends from the selected dataset.</p></div><span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700">Sample analysis</span></div><div class="mt-6"><div class="flex h-48 items-end gap-2 border-b border-l border-slate-200 px-3 pb-0">{#each [38,52,44,68,58,77,64,88,73,94,82,100] as height}<div class="flex-1 rounded-t bg-blue-500/75" style={`height:${height}%`}></div>{/each}</div><div class="mt-2 flex justify-between text-[10px] text-slate-400"><span>Jan</span><span>Apr</span><span>Jul</span><span>Oct</span></div></div><div class="mt-5 grid gap-3 sm:grid-cols-3"><div class="rounded-md border border-slate-100 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Sales trend</p><p class="mt-1 text-sm font-semibold text-emerald-700">+12.8%</p><p class="mt-1 text-[10px] text-slate-400">Illustrative monthly change</p></div><div class="rounded-md border border-slate-100 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Demand coverage</p><p class="mt-1 text-sm font-semibold text-slate-900">19 months</p><p class="mt-1 text-[10px] text-slate-400">Date range represented</p></div><div class="rounded-md border border-slate-100 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-400">Top category</p><p class="mt-1 text-sm font-semibold text-slate-900">Brake System</p><p class="mt-1 text-[10px] text-slate-400">Based on mock records</p></div></div></article>
                    <article class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6"><h2 class="text-sm font-semibold text-slate-900">Product-level performance</h2><p class="mt-1 text-xs text-slate-500">Illustrative demand and sales comparison.</p><div class="mt-5 space-y-4">{#each [{ name: "Kawasaki Ninja Brake Pad", value: "128 units", share: "86%" }, { name: "NGK Iridium Spark Plug", value: "96 units", share: "65%" }, { name: "DID 428H Chain Set", value: "74 units", share: "50%" }] as item}<div><div class="flex justify-between gap-3 text-xs"><span class="truncate text-slate-700">{item.name}</span><strong class="shrink-0 text-slate-800">{item.value}</strong></div><div class="mt-2 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-emerald-500" style={`width:${item.share}`}></div></div></div>{/each}</div></article>
                </section>

                <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <article class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6"><div class="flex items-start justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Model training configuration</h2><p class="mt-1 text-xs text-slate-500">Illustrative settings; no actual training occurs.</p></div><span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">{trainingStatus}</span></div><div class="mt-6 space-y-5"><div><label for="training-target" class="mb-1.5 block text-xs font-medium text-slate-700">Target variable <span class="text-rose-600">*</span></label><select id="training-target" bind:value={targetVariable} class:border-rose-300={formErrors.target} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value={dataset.target}>{dataset.target}</option><option value="units_sold">units_sold</option><option value="total_amount">total_amount</option></select>{#if formErrors.target}<p class="mt-1 text-[11px] text-rose-600">{formErrors.target}</p>{/if}</div><div><p class="mb-2 text-xs font-medium text-slate-700">Input features <span class="text-rose-600">*</span></p><div class="grid gap-2 sm:grid-cols-2">{#each dataset.features as feature}<label class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs text-slate-600"><input type="checkbox" checked={featureSelection.includes(feature)} on:change={() => toggleFeature(feature)} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />{feature}</label>{/each}</div>{#if formErrors.features}<p class="mt-1 text-[11px] text-rose-600">{formErrors.features}</p>{/if}</div><div class="grid gap-4 sm:grid-cols-2"><div><label for="training-split" class="mb-1.5 block text-xs font-medium text-slate-700">Train / test split</label><select id="training-split" bind:value={split} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option>80/20</option><option>70/30</option></select><p class="mt-1 text-[11px] text-slate-400">80/20 is illustrative only.</p></div><div><label for="training-horizon" class="mb-1.5 block text-xs font-medium text-slate-700">Forecast horizon</label><input id="training-horizon" bind:value={horizon} type="number" min="1" max="365" class:border-rose-300={formErrors.horizon} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />{#if formErrors.horizon}<p class="mt-1 text-[11px] text-rose-600">{formErrors.horizon}</p>{/if}</div></div><div><label for="training-model" class="mb-1.5 block text-xs font-medium text-slate-700">Model</label><select id="training-model" bind:value={modelType} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="simple_linear_regression">Simple Linear Regression</option><option value="random_forest_regression">Random Forest Regression</option></select></div>                    <div class="flex flex-wrap gap-2"><button type="button" class="rounded-md bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60" disabled={trainingStatus === "running"} on:click={runTraining}>{trainingStatus === "running" ? `Training ${trainingProgress}%` : "Run preview training"}</button><button type="button" class="rounded-md border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" on:click={showFailure}>Preview failure</button><button type="button" class="text-[11px] font-semibold text-slate-400 underline decoration-slate-300 underline-offset-2 hover:text-slate-600" on:click={showInsufficientData}>Preview insufficient data</button></div>{#if trainingStatus === "insufficient_data"}<p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800" role="alert">This dataset does not meet the proposed 1,000-record minimum.</p>{:else if trainingStatus === "failed"}<p class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs text-rose-700" role="alert">Preview training failed. No model was created.</p>{:else if trainingStatus === "completed"}<p class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs text-emerald-700" role="status">Sample run completed on {dataset.name} using the {split} split.</p>{/if}</div></article>
                    <article class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6"><div class="flex items-start justify-between"><div><h2 class="text-sm font-semibold text-slate-900">Evaluation metrics</h2><p class="mt-1 text-xs text-slate-500">Sample results for the selected model.</p></div><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600">Illustrative</span></div><div class="mt-6 grid gap-3 sm:grid-cols-3">{#each Object.entries(sampleMetrics) as [key, value]}<div class="rounded-md border border-slate-200 p-3"><p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{key}</p><p class="mt-2 text-xl font-semibold text-slate-950">{value}</p><p class="mt-2 text-[10px] leading-4 text-slate-500">{metricDescriptions[key]}</p></div>{/each}</div><div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-[11px] text-slate-500"><p>Model: <strong class="text-slate-700">{modelType === "random_forest_regression" ? "Random Forest Regression" : "Simple Linear Regression"}</strong></p><p>Dataset: <strong class="text-slate-700">{dataset.name}</strong></p><p>Evaluation split: <strong class="text-slate-700">{split}</strong></p><p>Sample run metadata: <strong class="text-slate-700">Oct 28, 2026 · preview</strong></p></div></article>
                </section>

                <section class="mt-6 rounded-lg border border-slate-200 bg-white"><div class="border-b border-slate-200 p-5"><h2 class="text-sm font-semibold text-slate-900">Saved models</h2><p class="mt-1 text-xs text-slate-500">Mock model registry; selecting a model only updates this local preview.</p></div><div class="divide-y divide-slate-100">{#each savedModels as model}<div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="text-xs font-semibold text-slate-800">{model.name}</h3><span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">{model.status}</span></div><p class="mt-1 text-[11px] text-slate-500">{model.type} · v{model.version} · {model.dataset}</p><p class="mt-1 text-[10px] text-slate-400">Trained {model.date} · MAE {model.mae} · RMSE {model.rmse} · MAPE {model.mape}</p></div><button type="button" class="w-fit rounded-md border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-600 hover:bg-slate-50" on:click={() => loadModel(model)}>{selectedModelId === model.id ? "Selected in preview" : "Select model"}</button></div>{/each}</div></section>
            </div>
        </main>
    </div>
</div>
