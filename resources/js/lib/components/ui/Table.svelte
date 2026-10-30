<script>
    import Root from "./table/table.svelte";
    export let className = "";
</script>

<Root class={className}><slot /></Root>
