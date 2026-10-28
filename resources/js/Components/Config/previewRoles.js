export const PREVIEW_ROLE_STORAGE_KEY = "motorcycle-parts-preview-role";
export const PREVIEW_ROLE_CHANGE_EVENT = "preview-role-change";

export const previewRoles = {
    admin: {
        label: "Admin",
        dashboardDescription: "Here’s an overview of your store’s performance today.",
        navigation: [
            { label: "Dashboard", icon: "grid", href: "/dashboard" },
            { label: "User Management", icon: "users", href: "/user-management" },
            { label: "Prediction", icon: "chart", href: "/prediction" },
        ],
    },
    instructor: {
        label: "Instructor",
        dashboardDescription: "Review dataset readiness, analysis activity and model evaluation previews.",
        navigation: [
            { label: "Dashboard", icon: "grid", href: "/dashboard" },
            { label: "Knowledge Analysis", icon: "analysis", href: "/knowledge-analysis" },
        ],
    },
    inventory_clerk: {
        label: "Inventory Clerk",
        dashboardDescription: "Monitor stock levels and recent inventory activity.",
        navigation: [
            { label: "Dashboard", icon: "grid", href: "/dashboard" },
            { label: "Inventory Management", icon: "inventory", href: "/inventory" },
        ],
    },
    sales_personnel: {
        label: "Sales Personnel",
        dashboardDescription: "Track sales performance and recent transaction activity.",
        navigation: [
            { label: "Dashboard", icon: "grid", href: "/dashboard" },
            { label: "Sales Management", icon: "sales", href: "/sales" },
        ],
    },
};

export function getPreviewRole() {
    if (typeof window === "undefined") return "admin";

    const storedRole = window.localStorage.getItem(PREVIEW_ROLE_STORAGE_KEY);
    return storedRole && previewRoles[storedRole] ? storedRole : "admin";
}

export function setPreviewRole(role) {
    const nextRole = previewRoles[role] ? role : "admin";

    if (typeof window !== "undefined") {
        window.localStorage.setItem(PREVIEW_ROLE_STORAGE_KEY, nextRole);
        window.dispatchEvent(new CustomEvent(PREVIEW_ROLE_CHANGE_EVENT, { detail: nextRole }));
    }

    return nextRole;
}
