<script>
    import Root from "./badge/badge.svelte";
    export let variant = "default";
    export let className = "";

    $: rootVariant = ["default", "secondary", "destructive", "outline"].includes(variant) ? variant : variant === "destructive" ? "destructive" : "secondary";
    $: toneClass = variant === "success" ? "bg-emerald-50 text-emerald-700" : variant === "warning" ? "bg-amber-50 text-amber-700" : "";
</script>

<Root variant={rootVariant} class={`${toneClass} ${className}`}><slot /></Root>
