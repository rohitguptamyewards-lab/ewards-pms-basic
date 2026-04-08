<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PriorityBadge from '@/Components/PriorityBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    projects: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    title: { type: String, default: 'Projects' },
    basePath: { type: String, default: '/projects' },
    boardPath: { type: String, default: '/projects/board' },
    showBoardToggle: { type: Boolean, default: true },
    showCreateButton: { type: Boolean, default: true },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const canCreate = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));
const isManagerOrAnalyst = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));
const canEditPriority = computed(() => ['manager', 'analyst_head', 'analyst', 'senior_developer'].includes(role.value));

// ── Stage Category Filter ──────────────────────────────
const stageCategoryFilter = ref('');

const stageCategories = {
    documentation: [
        'doc_yet_to_start','gpt_chat_wip','doc_wip','doc_under_review','doc_changes_required',
        'figma_yet_to_start','figma_design_wip','design_under_review',
    ],
    development: [
        'dev_yet_to_start','development_wip','dev_testing_wip','dev_changes_required',
        'bug_fixing_wip','bugs_fixed','vibe_code_shared','rnd',
    ],
    testing: [
        'testing_yet_to_start','testing_wip','ready_for_internal','testing_wip_internal',
        'bugs_reported_test_internal','bugs_reported_testing','bugs_reported_internal',
    ],
    live: [
        'live','ready_to_go_for_live','live_testing_yet_to_start','live_testing_wip',
        'bugs_reported_live','yet_to_announce_on_group','yet_to_put_on_cron','ticket_raised_for_revenue',
    ],
};

const filteredProjects = computed(() => {
    if (!stageCategoryFilter.value || !props.projects?.data) return props.projects?.data || [];
    const stages = stageCategories[stageCategoryFilter.value] || [];
    return props.projects.data.filter(p => stages.includes(p.current_stage));
});

const categoryCounts = computed(() => {
    const data = props.projects?.data || [];
    const counts = {};
    for (const [cat, stages] of Object.entries(stageCategories)) {
        counts[cat] = data.filter(p => stages.includes(p.current_stage)).length;
    }
    return counts;
});

// Filters
const filterForm = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    priority: props.filters?.priority || '',
    work_type: props.filters?.work_type || '',
});

function applyFilters() {
    const params = {};
    Object.entries(filterForm.value).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get(props.basePath, params, { preserveState: true, replace: true });
}

function clearFilters() {
    filterForm.value = { search: '', status: '', priority: '', work_type: '' };
    stageCategoryFilter.value = '';
    router.get(props.basePath, {}, { preserveState: true, replace: true });
}

// Team members for inline assignment (loaded on demand)
const teamMembers = ref([]);
const teamLoaded = ref(false);

async function loadTeamMembers() {
    if (teamLoaded.value) return;
    const { data } = await axios.get('/api/v1/team-members');
    teamMembers.value = data;
    teamLoaded.value = true;
}

// Inline person change
const editingCell = ref(null); // e.g. '3-analyst_id'
const editingPriorityProjectId = ref(null);

function startEditing(projectId, field) {
    loadTeamMembers();
    editingCell.value = `${projectId}-${field}`;
}

async function changeAssignment(project, field, newValue) {
    editingCell.value = null;
    const val = newValue ? parseInt(newValue) : null;
    try {
        await axios.put(`/api/v1/projects/${project.id}`, { [field]: val });
        router.reload({ only: ['projects'] });
    } catch (e) {
        console.error('Assignment update failed', e);
    }
}

function isEditing(projectId, field) {
    return editingCell.value === `${projectId}-${field}`;
}

function isEditingPriority(projectId) {
    return editingPriorityProjectId.value === projectId;
}

function startEditingPriority(projectId) {
    if (!canEditPriority.value) return;
    editingPriorityProjectId.value = projectId;
}

async function changePriority(project, newPriority) {
    editingPriorityProjectId.value = null;
    if (!newPriority || newPriority === project.priority) return;

    try {
        await axios.put(`/api/v1/projects/${project.id}`, { priority: newPriority });
        router.reload({ only: ['projects'] });
    } catch (e) {
        console.error('Priority update failed', e);
    }
}

// ── Inline Stage Change ────────────────────────────────
const editingStage = ref(null); // project id

const stageOptions = [
    'doc_yet_to_start','gpt_chat_wip','doc_wip','figma_yet_to_start','figma_design_wip',
    'doc_under_review','doc_changes_required','dev_yet_to_start','development_wip',
    'testing_yet_to_start','testing_wip','dev_testing_wip','ready_for_internal',
    'testing_wip_internal','bugs_reported_test_internal','bugs_reported_testing',
    'bugs_reported_internal','live_testing_yet_to_start','ready_to_go_for_live',
    'live_testing_wip','bugs_reported_live','bug_fixing_wip','bugs_fixed',
    'dev_changes_required','live','need_to_discuss','on_hold','scrapped','rnd',
    'yet_to_announce_on_group','yet_to_put_on_cron','ticket_raised_for_revenue',
    'design_under_review','vibe_code_shared',
];

function stageLabelFor(val) {
    return val?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || val;
}

async function changeStage(project, newStage) {
    editingStage.value = null;
    if (!newStage || newStage === project.current_stage) return;
    try {
        await axios.put(`/api/v1/projects/${project.id}/stage`, { stage_name: newStage });
        router.reload({ only: ['projects'] });
    } catch (e) {
        console.error('Stage update failed', e);
    }
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
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

const statusOptions = [
    { value: '', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'on_hold', label: 'On Hold' },
];

const priorityOptions = [
    { value: '', label: 'All Priority' },
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
    { value: 'critical', label: 'Critical' },
];

const workTypeOptions = [
    { value: '', label: 'All Work Types' },
    ...Object.entries(workTypeLabels).map(([v, l]) => ({ value: v, label: l })),
];
</script>

<template>
    <Head :title="title" />

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-gray-900">{{ title }}</h1>
                <div v-if="showBoardToggle" class="flex rounded-lg border border-gray-300 overflow-hidden">
                    <span class="bg-[#4e1a77] px-3 py-1.5 text-xs font-medium text-white">Table</span>
                    <Link :href="boardPath" class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Board</Link>
                </div>
            </div>
            <Link
                v-if="canCreate && showCreateButton"
                href="/projects/create"
                class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] transition-colors"
            >
                + New Project
            </Link>
        </div>

        <!-- Stage Category Tabs -->
        <div class="flex gap-2 flex-wrap">
            <button
                @click="stageCategoryFilter = ''"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="!stageCategoryFilter ? 'bg-[#4e1a77] text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                All <span class="ml-1 text-xs opacity-75">({{ projects?.data?.length || 0 }})</span>
            </button>
            <button
                @click="stageCategoryFilter = 'documentation'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="stageCategoryFilter === 'documentation' ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                Documentation <span class="ml-1 text-xs opacity-75">({{ categoryCounts.documentation }})</span>
            </button>
            <button
                @click="stageCategoryFilter = 'development'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="stageCategoryFilter === 'development' ? 'bg-green-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                Development <span class="ml-1 text-xs opacity-75">({{ categoryCounts.development }})</span>
            </button>
            <button
                @click="stageCategoryFilter = 'testing'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="stageCategoryFilter === 'testing' ? 'bg-orange-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                Testing <span class="ml-1 text-xs opacity-75">({{ categoryCounts.testing }})</span>
            </button>
            <button
                @click="stageCategoryFilter = 'live'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="stageCategoryFilter === 'live' ? 'bg-emerald-700 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                Live <span class="ml-1 text-xs opacity-75">({{ categoryCounts.live }})</span>
            </button>
        </div>

        <!-- Filters -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Search</label>
                    <input
                        v-model="filterForm.search"
                        placeholder="Search projects..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Status</label>
                    <select v-model="filterForm.status" @change="applyFilters" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Priority</label>
                    <select v-model="filterForm.priority" @change="applyFilters" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option v-for="o in priorityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Work Type</label>
                    <select v-model="filterForm.work_type" @change="applyFilters" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option v-for="o in workTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <button @click="applyFilters" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Filter</button>
                <button @click="clearFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</button>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Stage</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Work Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Analyst</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Developer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Analyst Testing</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Priority</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Planners</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="p in filteredProjects"
                        :key="p.id"
                        class="group hover:bg-[#f5f0ff]/20 transition-colors"
                    >
                        <!-- Project Name -->
                        <td class="px-4 py-3">
                            <Link :href="`/projects/${p.id}`" class="text-sm font-medium text-[#4e1a77] group-hover:underline">
                                {{ p.name }}
                            </Link>
                            <p class="text-xs text-gray-400 mt-0.5">{{ p.owner_name }}</p>
                        </td>

                        <!-- Stage (inline editable) -->
                        <td class="px-4 py-3">
                            <template v-if="editingStage === p.id">
                                <select
                                    :value="p.current_stage || ''"
                                    @change="changeStage(p, $event.target.value)"
                                    @blur="editingStage = null"
                                    class="rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77] w-full max-w-[180px]"
                                    autofocus
                                >
                                    <option value="">No stage</option>
                                    <option v-for="s in stageOptions" :key="s" :value="s">{{ stageLabelFor(s) }}</option>
                                </select>
                            </template>
                            <template v-else>
                                <span
                                    class="cursor-pointer hover:opacity-75"
                                    @click="editingStage = p.id"
                                    title="Click to change stage"
                                >
                                    <StageBadge v-if="p.current_stage" :stage="p.current_stage" />
                                    <span v-else class="text-xs text-gray-400 hover:text-[#4e1a77]">Set stage</span>
                                </span>
                            </template>
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-3">
                            <StatusBadge :status="p.status" type="project" />
                        </td>

                        <!-- Work Type -->
                        <td class="px-4 py-3">
                            <span v-if="p.work_type" :class="workTypeColors[p.work_type] || 'bg-gray-100 text-gray-600'" class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase">
                                {{ workTypeLabels[p.work_type] || p.work_type }}
                            </span>
                            <span v-else class="text-xs text-gray-400">-</span>
                        </td>

                        <!-- Analyst (inline editable) -->
                        <td class="px-4 py-3">
                            <template v-if="isManagerOrAnalyst && isEditing(p.id, 'analyst_id')">
                                <select
                                    :value="p.analyst_id"
                                    @change="changeAssignment(p, 'analyst_id', $event.target.value)"
                                    @blur="editingCell = null"
                                    class="rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77] w-full max-w-[140px]"
                                    autofocus
                                >
                                    <option value="">Unassigned</option>
                                    <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </template>
                            <template v-else>
                                <span
                                    class="text-sm text-gray-600"
                                    :class="{ 'cursor-pointer hover:text-[#4e1a77] hover:underline': isManagerOrAnalyst }"
                                    @click="isManagerOrAnalyst && startEditing(p.id, 'analyst_id')"
                                    :title="isManagerOrAnalyst ? 'Click to change' : ''"
                                >
                                    {{ p.analyst_name || '-' }}
                                </span>
                            </template>
                        </td>

                        <!-- Developer (inline editable) -->
                        <td class="px-4 py-3">
                            <template v-if="isManagerOrAnalyst && isEditing(p.id, 'developer_id')">
                                <select
                                    :value="p.developer_id"
                                    @change="changeAssignment(p, 'developer_id', $event.target.value)"
                                    @blur="editingCell = null"
                                    class="rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77] w-full max-w-[140px]"
                                    autofocus
                                >
                                    <option value="">Unassigned</option>
                                    <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </template>
                            <template v-else>
                                <span
                                    class="text-sm text-gray-600"
                                    :class="{ 'cursor-pointer hover:text-[#4e1a77] hover:underline': isManagerOrAnalyst }"
                                    @click="isManagerOrAnalyst && startEditing(p.id, 'developer_id')"
                                    :title="isManagerOrAnalyst ? 'Click to change' : ''"
                                >
                                    {{ p.developer_name || '-' }}
                                </span>
                            </template>
                        </td>

                        <!-- Analyst Testing (inline editable) -->
                        <td class="px-4 py-3">
                            <template v-if="isManagerOrAnalyst && isEditing(p.id, 'analyst_testing_id')">
                                <select
                                    :value="p.analyst_testing_id"
                                    @change="changeAssignment(p, 'analyst_testing_id', $event.target.value)"
                                    @blur="editingCell = null"
                                    class="rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77] w-full max-w-[140px]"
                                    autofocus
                                >
                                    <option value="">Unassigned</option>
                                    <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </template>
                            <template v-else>
                                <span
                                    class="text-sm text-gray-600"
                                    :class="{ 'cursor-pointer hover:text-[#4e1a77] hover:underline': isManagerOrAnalyst }"
                                    @click="isManagerOrAnalyst && startEditing(p.id, 'analyst_testing_id')"
                                    :title="isManagerOrAnalyst ? 'Click to change' : ''"
                                >
                                    {{ p.analyst_testing_name || '-' }}
                                </span>
                            </template>
                        </td>

                        <!-- Priority -->
                        <td class="px-4 py-3">
                            <template v-if="isEditingPriority(p.id)">
                                <select
                                    :value="p.priority"
                                    @change="changePriority(p, $event.target.value)"
                                    @blur="editingPriorityProjectId = null"
                                    class="rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77]"
                                    autofocus
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </template>
                            <template v-else>
                                <div
                                    class="inline-block"
                                    :class="{ 'cursor-pointer': canEditPriority }"
                                    @click="startEditingPriority(p.id)"
                                >
                                    <PriorityBadge :priority="p.priority" />
                                </div>
                            </template>
                        </td>

                        <!-- Planners -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                    <div
                                        class="bg-[#4e1a77] h-1.5 rounded-full transition-all"
                                        :style="{ width: (p.planner_count ? (p.planner_done_count / p.planner_count * 100) : 0) + '%' }"
                                    />
                                </div>
                                <span class="text-xs text-gray-600 font-medium">{{ p.planner_done_count || 0 }}/{{ p.planner_count || 0 }}</span>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <!-- Links -->
                                <a v-if="p.document_link" :href="p.document_link" target="_blank" title="Document" class="text-blue-500 hover:text-blue-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                </a>
                                <a v-if="p.ai_chat_link" :href="p.ai_chat_link" target="_blank" title="AI Chat" class="text-purple-500 hover:text-purple-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                </a>
                                <!-- Replicate Button -->
                                <Link
                                    v-if="canCreate"
                                    :href="`/projects/${p.id}/replicate`"
                                    title="Replicate project"
                                    class="text-gray-400 hover:text-[#4e1a77] transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                    </svg>
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!filteredProjects?.length" class="px-5 py-12 text-center text-sm text-gray-400">
                <template v-if="stageCategoryFilter">
                    No projects in "{{ stageCategoryFilter }}" category.
                    <button @click="stageCategoryFilter = ''" class="text-[#4e1a77] underline ml-1">Show all</button>
                </template>
                <template v-else>
                    No projects found.
                    <Link v-if="canCreate && showCreateButton" href="/projects/create" class="text-[#4e1a77] underline ml-1">Create one</Link>
                </template>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="projects.links?.length > 3" class="flex justify-center gap-1">
            <Link
                v-for="link in projects.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded px-3 py-1 text-xs"
                :class="link.active ? 'bg-[#4e1a77] text-white' : link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300'"
                v-html="link.label"
                preserve-scroll
            />
        </div>
    </div>
</template>
