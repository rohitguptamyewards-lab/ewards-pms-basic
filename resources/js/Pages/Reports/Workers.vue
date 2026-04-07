<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    workers: { type: [Array, Object], default: () => [] },
});

/* ── helpers ── */
function hoursDisplay(h) {
    h = parseFloat(h) || 0;
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    if (hrs === 0 && mins === 0) return '0h';
    if (mins === 0) return `${hrs}h`;
    return `${hrs}h ${mins}m`;
}

/* ── filters ── */
const search = ref('');
const roleFilter = ref('');

const allRoles = computed(() => {
    const roles = new Set();
    (props.workers || []).forEach(w => { if (w.role) roles.add(w.role); });
    return [...roles].sort();
});

/* ── sorting ── */
const sortKey = ref('name');
const sortAsc = ref(true);

function toggleSort(key) {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = true;
    }
}

function sortIcon(key) {
    if (sortKey.value !== key) return '↕';
    return sortAsc.value ? '↑' : '↓';
}

/* ── filtered + sorted workers ── */
const filteredWorkers = computed(() => {
    let list = [...(props.workers || [])];

    if (search.value) {
        const q = search.value.toLowerCase();
        list = list.filter(w => w.name?.toLowerCase().includes(q) || w.email?.toLowerCase().includes(q));
    }
    if (roleFilter.value) {
        list = list.filter(w => w.role === roleFilter.value);
    }

    const key = sortKey.value;
    list.sort((a, b) => {
        let va = a[key], vb = b[key];
        if (typeof va === 'string') va = va.toLowerCase();
        if (typeof vb === 'string') vb = vb.toLowerCase();
        va = va ?? '';
        vb = vb ?? '';
        if (va < vb) return sortAsc.value ? -1 : 1;
        if (va > vb) return sortAsc.value ? 1 : -1;
        return 0;
    });

    return list;
});

/* ── summary stats ── */
const totalMembers = computed(() => (props.workers || []).length);
const activeMembers = computed(() => (props.workers || []).filter(w => w.is_active).length);

const totalHoursAll = computed(() =>
    (props.workers || []).reduce((sum, w) => sum + (parseFloat(w.total_hours) || 0), 0)
);
const totalWeekHours = computed(() =>
    (props.workers || []).reduce((sum, w) => sum + (parseFloat(w.week_hours) || 0), 0)
);
const avgHoursPerMember = computed(() => {
    if (!totalMembers.value) return 0;
    return totalHoursAll.value / totalMembers.value;
});
</script>

<template>
    <Head title="Workers Report" />

    <div class="space-y-6">
        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h1 class="text-xl font-bold text-gray-900">Workers Report</h1>
            <span class="text-sm text-gray-500">{{ filteredWorkers.length }} of {{ totalMembers }} members shown</span>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Members</p>
                <p class="mt-2 text-2xl font-bold text-[#4e1a77]">{{ totalMembers }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Active Members</p>
                <p class="mt-2 text-2xl font-bold text-green-600">{{ activeMembers }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Hours (All Time)</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ hoursDisplay(totalHoursAll) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Avg Hours / Member</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ hoursDisplay(avgHoursPerMember) }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email..."
                    class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 text-sm text-gray-700 placeholder-gray-400 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
                />
            </div>
            <select
                v-model="roleFilter"
                class="rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
            >
                <option value="">All Roles</option>
                <option v-for="r in allRoles" :key="r" :value="r" class="capitalize">{{ r }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th @click="toggleSort('name')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Name <span class="ml-1 text-[10px]">{{ sortIcon('name') }}</span>
                        </th>
                        <th @click="toggleSort('role')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Role <span class="ml-1 text-[10px]">{{ sortIcon('role') }}</span>
                        </th>
                        <th @click="toggleSort('project_count')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Projects <span class="ml-1 text-[10px]">{{ sortIcon('project_count') }}</span>
                        </th>
                        <th @click="toggleSort('week_hours')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            This Week <span class="ml-1 text-[10px]">{{ sortIcon('week_hours') }}</span>
                        </th>
                        <th @click="toggleSort('total_hours')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Total Hours <span class="ml-1 text-[10px]">{{ sortIcon('total_hours') }}</span>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="w in filteredWorkers" :key="w.id" class="hover:bg-[#f5f0ff]/30 transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-medium text-gray-900">{{ w.name }}</p>
                            <p class="text-xs text-gray-400">{{ w.email }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full bg-[#f5f0ff] px-2.5 py-0.5 text-xs font-medium text-[#4e1a77] capitalize">
                                {{ w.role }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-sm font-medium text-gray-900">{{ w.project_count ?? 0 }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-sm font-medium text-[#4e1a77]">{{ hoursDisplay(w.week_hours) }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-900">{{ hoursDisplay(w.total_hours) }}</td>
                        <td class="px-5 py-3.5">
                            <span v-if="w.is_active" class="inline-flex items-center gap-1 text-xs font-medium text-green-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                Active
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-gray-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>
                                Inactive
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!filteredWorkers.length" class="px-5 py-12 text-center text-sm text-gray-400">
                No workers match your filters
            </p>
        </div>
    </div>
</template>
