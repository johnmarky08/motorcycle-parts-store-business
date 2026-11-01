<script>
    import Root from "./card/card.svelte";
    export let className = "";
</script>

<Root class={className}><slot /></Root>
