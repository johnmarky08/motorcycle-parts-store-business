<script>
    import { createEventDispatcher } from "svelte";
    import Root from "./button/button.svelte";

    export let variant = "default";
    export let size = "default";
    export let type = "button";
    export let disabled = false;
    export let className = "";
    export let onclick;

    const dispatch = createEventDispatcher();

    function handleClick(event) {
        onclick?.(event);
        dispatch("click", event);
    }
</script>

<Root {...$$restProps} {variant} {size} {type} {disabled} class={className} onclick={handleClick}>
    <slot />
</Root>
