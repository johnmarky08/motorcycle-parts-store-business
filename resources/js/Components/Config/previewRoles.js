export const roleDefinitions = {
    admin: {
        label: "Admin",
        dashboardDescription: "Here’s an overview of your store’s performance today.",
        navigation: [
            { label: "Dashboard", icon: "grid", href: "/dashboard" },
            { label: "User Management", icon: "users", href: "/user-management" },
            { label: "Prediction", icon: "chart", href: "/prediction" },
            { label: "Inventory Management", icon: "inventory", href: "/inventory" },
            { label: "Sales Management", icon: "sales", href: "/sales" },
            { label: "Knowledge Analysis", icon: "analysis", href: "/knowledge-analysis" },
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
