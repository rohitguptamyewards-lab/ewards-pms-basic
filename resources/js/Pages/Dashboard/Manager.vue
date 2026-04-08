<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatsCard from '@/Components/StatsCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PriorityBadge from '@/Components/PriorityBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({ layout: AppLayout });

const props = defineProps({
    totalProjects: Number,
    activeProjects: Number,
    onHoldProjects: Number,
    activeBlockers: { type: [Array, Object], default: () => [] },
    overduePlanners: { type: [Array, Object], default: () => [] },
    recentProjects: { type: [Array, Object], default: () => [] },
});
</script>

<template>
    <Head title="Manager Dashboard" />

    <div class="space-y-6">
        <h1 class="text-xl font-bold text-gray-900">Manager Dashboard</h1>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <StatsCard label="Total Projects" :value="totalProjects" color="blue" />
            <StatsCard label="Active" :value="activeProjects" color="green" />
            <StatsCard label="On Hold" :value="onHoldProjects" color="yellow" />
            <StatsCard label="Active Blockers" :value="activeBlockers?.length || 0" color="red" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Recent Projects -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-gray-900">Recent Projects</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <Link
                        v-for="p in recentProjects"
                        :key="p.id"
                        :href="`/projects/${p.id}`"
                        class="flex items-center justify-between px-5 py-3 hover:bg-[#f5f0ff]/30 transition-colors"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ p.name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ p.owner_name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <StageBadge v-if="p.current_stage" :stage="p.current_stage" />
                            <PriorityBadge :priority="p.priority" />
                        </div>
                    </Link>
                    <p v-if="!recentProjects?.length" class="px-5 py-8 text-center text-sm text-gray-400">No projects yet</p>
                </div>
            </div>

            <!-- Active Blockers -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5 flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-red-100 text-xs text-red-600">!</span>
                    <h2 class="text-sm font-semibold text-gray-900">Active Blockers</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="b in activeBlockers" :key="b.id" class="px-5 py-3">
                        <p class="text-sm text-gray-900">{{ b.description }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ b.project_name }} &middot; {{ b.creator_name }}</p>
                    </div>
                    <div v-if="!activeBlockers?.length" class="px-5 py-8 text-center">
                        <p class="text-sm text-gray-400">No active blockers</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue Planners -->
        <div v-if="overduePlanners?.length" class="rounded-xl border border-orange-200 bg-white shadow-sm">
            <div class="border-b border-orange-100 px-5 py-3.5 bg-orange-50/50 rounded-t-xl">
                <h2 class="text-sm font-semibold text-orange-700">Overdue Planner Items</h2>
            </div>
            <div class="divide-y divide-gray-100">
                <div v-for="p in overduePlanners" :key="p.id" class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ p.title }}</p>
                        <p class="text-xs text-gray-400">{{ p.project_name }} &middot; {{ p.assignee_name }}</p>
                    </div>
                    <span class="text-xs font-medium text-red-600">Due: {{ p.due_date }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
