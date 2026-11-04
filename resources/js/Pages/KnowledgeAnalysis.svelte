<script>
    import { Link, router } from "@inertiajs/svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Button from "../lib/components/ui/Button.svelte";

    export let datasets = [];
    export let selectedDatasetId = null;
    export let preview = null;
    export let search = "";
    export let models = [];
    export let metricDescriptions = {};

    const algorithms = [
        { value: "simple_linear_regression", label: "Simple Linear Regression" },
        { value: "random_forest_regression", label: "Random Forest Regression" },
    ];
    const statusStyles = { validated: "bg-emerald-50 text-emerald-700", warning: "bg-amber-50 text-amber-700", invalid: "bg-rose-50 text-rose-700" };
    const metricLabels = { mae: "MAE", rmse: "RMSE", mape: "MAPE", wape: "WAPE", r2: "R²" };

    let mobileMenuOpen = false;
    let successMessage = "";
    let errorMessage = "";
    let searchText = search;
    let searchTimer;
    let showUpload = false;
    let uploadForm = { name: "", type: "sales_history", file: null };
    let uploadErrors = {};
    let uploading = false;
    let training = false;
    let trainForm = { dataset_id: "", algorithm: "random_forest_regression", split: "0.8", tune: false, seasonal: false };
    let trainErrors = {};
    let expandedModel = null;

    export let status = null;
    export let error = null;
    $: if (status) successMessage = status;
    $: if (error) errorMessage = error;

    $: selected = datasets.find((dataset) => dataset.id === selectedDatasetId) ?? null;
    $: trainable = datasets.filter((dataset) => dataset.trainable);
    $: if (!trainForm.dataset_id && trainable.length) trainForm = { ...trainForm, dataset_id: String(trainable[0].id) };
    $: monthly = selected?.summary?.monthly ?? [];
    $: monthlyMax = Math.max(1, ...monthly.map((item) => item.value));

    function number(value) {
        return Number(value).toLocaleString("en-PH");
    }

    function metric(value, key) {
        if (value === null || value === undefined) return "n/a";
        return key === "mape" || key === "wape" ? `${Number(value).toFixed(1)}%` : Number(value).toLocaleString("en-PH", { maximumFractionDigits: 3 });
    }

    function visit(params) {
        router.get("/knowledge-analysis", params, { preserveState: true, preserveScroll: true, replace: true, only: ["selectedDatasetId", "preview", "search"] });
    }

    function selectDataset(id) {
        searchText = "";
        visit({ dataset: id });
    }

    function onSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => visit({ dataset: selectedDatasetId, q: searchText.trim() || undefined }), 350);
    }

    function goToPage(target) {
        visit({ dataset: selectedDatasetId, q: searchText.trim() || undefined, page: target > 1 ? target : undefined });
    }

    function openUpload() {
        uploadForm = { name: "", type: "sales_history", file: null };
        uploadErrors = {};
        errorMessage = "";
        successMessage = "";
        showUpload = true;
    }

    function submitUpload() {
        const next = {};
        if (uploadForm.name.trim().length < 3) next.name = "Enter a dataset name (at least 3 characters).";
        if (!uploadForm.file) next.file = "Choose a CSV file.";
        else if (!/\.csv$/i.test(uploadForm.file.name)) next.file = "Only .csv files are accepted.";
        else if (uploadForm.file.size > 20 * 1024 * 1024) next.file = "The file may not be larger than 20 MB.";
        uploadErrors = next;
        if (Object.keys(next).length || uploading) return;

        router.post("/knowledge-analysis/datasets", { name: uploadForm.name.trim(), type: uploadForm.type, file: uploadForm.file }, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (uploading = true),
            onFinish: () => (uploading = false),
            onSuccess: () => (showUpload = false),
            onError: (serverErrors) => (uploadErrors = serverErrors),
        });
    }

    function removeDataset(dataset) {
        if (!window.confirm(`Remove "${dataset.name}"? Saved models trained on it stay available.`)) return;
        router.delete(`/knowledge-analysis/datasets/${dataset.id}`, { preserveScroll: true });
    }

    function submitTrain() {
        trainErrors = {};
        errorMessage = "";
        successMessage = "";
        if (!trainForm.dataset_id) {
            trainErrors.dataset_id = "Select a dataset.";
            return;
        }
        if (training) return;
        router.post("/knowledge-analysis/train", { ...trainForm, dataset_id: Number(trainForm.dataset_id), split: Number(trainForm.split) }, {
            preserveScroll: true,
            onStart: () => (training = true),
            onFinish: () => (training = false),
            onError: (serverErrors) => (trainErrors = serverErrors),
        });
    }

    function activate(model) {
        router.post(`/knowledge-analysis/models/${model.id}/activate`, {}, { preserveScroll: true });
    }
</script>

<svelte:head><title>Knowledge Analysis | One Prompt Away</title></svelte:head>

<div class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen">
    <AppSidebar activeItem="Knowledge Analysis" bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col">
        <AppHeader title="Knowledge Analysis" bind:mobileMenuOpen />
        <main class="min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto max-w-[1180px] px-4 py-6 sm:px-8 sm:py-7">
                <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="mb-2 text-xs font-medium text-slate-500">Workspace / Knowledge Analysis</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Knowledge analysis</h1><p class="mt-1 max-w-3xl text-sm text-slate-500">Inspect datasets, train and save Simple Linear Regression and Random Forest models, and review their evaluation metrics.</p></div>
                    <Button type="button" on:click={openUpload} className="text-xs"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 20h16" /></svg>Upload dataset</Button>
                </div>

                {#if successMessage}<div class="mb-5 flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800" role="status"><span>{successMessage}</span><button type="button" class="font-semibold" on:click={() => (successMessage = "")}>Dismiss</button></div>{/if}
                {#if errorMessage}<div class="mb-5 flex items-center justify-between rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800" role="alert"><span>{errorMessage}</span><button type="button" class="font-semibold" on:click={() => (errorMessage = "")}>Dismiss</button></div>{/if}

                <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Datasets">
                    {#each datasets as dataset (dataset.id)}
                        <button type="button" class={`rounded-lg border p-5 text-left transition-colors ${dataset.id === selectedDatasetId ? "border-blue-300 bg-blue-50/50" : "border-slate-200 bg-white hover:border-slate-300"}`} on:click={() => selectDataset(dataset.id)}>
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{dataset.name}</p><p class="mt-1 text-[11px] text-slate-500">{dataset.type_label} · {dataset.is_system ? "built-in" : "uploaded"}</p></div><span class={`shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold capitalize ${statusStyles[dataset.status]}`}>{dataset.status}</span></div>
                            <div class="mt-4 grid grid-cols-3 gap-2 text-xs"><div><p class="text-slate-400">Records</p><p class="mt-1 font-semibold text-slate-800">{number(dataset.records)}</p></div><div><p class="text-slate-400">Columns</p><p class="mt-1 font-semibold text-slate-800">{dataset.columns}</p></div><div class="col-span-1"><p class="text-slate-400">Target</p><p class="mt-1 truncate font-semibold text-slate-800">{dataset.target}</p></div></div>
                            <p class="mt-3 text-[11px] text-slate-400">{dataset.coverage}</p>
                        </button>
                    {:else}
                        <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-xs text-slate-500 md:col-span-2 xl:col-span-3">No datasets yet. Upload a CSV or run <code>php artisan db:seed</code>.</p>
                    {/each}
                </section>

                {#if selected}
                    <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3"><div><h2 class="text-sm font-semibold text-slate-900">Dataset quality</h2><p class="mt-1 text-xs text-slate-500">{selected.description}</p></div>{#if !selected.is_system}<button type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-700" on:click={() => removeDataset(selected)}>Remove</button>{/if}</div>
                            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 text-xs">
                                {#each [["Records", number(selected.records)], ["Missing values", number(selected.missing)], ["Duplicate rows", number(selected.duplicates)], ["Invalid rows", number(selected.invalid)]] as tile}<div class="rounded-md border border-slate-100 p-3"><p class="text-slate-400">{tile[0]}</p><p class="mt-1 text-sm font-semibold text-slate-900">{tile[1]}</p></div>{/each}
                            </div>
                            <div class="mt-5 text-xs text-slate-600"><p><span class="text-slate-400">Coverage:</span> {selected.coverage}</p><p class="mt-1"><span class="text-slate-400">Target variable:</span> <strong>{selected.target}</strong></p><p class="mt-1"><span class="text-slate-400">Input features:</span> {selected.features.join(", ")}</p>{#if selected.filename}<p class="mt-1"><span class="text-slate-400">File:</span> {selected.filename}</p>{/if}</div>
                            <div class="mt-5 overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="pb-2">Column</th><th class="pb-2">Type</th></tr></thead><tbody class="divide-y divide-slate-50">{#each selected.schema as column}<tr><td class="py-1.5 text-xs text-slate-700">{column[0]}</td><td class="py-1.5 text-xs text-slate-400">{column[1]}</td></tr>{/each}</tbody></table></div>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-sm font-semibold text-slate-900">Analysis summary</h2><p class="mt-1 text-xs text-slate-500">{selected.summary?.metric} per month (latest 12 months in the data)</p>
                            <div class="mt-5 flex h-40 items-end gap-1.5" role="img" aria-label="Monthly totals">
                                {#each monthly as item}<div class="group flex min-w-0 flex-1 flex-col items-center justify-end gap-1" title={`${item.month}: ${number(item.value)}`}><div class="w-full rounded-t bg-blue-500/80 group-hover:bg-blue-600" style={`height: ${Math.max(2, (item.value / monthlyMax) * 100)}%`}></div><span class="text-[9px] text-slate-400">{item.month.slice(5)}</span></div>{/each}
                            </div>
                            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-600"><span><span class="text-slate-400">Latest 28 days vs previous 28:</span> <strong class:text-rose-600={(selected.summary?.trend_percent ?? 0) < 0} class:text-emerald-700={(selected.summary?.trend_percent ?? 0) >= 0}>{selected.summary?.trend_percent === null ? "n/a" : `${selected.summary.trend_percent > 0 ? "+" : ""}${selected.summary.trend_percent}%`}</strong></span><span><span class="text-slate-400">Products:</span> <strong>{selected.summary?.products}</strong></span></div>
                            <h3 class="mt-5 text-xs font-semibold text-slate-800">Top products by units</h3>
                            <div class="mt-2 divide-y divide-slate-100">{#each selected.summary?.top_products ?? [] as product, index}<div class="flex items-center gap-3 py-2 text-xs"><span class="w-4 font-semibold text-slate-400">{index + 1}</span><span class="min-w-0 flex-1 truncate text-slate-700">{product.name}</span><span class="text-slate-500">{number(product.units)} units</span></div>{/each}</div>
                        </article>
                    </section>

                    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="text-sm font-semibold text-slate-900">Dataset preview</h2><p class="mt-1 text-xs text-slate-500">{preview ? `${number(preview.total)} matching rows · page ${preview.page} of ${preview.last_page}` : "No preview available"}</p></div><input bind:value={searchText} on:input={onSearch} type="search" placeholder="Search rows" aria-label="Search dataset rows" class="h-9 w-full rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-60" /></div>
                        {#if preview && preview.rows.length}
                            <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{#each preview.columns as column}<th class="whitespace-nowrap px-4 py-3">{column}</th>{/each}</tr></thead><tbody class="divide-y divide-slate-100">{#each preview.rows as row}<tr>{#each preview.columns as column}<td class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-600">{row[column]}</td>{/each}</tr>{/each}</tbody></table></div>
                            <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-slate-500"><span>{preview.per_page} rows per page</span><div class="flex gap-2"><button type="button" disabled={preview.page <= 1} class="rounded-md border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40" on:click={() => goToPage(preview.page - 1)}>Previous</button><button type="button" disabled={preview.page >= preview.last_page} class="rounded-md border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40" on:click={() => goToPage(preview.page + 1)}>Next</button></div></div>
                        {:else}
                            <div class="flex min-h-40 items-center justify-center px-6 text-center text-xs text-slate-500">No rows match your search.</div>
                        {/if}
                    </section>
                {/if}

                <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Train a model</h2><p class="mt-1 text-xs text-slate-500">Training evaluates the model on the most recent part of the data, saves it, and makes it the active model for predictions.</p>
                        <form class="mt-5 space-y-4" on:submit|preventDefault={submitTrain}>
                            <div><label for="train-dataset" class="mb-1.5 block text-xs font-medium text-slate-700">Dataset</label><select id="train-dataset" bind:value={trainForm.dataset_id} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="">Select a dataset</option>{#each trainable as dataset}<option value={String(dataset.id)}>{dataset.name} ({number(dataset.records)} rows)</option>{/each}</select>{#if trainErrors.dataset_id}<p class="mt-1 text-[11px] text-rose-600">{trainErrors.dataset_id}</p>{/if}</div>
                            <div><label for="train-algorithm" class="mb-1.5 block text-xs font-medium text-slate-700">Algorithm</label><select id="train-algorithm" bind:value={trainForm.algorithm} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{#each algorithms as algorithm}<option value={algorithm.value}>{algorithm.label}</option>{/each}</select></div>
                            <div><label for="train-split" class="mb-1.5 block text-xs font-medium text-slate-700">Training share (rest is the hold-out test)</label><select id="train-split" bind:value={trainForm.split} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="0.7">70% train / 30% test</option><option value="0.8">80% train / 20% test</option><option value="0.9">90% train / 10% test</option></select></div>
                            {#if trainForm.algorithm === "random_forest_regression"}<label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" bind:checked={trainForm.tune} class="mt-0.5 rounded border-slate-300" /><span>Tune hyperparameters with time-series cross-validation <span class="text-slate-400">(slower, may improve accuracy)</span></span></label>{:else}<label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" bind:checked={trainForm.seasonal} class="mt-0.5 rounded border-slate-300" /><span>Apply a month-of-year seasonal index <span class="text-slate-400">(only helps with 2+ years of data)</span></span></label>{/if}
                            {#if trainErrors.algorithm || trainErrors.split}<p class="text-[11px] text-rose-600">{trainErrors.algorithm ?? trainErrors.split}</p>{/if}
                            <Button type="submit" disabled={training || !trainable.length} className="text-xs">{#if training}<span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>Training… please keep this page open{:else}Train and save model{/if}</Button>
                        </form>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Evaluation metrics</h2><p class="mt-1 text-xs text-slate-500">What each number in the model table means.</p>
                        <dl class="mt-4 space-y-3">{#each Object.keys(metricLabels) as key}<div class="rounded-md border border-slate-100 p-3"><dt class="text-xs font-semibold text-slate-800">{metricLabels[key]}</dt><dd class="mt-1 text-[11px] leading-5 text-slate-500">{metricDescriptions[key]}</dd></div>{/each}</dl>
                        <p class="mt-4 text-[11px] leading-5 text-slate-400">Metrics are measured on weeks the model never saw during training, using rolling forecasts (sales: 4 weeks ahead; demand: 4 weeks ahead per product). Weekly product demand has small counts, so its percentage errors are naturally higher than sales.</p>
                    </article>
                </section>

                <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-4 sm:p-5"><h2 class="text-sm font-semibold text-slate-900">Saved models</h2><p class="mt-1 text-xs text-slate-500">The active model of each type is the one used on the Prediction page.</p></div>
                    {#if models.length === 0}
                        <div class="flex min-h-40 items-center justify-center px-6 text-center text-xs text-slate-500">No models yet. Train one above or run <code class="mx-1">php artisan ml:train</code>.</div>
                    {:else}
                        <div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left"><thead><tr class="border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><th class="px-5 py-3">Model</th><th class="px-3 py-3">Dataset</th><th class="px-3 py-3 text-right">MAE</th><th class="px-3 py-3 text-right">RMSE</th><th class="px-3 py-3 text-right">MAPE</th><th class="px-3 py-3 text-right">WAPE</th><th class="px-3 py-3 text-right">R²</th><th class="px-3 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each models as model (model.id)}
                                    <tr class="align-middle"><td class="px-5 py-3.5"><p class="text-xs font-semibold text-slate-800">{model.name}</p><p class="mt-0.5 text-[10px] text-slate-400">v{model.version} · {model.trained_at}</p></td><td class="px-3 py-3.5 text-xs text-slate-500">{model.dataset}</td><td class="px-3 py-3.5 text-right text-xs text-slate-700">{metric(model.mae, "mae")}</td><td class="px-3 py-3.5 text-right text-xs text-slate-700">{metric(model.rmse, "rmse")}</td><td class="px-3 py-3.5 text-right text-xs text-slate-700">{metric(model.mape, "mape")}</td><td class="px-3 py-3.5 text-right text-xs text-slate-700">{metric(model.wape, "wape")}</td><td class="px-3 py-3.5 text-right text-xs text-slate-700">{metric(model.r2, "r2")}</td><td class="px-3 py-3.5"><span class:bg-emerald-50={model.is_active} class:text-emerald-700={model.is_active} class:bg-slate-100={!model.is_active} class:text-slate-500={!model.is_active} class="rounded-full px-2 py-1 text-[10px] font-semibold">{model.is_active ? "Active" : "Saved"}</span></td><td class="px-5 py-3.5"><div class="flex justify-end gap-1"><button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100" on:click={() => (expandedModel = expandedModel === model.id ? null : model.id)}>{expandedModel === model.id ? "Hide" : "Details"}</button>{#if !model.is_active}<button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-blue-600 hover:bg-blue-50" on:click={() => activate(model)}>Use for predictions</button>{/if}</div></td></tr>
                                    {#if expandedModel === model.id}
                                        <tr class="bg-slate-50/60"><td colspan="9" class="px-5 py-4 text-xs text-slate-600"><div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><p><span class="text-slate-400">Algorithm:</span> {model.algorithm_label}</p><p><span class="text-slate-400">Target:</span> {model.target}</p><p><span class="text-slate-400">Training share:</span> {Math.round(model.split * 100)}% ({model.train_points} train / {model.test_points} test periods)</p><p><span class="text-slate-400">Trained on data through:</span> {model.trained_through}</p></div><p class="mt-3"><span class="text-slate-400">Features:</span> {model.features.join(", ")}</p>{#if model.eval_protocol}<p class="mt-1"><span class="text-slate-400">Evaluation:</span> {model.eval_protocol}</p>{/if}{#if Object.keys(model.params).length}<p class="mt-1"><span class="text-slate-400">Tuned parameters:</span> {Object.entries(model.params).map(([key, value]) => `${key}=${value}`).join(", ")}</p>{/if}{#if model.notes.length}<ul class="mt-2 list-disc pl-5 text-slate-500">{#each model.notes as note}<li>{note}</li>{/each}</ul>{/if}</td></tr>
                                    {/if}
                                {/each}
                            </tbody></table></div>
                    {/if}
                </section>
            </div>
        </main>
    </div>
</div>

{#if showUpload}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 p-4" role="presentation" on:click={(event) => event.target === event.currentTarget && (showUpload = false)}>
        <dialog open class="static m-0 w-full max-w-lg rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-xl" aria-labelledby="upload-title">
            <div class="flex items-start justify-between"><div><h2 id="upload-title" class="text-base font-semibold text-slate-950">Upload dataset</h2><p class="mt-1 text-xs text-slate-500">CSV only, up to 20 MB, at least 1,000 records. The file is validated before it is saved.</p></div><button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close upload form" on:click={() => (showUpload = false)}>×</button></div>
            <form class="mt-6 space-y-4" on:submit|preventDefault={submitUpload}>
                <div><label for="upload-name" class="mb-1.5 block text-xs font-medium text-slate-700">Dataset name <span class="text-rose-600">*</span></label><input id="upload-name" bind:value={uploadForm.name} class:border-rose-300={uploadErrors.name} class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />{#if uploadErrors.name}<p class="mt-1 text-[11px] text-rose-600">{uploadErrors.name}</p>{/if}</div>
                <div><label for="upload-type" class="mb-1.5 block text-xs font-medium text-slate-700">Dataset type <span class="text-rose-600">*</span></label><select id="upload-type" bind:value={uploadForm.type} class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="sales_history">Sales history (one row per sold item)</option><option value="product_demand">Product demand (weekly units per product)</option></select><p class="mt-1.5 text-[11px] leading-4 text-slate-400">{uploadForm.type === "sales_history" ? "Required columns: transaction_number, transaction_date, product_id, product_name, quantity, unit_price, total_amount." : "Required columns: week_start, product_id, product_name, category, units_sold."}</p></div>
                <div><label for="upload-file" class="mb-1.5 block text-xs font-medium text-slate-700">CSV file <span class="text-rose-600">*</span></label><input id="upload-file" type="file" accept=".csv,text/csv" on:change={(event) => (uploadForm.file = event.currentTarget.files?.[0] ?? null)} class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200" />{#if uploadErrors.file}<p class="mt-1 text-[11px] text-rose-600">{uploadErrors.file}</p>{/if}</div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-5"><button type="button" class="rounded-md border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" on:click={() => (showUpload = false)}>Cancel</button><Button type="submit" disabled={uploading} className="text-xs">{uploading ? "Validating…" : "Upload and validate"}</Button></div>
            </form>
        </dialog>
    </div>
{/if}
