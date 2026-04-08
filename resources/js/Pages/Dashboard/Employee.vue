<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({ layout: AppLayout });

const props = defineProps({
    myProjects: { type: [Array, Object], default: () => [] },
    myPlanners: { type: [Array, Object], default: () => [] },
    myBlockers: { type: [Array, Object], default: () => [] },
});

function isOverdue(date) {
    if (!date) return false;
    return new Date(date) < new Date();
}

const workTypeLabels = {
    frontend_work: 'Frontend',
    backend_work: 'Backend',
    figma: 'Figma',
    trigger_part: 'Trigger',
    data_mapping: 'Data Mapping',
    full_stack: 'Full Stack',
    other: 'Other',
};

const workTypeColors = {
    frontend_work: 'bg-blue-100 text-blue-700',
    backend_work: 'bg-green-100 text-green-700',
    figma: 'bg-pink-100 text-pink-700',
    trigger_part: 'bg-orange-100 text-orange-700',
    data_mapping: 'bg-purple-100 text-purple-700',
    full_stack: 'bg-indigo-100 text-indigo-700',
    other: 'bg-gray-100 text-gray-600',
};

const roleLabels = {
    developer: 'Developer',
    analyst: 'Analyst',
    analyst_testing: 'Analyst Testing',
    owner: 'Owner',
    contributor: 'Contributor',
};
</script>

<template>
    <Head title="My Dashboard" />

    <div class="space-y-6">
        <h1 class="text-xl font-bold text-gray-900">My Dashboard</h1>

        <!-- My Projects -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-sm font-semibold text-gray-900">My Projects</h2>
            </div>
            <div class="divide-y divide-gray-100">
                <Link
                    v-for="p in myProjects"
                    :key="p.id"
                    :href="`/projects/${p.id}`"
                    class="flex items-center justify-between px-5 py-3 hover:bg-[#f5f0ff]/30 transition-colors"
                >
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ p.name }}</p>
                            <span
                                v-if="p.work_type"
                                :class="workTypeColors[p.work_type] || 'bg-gray-100 text-gray-600'"
                                class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                            >
                                {{ workTypeLabels[p.work_type] || p.work_type }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span
                                class="text-xs font-medium capitalize rounded-full px-2 py-0.5"
                                :class="{
                                    'bg-indigo-100 text-indigo-700': p.my_role === 'developer',
                                    'bg-amber-100 text-amber-700': p.my_role === 'analyst',
                                    'bg-teal-100 text-teal-700': p.my_role === 'analyst_testing',
                                    'bg-yellow-100 text-yellow-700': p.my_role === 'owner',
                                    'bg-gray-100 text-gray-600': !['developer','analyst','analyst_testing','owner'].includes(p.my_role),
                                }"
                            >
                                {{ roleLabels[p.my_role] || p.my_role }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-3">
                        <StageBadge v-if="p.current_stage" :stage="p.current_stage" />
                        <StatusBadge :status="p.status" type="project" />
                    </div>
                </Link>
                <p v-if="!myProjects?.length" class="px-5 py-8 text-center text-sm text-gray-400">No projects assigned</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- My Planners -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-gray-900">My Pending Tasks</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="p in myPlanners" :key="p.id" class="px-5 py-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">{{ p.title }}</p>
                            <StatusBadge :status="p.status" type="planner" />
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ p.project_name }}
                            <span v-if="p.due_date" :class="isOverdue(p.due_date) ? 'text-red-500 font-medium' : ''">
                                &middot; Due: {{ p.due_date }}
                            </span>
                        </p>
                    </div>
                    <p v-if="!myPlanners?.length" class="px-5 py-8 text-center text-sm text-gray-400">All tasks complete</p>
                </div>
            </div>

            <!-- My Blockers -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-gray-900">My Active Blockers</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="b in myBlockers" :key="b.id" class="px-5 py-3">
                        <p class="text-sm text-gray-900">{{ b.description }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ b.project_name }}</p>
                    </div>
                    <p v-if="!myBlockers?.length" class="px-5 py-8 text-center text-sm text-gray-400">No blockers</p>
                </div>
            </div>
        </div>
    </div>
</template>
