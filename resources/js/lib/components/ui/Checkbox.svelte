<script>
    import Root from "./checkbox/checkbox.svelte";
    export let checked = false;
    export let disabled = false;
    export let className = "";
</script>

<Root {...$$restProps} bind:checked {disabled} class={className} />
