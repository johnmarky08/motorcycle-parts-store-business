<script>
    // Store mark + name, matching the sign-in page and sidebar.
    export let className = "";
</script>

<div class="flex items-center gap-3 {className}">
    <div
        class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white"
    >
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            class="h-5 w-5"
            aria-hidden="true"
            ><path
                d="M5 16.5h14M7 16.5a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3m6 0a3 3 0 1 1-3-3v-1.2a5 5 0 0 1 10 0v1.2a3 3 0 1 1-3 3"
            /><path d="M9 10h6" /></svg
        >
    </div>
    <div class="text-left">
        <p class="text-sm font-semibold tracking-tight text-slate-950">
            One Prompt Away
        </p>
        <p class="mt-0.5 text-xs text-slate-500">Motorcycle Parts Store</p>
    </div>
</div>
