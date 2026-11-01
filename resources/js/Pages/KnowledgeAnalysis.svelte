<script>
    import { onMount } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Card from "../lib/components/ui/Card.svelte";
    import Table from "../lib/components/ui/Table.svelte";
    export let auth = { user: null };
    let datasets = [], algorithms = [], models = [], insights = { best_selling: [], slow_moving: [] }, loading = true, error = "", mobileMenuOpen = false;
    let selectedDataset = "", selectedAlgorithm = "", validating = false, training = false, actionMessage = "";
    async function load() {
        try {
            const [datasetResponse, modelResponse, insightResponse] = await Promise.all([fetch("/analysis/datasets"), fetch("/analysis/models"), fetch("/analysis/insights")]);
            const datasetData = await datasetResponse.json();
            datasets = datasetData.datasets || [];
            algorithms = datasetData.algorithms || [];
            selectedDataset ||= datasets[0]?.id || "";
            selectedAlgorithm ||= algorithms[0]?.id || "";
            models = (await modelResponse.json()).models || [];
            insights = await insightResponse.json();
        } catch (exception) { error = "Knowledge analysis data could not be loaded."; }
        loading = false;
    }
    async function validateDatasets() {
        validating = true;
        actionMessage = "";
        try {
            const response = await fetch("/analysis/datasets", { headers: { Accept: "application/json" } });
            if (!response.ok) throw new Error("Validation request failed.");
            const data = await response.json();
            datasets = data.datasets || [];
            algorithms = data.algorithms || algorithms;
            actionMessage = "Dataset validation completed.";
        } catch (exception) {
            actionMessage = exception.message;
        } finally {
            validating = false;
        }
    }
    async function trainModel() {
        training = true;
        actionMessage = "";
        try {
            const response = await fetch("/analysis/train", {
                method: "POST",
                headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": decodeURIComponent(document.cookie.split("XSRF-TOKEN=")[1]?.split(";")[0] || "") },
                body: JSON.stringify({ dataset_id: selectedDataset, algorithm: selectedAlgorithm }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || "Training failed.");
            models = [data.model, ...models];
            actionMessage = "Model trained and saved successfully.";
        } catch (exception) {
            actionMessage = exception.message;
        } finally {
            training = false;
        }
    }
    onMount(load);
</script>
<svelte:head><title>Knowledge Analysis | One Prompt Away</title></svelte:head>
<div class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen">
    <AppSidebar activeItem="Knowledge Analysis" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col"><AppHeader title="Knowledge Analysis" bind:mobileMenuOpen /><main class="min-h-0 flex-1 overflow-y-auto"><div class="mx-auto max-w-[1180px] px-4 py-6 sm:px-8 sm:py-7">
        <p class="mb-2 text-xs font-medium text-slate-500">Workspace / Knowledge Analysis</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Knowledge Analysis</h1><p class="mt-1 text-sm text-slate-500">Review validated datasets and persisted model evaluation results.</p>
        <div class="mt-6 rounded-lg border border-blue-100 bg-blue-50/70 px-4 py-3 text-xs text-blue-800">Synthetic datasets support model development only. Operational product analysis should use completed database sales.</div>
        {#if actionMessage}<div class="mt-4 rounded-md border border-slate-200 bg-white px-4 py-3 text-xs text-slate-700" role="status">{actionMessage}</div>{/if}
        {#if loading}<p class="mt-8 text-sm text-slate-500">Loading analysis data...</p>{:else if error}<p class="mt-8 text-sm text-rose-600">{error}</p>{:else}
            <Card className="mt-6 p-5"><div class="flex items-center justify-between"><div><h2 class="text-sm font-semibold">Dataset management</h2><p class="mt-1 text-xs text-slate-500">The existing datasets are synthetic and are not live business forecasts.</p></div><button type="button" disabled={validating} class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50" on:click={validateDatasets}>{validating ? "Validating..." : "Validate datasets"}</button></div><Table className="mt-4 text-left text-xs"><thead class="border-b border-slate-200 text-slate-500"><tr><th class="pb-3">Dataset</th><th class="pb-3">Records</th><th class="pb-3">Validation</th></tr></thead><tbody>{#each datasets as dataset}<tr class="border-b border-slate-100 last:border-0"><td class="py-3 font-medium">{dataset.name}<div class="font-normal text-slate-500">{dataset.description}</div></td><td class="py-3">{dataset.record_count}</td><td class="py-3">{dataset.valid ? "Valid" : "Invalid"}{#if dataset.errors?.length}<div class="text-rose-600">{dataset.errors.join(" ")}</div>{/if}</td></tr>{/each}</tbody></Table></Card>
            <Card className="mt-6 p-5"><h2 class="text-sm font-semibold">Model training</h2><p class="mt-1 text-xs text-slate-500">Training uses the selected dataset and persists the server-generated model artifact.</p><form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" on:submit|preventDefault={trainModel}><label class="flex-1 text-xs font-medium">Dataset<select bind:value={selectedDataset} class="mt-1 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm">{#each datasets as dataset}<option value={dataset.id}>{dataset.name}</option>{/each}</select></label><label class="flex-1 text-xs font-medium">Algorithm<select bind:value={selectedAlgorithm} class="mt-1 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm">{#each algorithms as algorithm}<option value={algorithm.id}>{algorithm.name}</option>{/each}</select></label><button type="submit" disabled={training || !selectedDataset || !selectedAlgorithm} class="h-10 rounded-md bg-blue-600 px-4 text-xs font-semibold text-white disabled:opacity-50">{training ? "Training..." : "Train model"}</button></form></Card>
            <Card className="mt-6 p-5"><h2 class="text-sm font-semibold">Saved models</h2>{#if !models.length}<p class="mt-4 text-sm text-slate-500">No trained models have been saved yet.</p>{:else}<Table className="mt-4 text-left text-xs"><thead class="border-b border-slate-200 text-slate-500"><tr><th class="pb-3">Dataset</th><th class="pb-3">Algorithm</th><th class="pb-3">Records</th><th class="pb-3">MAE</th><th class="pb-3">RMSE</th><th class="pb-3">R²</th></tr></thead><tbody>{#each models as model}<tr class="border-b border-slate-100 last:border-0"><td class="py-3">{model.dataset_id}</td><td class="py-3">{model.algorithm}</td><td class="py-3">{model.record_count}</td><td class="py-3">{Number(model.metrics.mae).toFixed(3)}</td><td class="py-3">{Number(model.metrics.rmse).toFixed(3)}</td><td class="py-3">{Number(model.metrics.r2).toFixed(3)}</td></tr>{/each}</tbody></Table>{/if}</Card>
            <section class="mt-6 grid gap-6 md:grid-cols-2"><div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-semibold">Best-selling products</h2>{#each insights.best_selling as item}<div class="flex justify-between border-b border-slate-100 py-3 text-xs last:border-0"><span>{item.name}</span><strong>{item.units_sold} units</strong></div>{:else}<p class="mt-4 text-sm text-slate-500">No completed sales yet.</p>{/each}</div><div class="rounded-lg border border-slate-200 bg-white p-5"><h2 class="text-sm font-semibold">Slow-moving products</h2>{#each insights.slow_moving as item}<div class="flex justify-between border-b border-slate-100 py-3 text-xs last:border-0"><span>{item.name}</span><strong>{item.stock_quantity} in stock</strong></div>{:else}<p class="mt-4 text-sm text-slate-500">No slow-moving products found.</p>{/each}</div></section>
        {/if}
    </div></main></div>
</div>
