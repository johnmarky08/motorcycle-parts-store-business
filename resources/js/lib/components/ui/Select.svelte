<script>
    import { cn } from "$lib/utils";
    export let value = "";
    export let disabled = false;
    export let className = "";
</script>

<select bind:value {disabled} class={cn("flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-50", className)}><slot /></select>
