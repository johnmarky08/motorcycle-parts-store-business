import { cubicOut } from "svelte/easing";

/** Modal panel: fades in while growing slightly from 96%, and does the reverse on close. */
export function pop(node, { duration = 180, start = 0.96 } = {}) {
    const style = getComputedStyle(node);
    const transform = style.transform === "none" ? "" : style.transform;

    return {
        duration,
        easing: cubicOut,
        css: (t, u) =>
            `transform: ${transform} scale(${start + (1 - start) * t}) translateY(${u * 8}px); opacity: ${t}`,
    };
}
