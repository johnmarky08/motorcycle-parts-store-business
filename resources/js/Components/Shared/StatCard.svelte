<script>
    import * as Card from "../../lib/components/ui/card/index.js";
    import Badge from "../../lib/components/ui/Badge.svelte";
    export let label;
    export let value;
    export let change;
    export let trend = "up";
    export let icon = "chart";
    export let note = "";
</script>

<Card.Root class="rounded-xl shadow-sm transition-shadow hover:shadow-md">
<Card.Content class="p-5">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-xs font-medium text-slate-500">{label}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{value}</p>
        </div>
        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-50 text-blue-600">
            {#if icon === "sales"}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-[18px] w-[18px]" aria-hidden="true"><path d="M4 19V5m0 14h16" /><path d="m7 15 3-4 3 2 5-7" /></svg>
            {:else if icon === "products"}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-[18px] w-[18px]" aria-hidden="true"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" /><path d="m4 7.5 8 4.5 8-4.5M12 12v9" /></svg>
            {:else if icon === "stock"}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-[18px] w-[18px]" aria-hidden="true"><path d="M12 3 3.5 19h17L12 3Z" /><path d="M12 9v4M12 16h.01" /></svg>
            {:else}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-[18px] w-[18px]" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h4" /></svg>
            {/if}
        </div>
    </div>
    <div class="mt-4 flex items-center gap-1.5 text-xs">
        <Badge variant={trend === "down" ? "destructive" : trend === "neutral" ? "secondary" : "success"}>{trend === "down" ? "↓ " : trend === "neutral" ? "" : "↑ "}{change}</Badge>
        {#if note}<span class="text-slate-400">{note}</span>{/if}
    </div>
</Card.Content>
</Card.Root>
