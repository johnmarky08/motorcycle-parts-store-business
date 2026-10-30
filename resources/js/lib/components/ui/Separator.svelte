<script>
    import { cn } from "$lib/utils";
    export let orientation = "horizontal";
    export let className = "";
</script>

<div role="separator" aria-orientation={orientation} class={cn(orientation === "vertical" ? "h-full w-px" : "h-px w-full", "shrink-0 bg-slate-200", className)}></div>
