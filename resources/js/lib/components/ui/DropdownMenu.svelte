<script>
    import { cn } from "$lib/utils";
    export let label = "Options";
    export let className = "";
</script>

<details class={cn("relative inline-block", className)}>
    <summary class="cursor-pointer list-none rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{label}</summary>
    <div class="absolute right-0 z-20 mt-2 min-w-40 rounded-md border border-slate-200 bg-white p-1 shadow-md"><slot /></div>
</details>
