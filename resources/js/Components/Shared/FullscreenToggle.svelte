<script>
    import { onMount } from "svelte";

    // "floating" pins the button to the top-right corner (sign-in and other pages without a header).
    export let floating = false;

    let active = false;
    let supported = false;

    function sync() {
        active = Boolean(document.fullscreenElement);
    }

    onMount(() => {
        supported = Boolean(document.fullscreenEnabled && document.documentElement.requestFullscreen);
        sync();
        document.addEventListener("fullscreenchange", sync);
        return () => document.removeEventListener("fullscreenchange", sync);
    });

    async function toggle() {
        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await document.documentElement.requestFullscreen({ navigationUI: "hide" });
            }
        } catch (error) {
            /* the browser refused (for example inside a frame); the page still fills the window */
        }
    }
</script>

{#if supported}
    <button
        type="button"
        class={floating
            ? "fixed right-4 top-4 z-40 rounded-md border border-slate-200 bg-white/90 p-2 text-slate-500 shadow-sm backdrop-blur transition-colors hover:bg-white hover:text-slate-900"
            : "rounded-md p-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-900"}
        aria-label={active ? "Exit Full Screen" : "Enter Full Screen"}
        title={active ? "Exit Full Screen" : "Enter Full Screen"}
        aria-pressed={active}
        onclick={toggle}
    >
        {#if active}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.75 w-4.75" aria-hidden="true"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" /></svg>
        {:else}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.75 w-4.75" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" /></svg>
        {/if}
    </button>
{/if}
