<script>
    import Root from "./label/label.svelte";
    export let forId = "";
    export let className = "";
</script>

<Root for={forId} class={className}><slot /></Root>
