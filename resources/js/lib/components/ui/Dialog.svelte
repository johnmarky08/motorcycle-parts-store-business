<script>
    import { cn } from "$lib/utils";
    export let open = false;
    export let title = "";
    export let className = "";
</script>

{#if open}
    <div class="fixed inset-0 z-50 flex min-h-full items-center justify-center overflow-y-auto bg-slate-950/30 p-4" role="presentation" on:click|self={() => (open = false)}>
        <section role="dialog" aria-modal="true" aria-label={title} class={cn("max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg border border-slate-200 bg-white p-6 shadow-lg", className)}>
            {#if title}<h2 class="text-lg font-semibold text-slate-950">{title}</h2>{/if}
            <slot />
        </section>
    </div>
{/if}
