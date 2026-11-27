<script>
    import { Link } from "@inertiajs/svelte";
    import BrandLogo from "../Components/Shared/BrandLogo.svelte";
    import FullscreenToggle from "../Components/Shared/FullscreenToggle.svelte";
    export let status = 500;

    const messages = {
        403: {
            title: "Access Denied",
            text: "Your role does not have permission to open this page.",
        },
        404: {
            title: "Page Not Found",
            text: "The page you are looking for does not exist or was moved.",
        },
        429: {
            title: "Too Many Requests",
            text: "Please slow down and try again in a minute.",
        },
        503: {
            title: "Service Unavailable",
            text: "The system is temporarily unavailable. Please try again soon.",
        },
        500: {
            title: "Something Went Wrong",
            text: "An unexpected error occurred. Please try again.",
        },
    };

    $: message = messages[status] ?? messages[500];
</script>

<FullscreenToggle floating />

<svelte:head>
    <title>{message.title} | One Prompt Away</title>
</svelte:head>

<main
    class="flex min-h-screen items-center justify-center bg-slate-50 px-4 pb-8 pt-20 font-sans text-slate-900"
>
    <div
        class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm"
    >
        <BrandLogo className="mb-8 justify-center" />
        <p
            class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600"
        >
            Error {status}
        </p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">
            {message.title}
        </h1>
        <p class="mt-2 text-sm text-slate-500">{message.text}</p>
        <Link
            href="/dashboard"
            class="mt-6 inline-flex h-10 items-center justify-center rounded-md bg-slate-900 px-5 text-sm font-semibold text-white transition-colors hover:bg-slate-700"
            >Back To Dashboard</Link
        >
    </div>
</main>
