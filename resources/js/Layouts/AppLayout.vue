<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const url = computed(() => page.url);
const user = computed(() => page.props.auth?.user);
const role = computed(() => user.value?.role);
const flash = computed(() => page.props.flash);

const sidebarOpen = ref(false);

function isActive(href) {
    if (!href) return false;
    if (href === '/') return url.value === '/';
    if (href === '/projects') return url.value === '/projects' || (url.value.startsWith('/projects/') && !url.value.startsWith('/projects/board'));
    if (href === '/projects/board') return url.value.startsWith('/projects/board');
    return url.value.startsWith(href);
}

function logout() {
    router.post('/logout');
}

const navItems = computed(() => {
    const items = [
        { label: 'Dashboard', href: '/', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1' },
        { label: 'Projects', href: '/projects', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
        { label: 'Board', href: '/projects/board', icon: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2' },
        { label: 'Work Logs', href: '/work-logs', icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' },
    ];

    if (['manager', 'analyst_head', 'analyst'].includes(role.value)) {
        items.push(
            { label: 'TEAM', header: true },
            { label: 'Team Members', href: '/team-members', icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
        );
    }

    if (['manager', 'analyst_head', 'analyst', 'senior_developer'].includes(role.value)) {
        items.push(
            { label: 'REPORTS', header: true },
            { label: 'Report Dashboard', href: '/reports/dashboard', icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
        );

        if (['manager', 'analyst_head', 'analyst'].includes(role.value)) {
            items.push(
                { label: 'Projects Report', href: '/reports/projects', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            );
        }
    }

    if (['manager', 'analyst_head', 'senior_developer'].includes(role.value)) {
        if (!items.find(i => i.label === 'REPORTS')) {
            items.push({ label: 'REPORTS', header: true });
        }
        items.push(
            { label: 'Workers Report', href: '/reports/workers', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
        );
    }

    return items;
});
</script>

<template>
    <Head :title="$page.props.title || ''" />

    <div class="flex h-screen overflow-hidden bg-gray-50">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 flex w-56 flex-col bg-[#2c0f47] text-white transition-transform duration-200 lg:relative lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Logo -->
            <div class="flex h-14 items-center gap-2 border-b border-white/10 px-4">
                <img src="/ewards-logo-white.svg" alt="eWards" class="h-6" />
            </div>
            <p class="px-4 pt-1.5 pb-2 text-[9px] font-semibold uppercase tracking-widest text-white/40">PMS Dashboard</p>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-2 py-1 space-y-0.5">
                <template v-for="item in navItems" :key="item.label">
                    <div v-if="item.header" class="px-3 pt-5 pb-1 text-[10px] font-bold uppercase tracking-widest text-white/40">
                        {{ item.label }}
                    </div>
                    <Link
                        v-else
                        :href="item.href"
                        @click="sidebarOpen = false"
                        class="relative flex items-center gap-2.5 rounded-r-lg px-3 py-2 text-sm transition-colors"
                        :class="isActive(item.href)
                            ? 'bg-white/10 text-white font-semibold border-l-[3px] border-[#f5941e]'
                            : 'text-white/70 hover:bg-white/5 hover:text-white border-l-[3px] border-transparent'"
                    >
                        <svg class="h-4.5 w-4.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        {{ item.label }}
                    </Link>
                </template>
            </nav>

            <!-- User -->
            <div class="border-t border-white/10 px-3 py-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#f5941e] text-xs font-bold text-white">
                        {{ user?.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-xs font-medium">{{ user?.name }}</p>
                        <p class="truncate text-[10px] text-white/50 capitalize">{{ role }}</p>
                    </div>
                    <button @click="logout" class="rounded p-1 text-white/40 hover:bg-white/10 hover:text-white" title="Logout">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Mobile overlay -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- Main content -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Top bar -->
            <header class="flex h-14 items-center gap-3 border-b border-gray-200 bg-white px-4 shadow-sm">
                <button @click="sidebarOpen = !sidebarOpen" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <div class="flex-1" />
                <!-- User info in top bar -->
                <div class="flex items-center gap-3">
                    <span class="hidden sm:inline text-xs text-gray-400">{{ user?.email }}</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#4e1a77] text-xs font-bold text-white">
                        {{ user?.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <span class="hidden sm:inline text-sm font-medium text-gray-700">{{ user?.name }}</span>
                </div>
            </header>

            <!-- Flash Messages -->
            <div v-if="flash?.success" class="mx-4 mt-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-700">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mx-4 mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">
                {{ flash.error }}
            </div>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
