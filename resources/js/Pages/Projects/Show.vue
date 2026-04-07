<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PriorityBadge from '@/Components/PriorityBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    project: Object,
    planners: { type: Array, default: () => [] },
    workers: { type: Array, default: () => [] },
    updates: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    tickets: { type: Array, default: () => [] },
    stageHistory: { type: Array, default: () => [] },
    transfers: { type: Array, default: () => [] },
    teamMembers: { type: [Array, Object], default: () => [] },
    attachments: { type: Array, default: () => [] },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const isManagerOrAnalyst = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));

const activeTab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'planner', label: 'Planner' },
    { key: 'report', label: 'Report' },
    { key: 'updates', label: 'Updates' },
    { key: 'workers', label: 'Workers' },
    { key: 'attachments', label: 'Attachments' },
    { key: 'tickets', label: 'Tickets' },
    { key: 'blockers', label: 'Blockers' },
];

// Local reactive state
const localPlanners = ref([...(props.planners || [])]);
const localUpdates = ref([...(props.updates || [])]);
const localWorkers = ref([...(props.workers || [])]);
const localBlockers = ref([...(props.blockers || [])]);
const localTickets = ref([...(props.tickets || [])]);
const localStageHistory = ref([...(props.stageHistory || [])]);

// Work type display helpers
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

const taskTypeLabels = {
    new_project: 'New Project',
    addition_on_existing: 'Addition on Existing',
    bug_fix: 'Bug Fix',
    data_mapping: 'Data Mapping',
    integration: 'Integration',
    other: 'Other',
};

// ── Report Tab Data ────────────────────────────────────
const reportData = ref(null);
const reportLoading = ref(false);
const expandedEmployee = ref(null);

async function loadReportData() {
    if (reportData.value) return;
    reportLoading.value = true;
    try {
        const { data } = await axios.get(`/api/v1/reports/projects/${props.project.id}/worklogs`);
        reportData.value = data;
    } catch (e) {
        console.error('Failed to load report data', e);
    }
    reportLoading.value = false;
}

watch(activeTab, (val) => {
    if (val === 'report') loadReportData();
});

const totalProjectHours = computed(() => {
    if (!reportData.value?.employeeSummary) return 0;
    return reportData.value.employeeSummary.reduce((sum, e) => sum + parseFloat(e.total_hours || 0), 0);
});

const totalLogEntries = computed(() => {
    if (!reportData.value?.employeeSummary) return 0;
    return reportData.value.employeeSummary.reduce((sum, e) => sum + parseInt(e.log_count || 0), 0);
});

function getEmployeeLogs(userId) {
    if (!reportData.value?.worklogs) return [];
    return reportData.value.worklogs.filter(w => w.user_id === userId);
}

function toggleEmployee(userId) {
    expandedEmployee.value = expandedEmployee.value === userId ? null : userId;
}

// ── Inline Assignment Change ───────────────────────────
const editingField = ref(null);

function startEditField(field) {
    if (isManagerOrAnalyst.value) editingField.value = field;
}

async function updateAssignment(field, value) {
    editingField.value = null;
    try {
        await axios.put(`/api/v1/projects/${props.project.id}`, { [field]: value ? parseInt(value) : null });
        // Update local display
        window.location.reload();
    } catch (e) {
        console.error('Failed to update assignment', e);
    }
}

// ── Planner ─────────────────────────────────────────────
const plannerForm = ref({ title: '', description: '', assigned_to: '', due_date: '', milestone_flag: false });

async function addPlanner() {
    if (!plannerForm.value.title) return;
    const { data } = await axios.post('/api/v1/planners', {
        project_id: props.project.id,
        ...plannerForm.value,
        assigned_to: plannerForm.value.assigned_to ? parseInt(plannerForm.value.assigned_to) : null,
    });
    localPlanners.value.push(data);
    plannerForm.value = { title: '', description: '', assigned_to: '', due_date: '', milestone_flag: false };
}

async function updatePlannerStatus(planner, status) {
    await axios.put(`/api/v1/planners/${planner.id}`, { status });
    planner.status = status;
}

async function deletePlanner(id) {
    await axios.delete(`/api/v1/planners/${id}`);
    localPlanners.value = localPlanners.value.filter(p => p.id !== id);
}

// ── Updates ─────────────────────────────────────────────
const updateForm = ref({ content: '', source: 'manual' });

async function addUpdate() {
    if (!updateForm.value.content) return;
    const { data } = await axios.post(`/api/v1/projects/${props.project.id}/updates`, updateForm.value);
    localUpdates.value.unshift(data);
    updateForm.value = { content: '', source: 'manual' };
}

// ── Workers ─────────────────────────────────────────────
const workerForm = ref({ user_id: '' });

async function addContributor() {
    if (!workerForm.value.user_id) return;
    await axios.post(`/api/v1/projects/${props.project.id}/workers/contributor`, {
        user_id: parseInt(workerForm.value.user_id),
    });
    const { data } = await axios.get(`/api/v1/projects/${props.project.id}/workers`);
    localWorkers.value = data;
    workerForm.value = { user_id: '' };
}

async function removeWorker(userId) {
    await axios.delete(`/api/v1/projects/${props.project.id}/workers/${userId}`);
    localWorkers.value = localWorkers.value.filter(w => w.user_id !== userId);
}

// ── Blockers ────────────────────────────────────────────
const blockerForm = ref({ description: '' });

async function addBlocker() {
    if (!blockerForm.value.description) return;
    const { data } = await axios.post(`/api/v1/projects/${props.project.id}/blockers`, blockerForm.value);
    localBlockers.value.unshift(data);
    blockerForm.value = { description: '' };
}

async function resolveBlocker(id) {
    await axios.put(`/api/v1/blockers/${id}/resolve`);
    const b = localBlockers.value.find(x => x.id === id);
    if (b) b.status = 'resolved';
}

// ── Tickets ─────────────────────────────────────────────
const ticketForm = ref({ ticket_id: '', source_type: 'external' });

async function addTicket() {
    if (!ticketForm.value.ticket_id) return;
    const { data } = await axios.post(`/api/v1/projects/${props.project.id}/tickets`, ticketForm.value);
    localTickets.value.unshift(data);
    ticketForm.value = { ticket_id: '', source_type: 'external' };
}

async function removeTicket(id) {
    await axios.delete(`/api/v1/tickets/${id}`);
    localTickets.value = localTickets.value.filter(t => t.id !== id);
}

// ── Stage ───────────────────────────────────────────────
const stageForm = ref({ stage_name: '' });

async function updateStage() {
    if (!stageForm.value.stage_name) return;
    await axios.put(`/api/v1/projects/${props.project.id}/stage`, stageForm.value);
    const { data } = await axios.get(`/api/v1/projects/${props.project.id}/stage/history`);
    localStageHistory.value = data;
    stageForm.value = { stage_name: '' };
}

// Stage options list
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

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return `${hr > 12 ? hr - 12 : hr}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
}

function timeAgo(d) {
    if (!d) return '';
    const diff = Date.now() - new Date(d).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function isOverdue(date) {
    if (!date) return false;
    return new Date(date) < new Date();
}

function hoursDisplay(h) {
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    if (mins === 0) return `${hrs}h`;
    return `${hrs}h ${mins}m`;
}

// ── Attachments ────────────────────────────────────────
const localAttachments = ref([...(props.attachments || [])]);
const uploadingFile = ref(false);
const fileInput = ref(null);

async function uploadAttachment(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploadingFile.value = true;
    const formData = new FormData();
    formData.append('file', file);
    try {
        const { data } = await axios.post(`/api/v1/projects/${props.project.id}/attachments`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        localAttachments.value.unshift(data);
    } catch (e) {
        console.error('Upload failed', e);
        alert('Upload failed: ' + (e.response?.data?.message || e.message));
    }
    uploadingFile.value = false;
    if (fileInput.value) fileInput.value.value = '';
}

async function deleteAttachment(id) {
    if (!confirm('Delete this attachment?')) return;
    try {
        await axios.delete(`/api/v1/attachments/${id}`);
        localAttachments.value = localAttachments.value.filter(a => a.id !== id);
    } catch (e) {
        console.error('Delete failed', e);
    }
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function fileIcon(mimeType) {
    if (!mimeType) return 'file';
    if (mimeType.startsWith('image/')) return 'image';
    if (mimeType.includes('pdf')) return 'pdf';
    if (mimeType.includes('sheet') || mimeType.includes('excel') || mimeType.includes('csv')) return 'sheet';
    if (mimeType.includes('doc') || mimeType.includes('word')) return 'doc';
    return 'file';
}

const fileIconColors = {
    image: 'text-pink-500 bg-pink-50',
    pdf: 'text-red-500 bg-red-50',
    sheet: 'text-green-500 bg-green-50',
    doc: 'text-blue-500 bg-blue-50',
    file: 'text-gray-500 bg-gray-50',
};

// ── Enhanced Workers: auto-assigned employees ──────────
const assignedEmployees = computed(() => {
    const assigned = [];
    const seen = new Set();
    const p = props.project;
    const allMembers = Array.isArray(props.teamMembers) ? props.teamMembers : [];

    // Add owner
    if (p?.owner_id) {
        assigned.push({ user_id: p.owner_id, user_name: p.owner_name, role: 'owner', source: 'project' });
        seen.add(p.owner_id);
    }
    // Add analyst
    if (p?.analyst_id && !seen.has(p.analyst_id)) {
        const m = allMembers.find(x => x.id === p.analyst_id);
        assigned.push({ user_id: p.analyst_id, user_name: p.analyst_name || m?.name || 'Unknown', role: 'analyst', source: 'project' });
        seen.add(p.analyst_id);
    }
    // Add developer
    if (p?.developer_id && !seen.has(p.developer_id)) {
        const m = allMembers.find(x => x.id === p.developer_id);
        assigned.push({ user_id: p.developer_id, user_name: p.developer_name || m?.name || 'Unknown', role: 'developer', source: 'project' });
        seen.add(p.developer_id);
    }
    // Add analyst testing
    if (p?.analyst_testing_id && !seen.has(p.analyst_testing_id)) {
        const m = allMembers.find(x => x.id === p.analyst_testing_id);
        assigned.push({ user_id: p.analyst_testing_id, user_name: p.analyst_testing_name || m?.name || 'Unknown', role: 'analyst_testing', source: 'project' });
        seen.add(p.analyst_testing_id);
    }
    // Add project_workers contributors
    for (const w of localWorkers.value) {
        if (!seen.has(w.user_id)) {
            assigned.push({ user_id: w.user_id, user_name: w.user_name, role: w.role, source: 'worker', user_email: w.user_email });
            seen.add(w.user_id);
        }
    }
    return assigned;
});

// Transfer flow
const transferForm = ref({ from_user: '', to_user: '', field: '', notes: '' });
const showTransferModal = ref(false);

function startTransfer(employee) {
    transferForm.value = {
        from_user: employee.user_id,
        from_name: employee.user_name,
        from_role: employee.role,
        to_user: '',
        field: employee.source === 'project' ? getRoleField(employee.role) : '',
        notes: '',
    };
    showTransferModal.value = true;
}

function getRoleField(role) {
    const map = { analyst: 'analyst_id', developer: 'developer_id', analyst_testing: 'analyst_testing_id' };
    return map[role] || '';
}

async function executeTransfer() {
    if (!transferForm.value.to_user || !transferForm.value.notes) return;
    try {
        const field = transferForm.value.field;
        if (field) {
            // Update project assignment
            await axios.put(`/api/v1/projects/${props.project.id}`, {
                [field]: parseInt(transferForm.value.to_user),
            });
        }
        // If it's a project_worker contributor, remove old and add new
        if (!field) {
            try { await axios.delete(`/api/v1/projects/${props.project.id}/workers/${transferForm.value.from_user}`); } catch (_) {}
            await axios.post(`/api/v1/projects/${props.project.id}/workers/contributor`, {
                user_id: parseInt(transferForm.value.to_user),
            });
        }
        // Log transfer
        await axios.post(`/api/v1/projects/${props.project.id}/transfers`, {
            to_user: parseInt(transferForm.value.to_user),
            notes: transferForm.value.notes,
        });
        showTransferModal.value = false;
        window.location.reload();
    } catch (e) {
        console.error('Transfer failed', e);
        alert('Transfer failed: ' + (e.response?.data?.message || e.message));
    }
}

const roleColors = {
    owner: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    analyst: 'bg-blue-100 text-blue-700 border-blue-200',
    developer: 'bg-green-100 text-green-700 border-green-200',
    analyst_testing: 'bg-orange-100 text-orange-700 border-orange-200',
    contributor: 'bg-gray-100 text-gray-600 border-gray-200',
};

const statusColors = {
    done: 'bg-green-100 text-green-700',
    in_progress: 'bg-blue-100 text-blue-700',
    blocked: 'bg-red-100 text-red-700',
};
</script>

<template>
    <Head :title="project?.name || 'Project'" />

    <div class="space-y-4">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <Link href="/projects" class="hover:text-[#4e1a77]">Projects</Link>
            <span>/</span>
            <span class="text-gray-900 font-medium truncate max-w-xs">{{ project?.name }}</span>
        </div>

        <!-- Header -->
        <div class="rounded-xl border border-gray-200 bg-white px-6 py-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ project?.name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">Owner: <strong>{{ project?.owner_name }}</strong></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <StageBadge v-if="project?.current_stage" :stage="project.current_stage" />
                    <StatusBadge :status="project?.status" type="project" />
                    <PriorityBadge :priority="project?.priority" />
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200">
            <nav class="flex gap-0.5 overflow-x-auto">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                    :class="activeTab === tab.key
                        ? 'border-[#4e1a77] text-[#4e1a77] bg-[#f5f0ff]/50'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                >
                    {{ tab.label }}
                    <span v-if="tab.key === 'report'" class="ml-1 rounded-full bg-[#4e1a77]/10 px-1.5 py-0.5 text-[10px] text-[#4e1a77]">NEW</span>
                </button>
            </nav>
        </div>

        <!-- ═══════ OVERVIEW TAB ═══════ -->
        <div v-if="activeTab === 'overview'" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Details Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900">Details</h3>
                    <div v-if="project?.description">
                        <p class="text-xs font-medium text-gray-500">Description</p>
                        <p class="text-sm text-gray-700">{{ project.description }}</p>
                    </div>
                    <div v-if="project?.objective">
                        <p class="text-xs font-medium text-gray-500">Objective</p>
                        <p class="text-sm text-gray-700">{{ project.objective }}</p>
                    </div>
                    <div v-if="project?.tags?.length">
                        <p class="text-xs font-medium text-gray-500">Tags</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <span v-for="tag in project.tags" :key="tag" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">{{ tag }}</span>
                        </div>
                    </div>
                </div>

                <!-- Stage Change -->
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm space-y-3">
                    <h3 class="text-sm font-semibold text-gray-900">Project Stage</h3>
                    <div class="flex items-center gap-3">
                        <StageBadge v-if="project?.current_stage" :stage="project.current_stage" />
                        <span v-else class="text-sm text-gray-400">No stage set</span>
                    </div>

                    <div class="flex gap-2 mt-3">
                        <select v-model="stageForm.stage_name" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="">Select new stage...</option>
                            <option v-for="s in stageOptions" :key="s" :value="s">{{ stageLabelFor(s) }}</option>
                        </select>
                        <button @click="updateStage" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Update</button>
                    </div>

                    <!-- Stage History -->
                    <div v-if="localStageHistory?.length" class="mt-4 space-y-2">
                        <p class="text-xs font-medium text-gray-500">History</p>
                        <div v-for="s in localStageHistory.slice(0, 5)" :key="s.id" class="flex items-center gap-2 text-xs text-gray-500">
                            <StageBadge :stage="s.stage_name" />
                            <span>&middot; {{ s.updater_name }} &middot; {{ timeAgo(s.created_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Metadata Row -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Project Info</h3>
                <div class="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Work Type</p>
                        <span v-if="project?.work_type" :class="workTypeColors[project.work_type] || 'bg-gray-100 text-gray-600'" class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase">
                            {{ workTypeLabels[project.work_type] || project.work_type }}
                        </span>
                        <p v-else class="text-sm text-gray-400 mt-1">-</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Task Type</p>
                        <p class="text-sm text-gray-700 mt-1">{{ taskTypeLabels[project?.task_type] || project?.task_type || '-' }}</p>
                    </div>
                    <!-- Analyst (editable) -->
                    <div>
                        <p class="text-xs font-medium text-gray-500">Analyst</p>
                        <template v-if="editingField === 'analyst_id'">
                            <select
                                :value="project?.analyst_id"
                                @change="updateAssignment('analyst_id', $event.target.value)"
                                @blur="editingField = null"
                                class="mt-1 rounded border border-[#4e1a77] px-2 py-1 text-sm focus:ring-1 focus:ring-[#4e1a77]"
                                autofocus
                            >
                                <option value="">Unassigned</option>
                                <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </template>
                        <p v-else
                           class="text-sm text-gray-700 mt-1"
                           :class="{ 'cursor-pointer hover:text-[#4e1a77]': isManagerOrAnalyst }"
                           @click="startEditField('analyst_id')"
                        >
                            {{ project?.analyst_name || '-' }}
                            <span v-if="isManagerOrAnalyst" class="text-[10px] text-gray-400 ml-1">(click to change)</span>
                        </p>
                    </div>
                    <!-- Developer (editable) -->
                    <div>
                        <p class="text-xs font-medium text-gray-500">Developer</p>
                        <template v-if="editingField === 'developer_id'">
                            <select
                                :value="project?.developer_id"
                                @change="updateAssignment('developer_id', $event.target.value)"
                                @blur="editingField = null"
                                class="mt-1 rounded border border-[#4e1a77] px-2 py-1 text-sm focus:ring-1 focus:ring-[#4e1a77]"
                                autofocus
                            >
                                <option value="">Unassigned</option>
                                <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </template>
                        <p v-else
                           class="text-sm text-gray-700 mt-1"
                           :class="{ 'cursor-pointer hover:text-[#4e1a77]': isManagerOrAnalyst }"
                           @click="startEditField('developer_id')"
                        >
                            {{ project?.developer_name || '-' }}
                            <span v-if="isManagerOrAnalyst" class="text-[10px] text-gray-400 ml-1">(click to change)</span>
                        </p>
                    </div>
                    <!-- Analyst Testing (editable) -->
                    <div>
                        <p class="text-xs font-medium text-gray-500">Analyst Testing</p>
                        <template v-if="editingField === 'analyst_testing_id'">
                            <select
                                :value="project?.analyst_testing_id"
                                @change="updateAssignment('analyst_testing_id', $event.target.value)"
                                @blur="editingField = null"
                                class="mt-1 rounded border border-[#4e1a77] px-2 py-1 text-sm focus:ring-1 focus:ring-[#4e1a77]"
                                autofocus
                            >
                                <option value="">Unassigned</option>
                                <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </template>
                        <p v-else
                           class="text-sm text-gray-700 mt-1"
                           :class="{ 'cursor-pointer hover:text-[#4e1a77]': isManagerOrAnalyst }"
                           @click="startEditField('analyst_testing_id')"
                        >
                            {{ project?.analyst_testing_name || '-' }}
                            <span v-if="isManagerOrAnalyst" class="text-[10px] text-gray-400 ml-1">(click to change)</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Ticket Link</p>
                        <a v-if="project?.ticket_link" :href="project.ticket_link" target="_blank" class="text-sm text-blue-600 hover:underline mt-1 inline-block">Open Ticket</a>
                        <p v-else class="text-sm text-gray-400 mt-1">-</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Document Link</p>
                        <a v-if="project?.document_link" :href="project.document_link" target="_blank" class="text-sm text-blue-600 hover:underline mt-1 inline-block">Open Document</a>
                        <p v-else class="text-sm text-gray-400 mt-1">-</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">AI Chat Link</p>
                        <a v-if="project?.ai_chat_link" :href="project.ai_chat_link" target="_blank" class="text-sm text-purple-600 hover:underline mt-1 inline-block">Open AI Chat</a>
                        <p v-else class="text-sm text-gray-400 mt-1">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════ REPORT TAB ═══════ -->
        <div v-if="activeTab === 'report'" class="space-y-4">
            <!-- Loading -->
            <div v-if="reportLoading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4e1a77]"></div>
                <span class="ml-3 text-sm text-gray-500">Loading report data...</span>
            </div>

            <template v-if="reportData && !reportLoading">
                <!-- Summary Stats -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-xl border border-[#ddd0f7] bg-[#f5f0ff] px-4 py-3">
                        <p class="text-xs font-medium text-gray-500">Total Hours</p>
                        <p class="mt-1 text-2xl font-bold text-[#4e1a77]">{{ hoursDisplay(totalProjectHours) }}</p>
                    </div>
                    <div class="rounded-xl border border-green-100 bg-green-50 px-4 py-3">
                        <p class="text-xs font-medium text-gray-500">Contributors</p>
                        <p class="mt-1 text-2xl font-bold text-green-700">{{ reportData.employeeSummary?.length || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                        <p class="text-xs font-medium text-gray-500">Work Log Entries</p>
                        <p class="mt-1 text-2xl font-bold text-blue-700">{{ totalLogEntries }}</p>
                    </div>
                    <div class="rounded-xl border border-yellow-100 bg-yellow-50 px-4 py-3">
                        <p class="text-xs font-medium text-gray-500">Planner Progress</p>
                        <p class="mt-1 text-2xl font-bold text-yellow-700">
                            {{ localPlanners.filter(p => p.status === 'done').length }}/{{ localPlanners.length }}
                        </p>
                    </div>
                </div>

                <!-- Employee Contribution Breakdown -->
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-gray-900">Employee Contributions</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Who worked how much and what they did</p>
                    </div>

                    <div v-if="reportData.employeeSummary?.length" class="divide-y divide-gray-100">
                        <div v-for="emp in reportData.employeeSummary" :key="emp.user_id">
                            <!-- Employee Summary Row -->
                            <div
                                class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors"
                                @click="toggleEmployee(emp.user_id)"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#e8ddf0] text-sm font-bold text-[#4e1a77]">
                                        {{ (emp.user_name || '?').charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ emp.user_name }}</p>
                                        <p class="text-xs text-gray-400 capitalize">{{ emp.user_role }} &middot; {{ emp.log_count }} entries &middot; {{ formatDate(emp.first_log) }} - {{ formatDate(emp.last_log) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <!-- Status breakdown mini badges -->
                                    <div class="flex items-center gap-1.5">
                                        <span v-if="emp.done_count > 0" class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700">{{ emp.done_count }} done</span>
                                        <span v-if="emp.in_progress_count > 0" class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium text-blue-700">{{ emp.in_progress_count }} WIP</span>
                                        <span v-if="emp.blocked_count > 0" class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">{{ emp.blocked_count }} blocked</span>
                                    </div>
                                    <!-- Hours -->
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-[#4e1a77]">{{ hoursDisplay(emp.total_hours) }}</p>
                                        <div class="w-24 bg-gray-200 rounded-full h-1.5 mt-1">
                                            <div class="bg-[#4e1a77] h-1.5 rounded-full" :style="{ width: Math.min(100, (emp.total_hours / Math.max(totalProjectHours, 1)) * 100) + '%' }"></div>
                                        </div>
                                    </div>
                                    <!-- Expand arrow -->
                                    <svg class="h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': expandedEmployee === emp.user_id }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Expanded: Detailed Work Logs -->
                            <div v-if="expandedEmployee === emp.user_id" class="bg-gray-50/50 px-5 pb-4">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                            <th class="py-2 pr-3 text-left">Date</th>
                                            <th class="py-2 pr-3 text-left">Time</th>
                                            <th class="py-2 pr-3 text-left">Hours</th>
                                            <th class="py-2 pr-3 text-left">Status</th>
                                            <th class="py-2 text-left">Note</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="log in getEmployeeLogs(emp.user_id)" :key="log.id" class="text-sm">
                                            <td class="py-2 pr-3 text-gray-600 whitespace-nowrap">{{ formatDate(log.log_date) }}</td>
                                            <td class="py-2 pr-3 text-gray-500 whitespace-nowrap text-xs">
                                                {{ formatTime(log.start_time) }} - {{ formatTime(log.end_time) }}
                                            </td>
                                            <td class="py-2 pr-3 font-medium text-gray-900 whitespace-nowrap">{{ hoursDisplay(log.hours_spent) }}</td>
                                            <td class="py-2 pr-3">
                                                <span :class="statusColors[log.status] || 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-0.5 text-[10px] font-medium capitalize">{{ log.status?.replace('_', ' ') }}</span>
                                            </td>
                                            <td class="py-2 text-gray-600">
                                                <p v-if="log.note" class="text-xs">{{ log.note }}</p>
                                                <p v-if="log.blocker" class="text-xs text-red-600 mt-0.5">Blocker: {{ log.blocker }}</p>
                                                <span v-if="!log.note && !log.blocker" class="text-xs text-gray-300">-</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <p v-else class="px-5 py-8 text-center text-sm text-gray-400">No work logs recorded for this project yet</p>
                </div>
            </template>
        </div>

        <!-- ═══════ PLANNER TAB ═══════ -->
        <div v-if="activeTab === 'planner'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Add Planner Item</h3>
                <div class="flex flex-wrap gap-3">
                    <input v-model="plannerForm.title" placeholder="Task title *" class="flex-1 min-w-[200px] rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    <select v-model="plannerForm.assigned_to" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option value="">Assignee (default: owner)</option>
                        <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <input v-model="plannerForm.due_date" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    <label class="flex items-center gap-1.5 text-sm text-gray-600">
                        <input v-model="plannerForm.milestone_flag" type="checkbox" class="rounded border-gray-300 text-[#4e1a77] focus:ring-[#4e1a77]" />
                        Milestone
                    </label>
                    <button @click="addPlanner" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Add</button>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="divide-y divide-gray-100">
                    <div
                        v-for="p in localPlanners"
                        :key="p.id"
                        class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors"
                        :class="{ 'bg-red-50/50': p.due_date && isOverdue(p.due_date) && p.status !== 'done' }"
                    >
                        <div class="flex items-center gap-3">
                            <span v-if="p.milestone_flag" class="text-yellow-500" title="Milestone">&#9733;</span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ p.title }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ p.assignee_name || 'Unassigned' }}
                                    <span v-if="p.due_date"> &middot; Due: {{ p.due_date }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <select
                                :value="p.status"
                                @change="updatePlannerStatus(p, $event.target.value)"
                                class="rounded border border-gray-200 px-2 py-1 text-xs focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                            >
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="done">Done</option>
                                <option value="blocked">Blocked</option>
                            </select>
                            <button @click="deletePlanner(p.id)" class="text-gray-300 hover:text-red-500 text-sm" title="Delete">&#10005;</button>
                        </div>
                    </div>
                    <p v-if="!localPlanners?.length" class="px-5 py-8 text-center text-sm text-gray-400">No planner items yet</p>
                </div>
            </div>
        </div>

        <!-- ═══════ UPDATES TAB ═══════ -->
        <div v-if="activeTab === 'updates'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex gap-3">
                    <textarea v-model="updateForm.content" rows="2" placeholder="Write an update..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    <div class="flex flex-col gap-2">
                        <select v-model="updateForm.source" class="rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="manual">Manual</option>
                            <option value="ai">AI</option>
                        </select>
                        <button @click="addUpdate" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Post</button>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="u in localUpdates"
                    :key="u.id"
                    class="rounded-xl border px-5 py-4 shadow-sm"
                    :class="u.source === 'system' ? 'border-gray-100 bg-gray-50' : 'border-gray-200 bg-white'"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                            :class="u.source === 'system' ? 'bg-gray-200 text-gray-500' : 'bg-[#e8ddf0] text-[#4e1a77]'"
                        >
                            {{ u.source === 'system' ? 'S' : (u.author_name || '?').charAt(0).toUpperCase() }}
                        </div>
                        <span class="text-sm font-medium" :class="u.source === 'system' ? 'text-gray-500' : 'text-gray-900'">{{ u.source === 'system' ? 'System' : u.author_name }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase"
                            :class="u.source === 'ai' ? 'bg-purple-100 text-purple-700' : u.source === 'system' ? 'bg-gray-200 text-gray-500' : 'bg-gray-100 text-gray-600'"
                        >{{ u.source }}</span>
                        <span class="text-xs text-gray-400 ml-auto">{{ timeAgo(u.created_at) }}</span>
                    </div>
                    <p
                        class="text-sm whitespace-pre-wrap"
                        :class="u.source === 'system' ? 'text-gray-500 italic' : 'text-gray-700'"
                    >{{ u.content }}</p>
                </div>
                <p v-if="!localUpdates?.length" class="text-center text-sm text-gray-400 py-8">No updates yet</p>
            </div>
        </div>

        <!-- ═══════ WORKERS TAB ═══════ -->
        <div v-if="activeTab === 'workers'" class="space-y-4">
            <!-- All Assigned Employees (auto-populated) -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Assigned Team</h2>
                        <p class="text-xs text-gray-400 mt-0.5">All employees assigned to this project</p>
                    </div>
                    <span class="rounded-full bg-[#4e1a77]/10 px-2.5 py-0.5 text-xs font-medium text-[#4e1a77]">{{ assignedEmployees.length }} members</span>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="emp in assignedEmployees" :key="emp.user_id" class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold bg-[#e8ddf0] text-[#4e1a77]">
                                {{ (emp.user_name || '?').charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ emp.user_name }}</p>
                                <span
                                    class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize"
                                    :class="roleColors[emp.role] || roleColors.contributor"
                                >
                                    {{ emp.role?.replace('_', ' ') }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="isManagerOrAnalyst && emp.role !== 'owner'"
                                @click="startTransfer(emp)"
                                class="rounded-lg border border-orange-200 px-3 py-1.5 text-xs font-medium text-orange-600 hover:bg-orange-50 transition-colors"
                                title="Transfer to another employee"
                            >
                                Transfer
                            </button>
                        </div>
                    </div>
                    <p v-if="!assignedEmployees.length" class="px-5 py-8 text-center text-sm text-gray-400">No employees assigned</p>
                </div>
            </div>

            <!-- Add Contributor -->
            <div v-if="isManagerOrAnalyst" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Add Contributor</h3>
                <div class="flex gap-3">
                    <select v-model="workerForm.user_id" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option value="">Select team member</option>
                        <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }} ({{ m.role }})</option>
                    </select>
                    <button @click="addContributor" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Add</button>
                </div>
            </div>

            <!-- Transfer History -->
            <div v-if="transfers?.length" class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3"><h3 class="text-sm font-semibold text-gray-900">Transfer History</h3></div>
                <div class="divide-y divide-gray-100">
                    <div v-for="t in transfers" :key="t.id" class="px-5 py-3">
                        <p class="text-sm text-gray-700"><strong>{{ t.from_name }}</strong> &rarr; <strong>{{ t.to_name }}</strong></p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ t.notes }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ timeAgo(t.created_at) }}</p>
                    </div>
                </div>
            </div>

            <!-- Transfer Modal -->
            <div v-if="showTransferModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Transfer Role</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Transfer <strong>{{ transferForm.from_name }}</strong>'s
                        <span class="capitalize font-medium">{{ transferForm.from_role?.replace('_', ' ') }}</span>
                        role to another employee.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs font-medium text-gray-500 mb-1 block">New Employee</label>
                            <select v-model="transferForm.to_user" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                                <option value="">Select employee...</option>
                                <option v-for="m in teamMembers" :key="m.id" :value="m.id" :disabled="m.id === transferForm.from_user">
                                    {{ m.name }} ({{ m.role }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 mb-1 block">Transfer Reason</label>
                            <textarea
                                v-model="transferForm.notes"
                                rows="2"
                                placeholder="e.g. Employee left the company, workload rebalancing..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                            />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-5">
                        <button @click="showTransferModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button
                            @click="executeTransfer"
                            :disabled="!transferForm.to_user || !transferForm.notes"
                            class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] disabled:opacity-50 disabled:cursor-not-allowed"
                        >Transfer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════ ATTACHMENTS TAB ═══════ -->
        <div v-if="activeTab === 'attachments'" class="space-y-4">
            <!-- Upload -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Upload File</h3>
                <div class="flex items-center gap-3">
                    <label class="flex-1 flex items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-4 py-6 cursor-pointer hover:border-[#4e1a77] hover:bg-[#f5f0ff]/30 transition-colors">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        <span class="text-sm text-gray-500">
                            <span v-if="uploadingFile">Uploading...</span>
                            <span v-else>Click to upload (doc, sheet, pdf, images, any file - max 20MB)</span>
                        </span>
                        <input
                            ref="fileInput"
                            type="file"
                            class="hidden"
                            @change="uploadAttachment"
                            :disabled="uploadingFile"
                        />
                    </label>
                </div>
            </div>

            <!-- Files List -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Attached Files</h2>
                    <span class="text-xs text-gray-400">{{ localAttachments.length }} file(s)</span>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="a in localAttachments" :key="a.id" class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg shrink-0" :class="fileIconColors[fileIcon(a.mime_type)]">
                                <svg v-if="fileIcon(a.mime_type) === 'image'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" /></svg>
                                <svg v-else-if="fileIcon(a.mime_type) === 'pdf'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <a :href="`/storage/${a.stored_path}`" target="_blank" class="text-sm font-medium text-gray-900 hover:text-[#4e1a77] truncate block">
                                    {{ a.original_name }}
                                </a>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ formatFileSize(a.size) }}
                                    <span v-if="a.uploader_name"> &middot; {{ a.uploader_name }}</span>
                                    <span v-if="a.created_at"> &middot; {{ formatDate(a.created_at) }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ml-3">
                            <a :href="`/storage/${a.stored_path}`" target="_blank" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Download</a>
                            <button
                                v-if="isManagerOrAnalyst"
                                @click="deleteAttachment(a.id)"
                                class="text-xs text-red-500 hover:text-red-700"
                            >Delete</button>
                        </div>
                    </div>
                    <p v-if="!localAttachments?.length" class="px-5 py-8 text-center text-sm text-gray-400">No files attached yet</p>
                </div>
            </div>
        </div>

        <!-- ═══════ TICKETS TAB ═══════ -->
        <div v-if="activeTab === 'tickets'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Link Ticket</h3>
                <div class="flex gap-3">
                    <input v-model="ticketForm.ticket_id" placeholder="Ticket ID (e.g. JIRA-123)" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    <select v-model="ticketForm.source_type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option value="external">External</option>
                        <option value="internal">Internal</option>
                    </select>
                    <button @click="addTicket" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Link</button>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                <div v-for="t in localTickets" :key="t.id" class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ t.ticket_id }}</p>
                        <span class="text-xs capitalize rounded-full px-2 py-0.5" :class="t.source_type === 'internal' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600'">{{ t.source_type }}</span>
                    </div>
                    <button @click="removeTicket(t.id)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                </div>
                <p v-if="!localTickets?.length" class="px-5 py-8 text-center text-sm text-gray-400">No tickets linked (tickets are optional)</p>
            </div>
        </div>

        <!-- ═══════ BLOCKERS TAB ═══════ -->
        <div v-if="activeTab === 'blockers'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Report Blocker</h3>
                <div class="flex gap-3">
                    <textarea v-model="blockerForm.description" rows="2" placeholder="Describe the blocker..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    <button @click="addBlocker" class="self-end rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Report</button>
                </div>
            </div>

            <div class="rounded-xl border border-red-200 bg-white shadow-sm">
                <div class="border-b border-red-100 px-5 py-3 bg-red-50/50 rounded-t-xl">
                    <h3 class="text-sm font-semibold text-red-700">Active Blockers</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="b in localBlockers.filter(x => x.status === 'active')" :key="b.id" class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="text-sm text-gray-900">{{ b.description }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ b.creator_name }} &middot; {{ timeAgo(b.created_at) }}</p>
                        </div>
                        <button @click="resolveBlocker(b.id)" class="rounded-lg border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50">Resolve</button>
                    </div>
                    <p v-if="!localBlockers.filter(x => x.status === 'active').length" class="px-5 py-6 text-center text-sm text-gray-400">No active blockers</p>
                </div>
            </div>

            <div v-if="localBlockers.filter(x => x.status === 'resolved').length" class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Resolved</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="b in localBlockers.filter(x => x.status === 'resolved')" :key="b.id" class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-green-500">&#10003;</span>
                            <p class="text-sm text-gray-600 line-through">{{ b.description }}</p>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 ml-5">Resolved by {{ b.resolver_name }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
