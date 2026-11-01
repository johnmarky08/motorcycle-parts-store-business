<script>
    import { onMount } from "svelte";
    import AppHeader from "../Components/Layout/AppHeader.svelte";
    import AppSidebar from "../Components/Layout/AppSidebar.svelte";
    import Card from "../lib/components/ui/Card.svelte";
    import Table from "../lib/components/ui/Table.svelte";
    export let auth = { user: null };
    let datasets = [], models = [], insights = { best_selling: [], slow_moving: [] }, loading = true, error = "", mobileMenuOpen = false;
    async function load() {
        try {
            const [datasetResponse, modelResponse, insightResponse] = await Promise.all([fetch("/analysis/datasets"), fetch("/analysis/models"), fetch("/analysis/insights")]);
            datasets = (await datasetResponse.json()).datasets || [];
            models = (await modelResponse.json()).models || [];
            insights = await insightResponse.json();
        } catch (exception) { error = "Knowledge analysis data could not be loaded."; }
        loading = false;
    }
    onMount(load);
</script>
<svelte:head><title>Knowledge Analysis | One Prompt Away</title></svelte:head>
<div class="flex min-h-screen overflow-x-hidden bg-slate-50/80 font-sans text-slate-900 lg:h-screen">
    <AppSidebar activeItem="Knowledge Analysis" {auth} bind:mobileMenuOpen />
    <div class="flex min-w-0 flex-1 flex-col"><AppHeader title="Knowledge Analysis" bind:mobileMenuOpen /><main class="min-h-0 flex-1 overflow-y-auto"><div class="mx-auto max-w-[1180px] px-4 py-6 sm:px-8 sm:py-7">
        <p class="mb-2 text-xs font-medium text-slate-500">Workspace / Knowledge Analysis</p><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Knowledge Analysis</h1><p class="mt-1 text-sm text-slate-500">Review validated datasets and persisted model evaluation results.</p>
        <div class="mt-6 rounded-lg border border-blue-100 bg-blue-50/70 px-4 py-3 text-xs text-blue-800">Synthetic datasets support model development only. Operational product analysis should use completed database sales.</div>
        {#if loading}<p class="mt-8 text-sm text-slate-500">Loading analysis data...</p>{:else if error}<p class="mt-8 text-sm text-rose-600">{error}</p>{:else}
            <Card className="mt-6 p-5"><h2 class="text-sm font-semibold">Datasets</h2><Table className="mt-4 text-left text-xs"><thead class="border-b border-slate-200 text-slate-500"><tr><th class="pb-3">Dataset</th><th class="pb-3">Records</th><th class="pb-3">Required columns</th><th class="pb-3">Validation</th></tr></thead><tbody>{#each datasets as dataset}<tr class="border-b border-slate-100 last:border-0"><td class="py-3 font-medium">{dataset.name}<div class="font-normal text-slate-500">{dataset.description}</div></td><td class="py-3">{dataset.record_count}</td><td class="py-3">{dataset.required_columns?.join(", ")}</td><td class="py-3">{dataset.valid ? "Valid" : "Invalid"}</td></tr>{/each}</tbody></Table></Card>
            <Card className="mt-6 p-5"><h2 class="text-sm font-semibold">Saved models</h2>{#if !models.length}<p class="mt-4 text-sm text-slate-500">No trained models have been saved yet.</p>{:else}<Table className="mt-4 text-left text-xs"><thead class="border-b border-slate-200 text-slate-500"><tr><th class="pb-3">Dataset</th><th class="pb-3">Algorithm</th><th class="pb-3">Records</th><th class="pb-3">MAE</th><th class="pb-3">RMSE</th><th class="pb-3">R²</th></tr></thead><tbody>{#each models as model}<tr class="border-b border-slate-100 last:border-0"><td class="py-3">{model.dataset_id}</td><td class="py-3">{model.algorithm}</td><td class="py-3">{model.record_count}</td><td class="py-3">{Number(model.metrics.mae).toFixed(3)}</td><td class="py-3">{Number(model.metrics.rmse).toFixed(3)}</td><td class="py-3">{Number(model.metrics.r2).toFixed(3)}</td></tr>{/each}</tbody></Table>{/if}</Card>
            <section class="mt-6 grid gap-6 md:grid-cols-2"><div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-semibold">Best-selling products</h2>{#each insights.best_selling as item}<div class="flex justify-between border-b border-slate-100 py-3 text-xs last:border-0"><span>{item.name}</span><strong>{item.units_sold} units</strong></div>{:else}<p class="mt-4 text-sm text-slate-500">No completed sales yet.</p>{/each}</div><div class="rounded-lg border border-slate-200 bg-white p-5"><h2 class="text-sm font-semibold">Slow-moving products</h2>{#each insights.slow_moving as item}<div class="flex justify-between border-b border-slate-100 py-3 text-xs last:border-0"><span>{item.name}</span><strong>{item.stock_quantity} in stock</strong></div>{:else}<p class="mt-4 text-sm text-slate-500">No slow-moving products found.</p>{/each}</div></section>
        {/if}
    </div></main></div>
</div>
