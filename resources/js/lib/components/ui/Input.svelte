<script>
    import Root from "./input/input.svelte";
    export let value = "";
    export let type = "text";
    export let placeholder = "";
    export let disabled = false;
    export let className = "";
</script>

<Root {...$$restProps} bind:value {type} {placeholder} {disabled} class={className} />
