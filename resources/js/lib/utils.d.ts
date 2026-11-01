import type { HTMLAttributes } from "svelte/elements";

export declare function cn(...inputs: unknown[]): string;

export type WithElementRef<T extends HTMLAttributes<HTMLElement>> = T & {
    ref?: HTMLElement | null;
};

export type WithoutChild<T> = Omit<T, "child">;
export type WithoutChildrenOrChild<T> = Omit<T, "children" | "child">;
