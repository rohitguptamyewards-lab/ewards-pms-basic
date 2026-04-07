<script setup>
import { ref, computed, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    workLogs: { type: Object, default: () => ({ data: [], links: [] }) },
    projects: { type: [Array, Object], default: () => [] },
    teamMembers: { type: [Array, Object], default: () => [] },
    weekTotal: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const isManagerOrAnalyst = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));

// --- Mode: 'manual' or 'timer' ---
const mode = ref('manual');

// --- Form state ---
const form = ref({
    project_id: '',
    log_date: new Date().toISOString().split('T')[0],
    start_time: '',
    end_time: '',
    note: '',
    blocker: '',
});

const formErrors = ref({});
const submitting = ref(false);

// --- Timer state ---
const timerRunning = ref(false);
const timerStartedAt = ref(null);
const timerElapsed = ref(0);
let timerInterval = null;

function padZero(n) {
    return String(n).padStart(2, '0');
}

function formatElapsed(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    return `${padZero(h)}:${padZero(m)}:${padZero(s)}`;
}

function nowTimeString() {
    const now = new Date();
    return `${padZero(now.getHours())}:${padZero(now.getMinutes())}`;
}

function todayString() {
    return new Date().toISOString().split('T')[0];
}

function startTimer() {
    timerRunning.value = true;
    timerStartedAt.value = new Date();
    timerElapsed.value = 0;
    form.value.start_time = nowTimeString();
    form.value.log_date = todayString();

    timerInterval = setInterval(() => {
        const now = new Date();
        timerElapsed.value = Math.floor((now - timerStartedAt.value) / 1000);
    }, 1000);
}

async function stopTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
    timerRunning.value = false;
    form.value.end_time = nowTimeString();

    await addEntry();
    timerElapsed.value = 0;
    timerStartedAt.value = null;
}

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// --- Duration calculation ---
function calcDurationSeconds(start, end) {
    if (!start || !end) return 0;
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const diff = (eh * 60 + em) - (sh * 60 + sm);
    return diff > 0 ? diff * 60 : 0;
}

const calculatedDuration = computed(() => {
    const sec = calcDurationSeconds(form.value.start_time, form.value.end_time);
    if (sec <= 0) return '';
    return formatElapsed(sec);
});

// --- Add entry ---
async function addEntry() {
    if (!form.value.project_id || !form.value.start_time || !form.value.end_time) return;
    submitting.value = true;
    formErrors.value = {};

    try {
        await axios.post('/api/v1/work-logs', form.value);
        form.value = {
            project_id: form.value.project_id,
            log_date: new Date().toISOString().split('T')[0],
            start_time: '',
            end_time: '',
            note: '',
            blocker: '',
        };
        router.reload({ only: ['workLogs', 'weekTotal'] });
    } catch (err) {
        if (err.response?.status === 422) {
            formErrors.value = err.response.data.errors || {};
        }
    } finally {
        submitting.value = false;
    }
}

// --- Delete entry ---
async function deleteEntry(id) {
    if (!confirm('Delete this work log?')) return;
    await axios.delete(`/api/v1/work-logs/${id}`);
    router.reload({ only: ['workLogs', 'weekTotal'] });
}

// --- Group logs by date ---
const groupedLogs = computed(() => {
    const groups = {};
    (props.workLogs?.data || []).forEach(log => {
        const date = log.log_date?.split('T')[0] || log.log_date;
        if (!groups[date]) groups[date] = { date, logs: [], total: 0 };
        groups[date].logs.push(log);
        groups[date].total += parseFloat(log.hours_spent || 0);
    });
    return Object.values(groups).sort((a, b) => b.date.localeCompare(a.date));
});

// --- Date/time formatting ---
function formatDate(d) {
    if (!d) return '';
    const date = new Date(d + 'T00:00:00');
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === today.toDateString()) return 'Today';
    if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function formatTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    const ampm = hr >= 12 ? 'PM' : 'AM';
    const hr12 = hr % 12 || 12;
    return `${hr12}:${m} ${ampm}`;
}

function hoursToHMS(hours) {
    const total = Math.round(parseFloat(hours) * 3600);
    return formatElapsed(total);
}

function dailyTotalHMS(totalHours) {
    return hoursToHMS(totalHours);
}

function weekTotalFormatted() {
    return hoursToHMS(props.weekTotal);
}

// --- Project color dots ---
const dotColors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6', '#6366f1'];

function projectDotColor(projectId) {
    return dotColors[(projectId || 0) % dotColors.length];
}

// --- Filter state ---
const showFilters = ref(false);
const filterForm = ref({ ...props.filters });

function applyFilters() {
    router.get('/work-logs', filterForm.value, { preserveState: true });
}

function clearFilters() {
    filterForm.value = {};
    router.get('/work-logs', {}, { preserveState: true });
}

// --- 3-dot menu ---
const openMenuId = ref(null);

function toggleMenu(id) {
    openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
    openMenuId.value = null;
}

// --- Selected project name for display ---
const selectedProjectName = computed(() => {
    if (!form.value.project_id) return '';
    const p = (Array.isArray(props.projects) ? props.projects : []).find(
        proj => proj.id == form.value.project_id
    );
    return p ? p.name : '';
});
</script>

<template>
    <Head title="Work Logs" />

    <div class="space-y-0" @click="closeMenu">
        <!-- ===================== TOP BAR ===================== -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm mb-5">
            <!-- Mode toggle row -->
            <div class="flex items-center justify-end border-b border-gray-100 px-4 py-1.5">
                <div class="inline-flex items-center rounded-md bg-gray-100 p-0.5 text-xs">
                    <button
                        @click="mode = 'manual'"
                        class="rounded px-3 py-1 font-medium transition-colors"
                        :class="mode === 'manual' ? 'bg-white text-[#4e1a77] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    >
                        Manual
                    </button>
                    <button
                        @click="mode = 'timer'"
                        class="rounded px-3 py-1 font-medium transition-colors"
                        :class="mode === 'timer' ? 'bg-white text-[#4e1a77] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    >
                        Timer
                    </button>
                </div>
            </div>

            <!-- Input row -->
            <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
                <!-- Note input -->
                <div class="flex-1 min-w-0">
                    <input
                        v-model="form.note"
                        type="text"
                        placeholder="What have you worked on?"
                        class="w-full border-0 border-b border-transparent bg-transparent px-0 py-1.5 text-sm text-gray-800 placeholder-gray-400 focus:border-b focus:border-[#4e1a77] focus:outline-none focus:ring-0"
                    />
                </div>

                <!-- Project dropdown -->
                <div class="shrink-0">
                    <select
                        v-model="form.project_id"
                        class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        :class="{ 'border-red-400': formErrors.project_id }"
                    >
                        <option value="">Project</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>

                <!-- Divider (desktop) -->
                <div class="hidden lg:block h-8 w-px bg-gray-200"></div>

                <!-- MANUAL: time inputs + date + duration + ADD -->
                <template v-if="mode === 'manual'">
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="form.start_time"
                            type="time"
                            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-2 text-sm text-gray-700 focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        />
                        <span class="text-gray-400 text-sm">-</span>
                        <input
                            v-model="form.end_time"
                            type="time"
                            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-2 text-sm text-gray-700 focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        />
                        <input
                            v-model="form.log_date"
                            type="date"
                            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-2 text-sm text-gray-700 focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        />
                    </div>

                    <!-- Duration display -->
                    <div class="shrink-0 min-w-[72px] text-center">
                        <span class="font-mono text-sm font-semibold" :class="calculatedDuration ? 'text-[#4e1a77]' : 'text-gray-300'">
                            {{ calculatedDuration || '00:00:00' }}
                        </span>
                    </div>

                    <!-- ADD button -->
                    <button
                        @click="addEntry"
                        :disabled="submitting || !form.project_id || !form.start_time || !form.end_time"
                        class="shrink-0 rounded-lg bg-[#4e1a77] px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#3d1560] disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        ADD
                    </button>
                </template>

                <!-- TIMER: elapsed display + start/stop -->
                <template v-else>
                    <div class="flex items-center gap-3">
                        <!-- Timer display -->
                        <div class="min-w-[88px] text-center">
                            <span class="font-mono text-lg font-semibold" :class="timerRunning ? 'text-[#4e1a77]' : 'text-gray-400'">
                                {{ formatElapsed(timerElapsed) }}
                            </span>
                        </div>

                        <!-- Start / Stop -->
                        <button
                            v-if="!timerRunning"
                            @click="startTimer"
                            :disabled="!form.project_id"
                            class="shrink-0 rounded-lg bg-[#4e1a77] px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#3d1560] disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            START
                        </button>
                        <button
                            v-else
                            @click="stopTimer"
                            class="shrink-0 rounded-lg bg-red-600 px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
                        >
                            STOP
                        </button>
                    </div>
                </template>
            </div>

            <!-- Error row -->
            <div v-if="Object.keys(formErrors).length" class="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-600">
                <span v-for="(errs, field) in formErrors" :key="field">{{ errs[0] }} </span>
            </div>
        </div>

        <!-- ===================== WEEK HEADER + FILTERS ===================== -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-bold text-gray-900">This week</h2>
                <button
                    v-if="isManagerOrAnalyst"
                    @click="showFilters = !showFilters"
                    class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
                >
                    <span v-if="!showFilters">Filters</span>
                    <span v-else>Hide filters</span>
                </button>
            </div>
            <div class="text-right">
                <span class="text-sm text-gray-500">Total: </span>
                <span class="font-mono text-base font-bold text-[#4e1a77]">{{ weekTotalFormatted() }}</span>
            </div>
        </div>

        <!-- Filters Panel -->
        <div v-if="showFilters && isManagerOrAnalyst" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm mb-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Team Member</label>
                    <select
                        v-model="filterForm.user_id"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                    >
                        <option value="">All</option>
                        <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Project</label>
                    <select
                        v-model="filterForm.project_id"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                    >
                        <option value="">All</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                    <input
                        v-model="filterForm.date_from"
                        type="date"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                    <input
                        v-model="filterForm.date_to"
                        type="date"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                    />
                </div>
                <button
                    @click="applyFilters"
                    class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]"
                >
                    Apply
                </button>
                <button
                    @click="clearFilters"
                    class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:text-gray-700"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- ===================== GROUPED WORK LOG ENTRIES ===================== -->
        <div class="space-y-4">
            <div v-for="group in groupedLogs" :key="group.date" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <!-- Date header -->
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-5 py-2.5">
                    <span class="text-sm font-semibold text-gray-700">{{ formatDate(group.date) }}</span>
                    <span class="font-mono text-sm font-bold text-[#4e1a77]">{{ dailyTotalHMS(group.total) }}</span>
                </div>

                <!-- Entries -->
                <div class="divide-y divide-gray-50">
                    <div
                        v-for="log in group.logs"
                        :key="log.id"
                        class="group flex items-center gap-4 px-5 py-3 hover:bg-gray-50/60 transition-colors"
                    >
                        <!-- Left: note + project -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-800 truncate" :class="{ 'text-gray-400 italic': !log.note }">
                                {{ log.note || 'No description' }}
                            </p>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span
                                    class="inline-block h-2.5 w-2.5 rounded-full shrink-0"
                                    :style="{ backgroundColor: projectDotColor(log.project_id) }"
                                ></span>
                                <Link
                                    :href="`/projects/${log.project_id}`"
                                    class="text-xs font-medium text-gray-500 hover:text-[#4e1a77] truncate"
                                >
                                    {{ log.project_name }}
                                </Link>
                            </div>
                            <p v-if="isManagerOrAnalyst && log.user_name" class="text-[10px] text-gray-400 mt-0.5">{{ log.user_name }}</p>
                        </div>

                        <!-- Right: time range + duration + menu -->
                        <div class="flex items-center gap-4 shrink-0">
                            <!-- Time range -->
                            <span class="hidden sm:inline text-xs text-gray-400">
                                {{ formatTime(log.start_time) }} - {{ formatTime(log.end_time) }}
                            </span>

                            <!-- Duration -->
                            <span class="font-mono text-sm font-semibold text-gray-800 min-w-[72px] text-right">
                                {{ hoursToHMS(log.hours_spent) }}
                            </span>

                            <!-- 3-dot menu -->
                            <div class="relative">
                                <button
                                    @click.stop="toggleMenu(log.id)"
                                    class="opacity-0 group-hover:opacity-100 rounded p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"
                                    title="Actions"
                                >
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="10" cy="4" r="1.5" />
                                        <circle cx="10" cy="10" r="1.5" />
                                        <circle cx="10" cy="16" r="1.5" />
                                    </svg>
                                </button>
                                <!-- Dropdown -->
                                <div
                                    v-if="openMenuId === log.id"
                                    class="absolute right-0 top-8 z-10 w-32 rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                >
                                    <button
                                        @click.stop="deleteEntry(log.id); closeMenu()"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-xs text-red-600 hover:bg-red-50"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!groupedLogs.length" class="rounded-xl border border-gray-200 bg-white px-5 py-16 text-center shadow-sm mt-4">
            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-gray-400">No work logs found.</p>
            <p class="mt-1 text-xs text-gray-400">Start tracking your time using the bar above.</p>
        </div>

        <!-- Pagination -->
        <div v-if="workLogs.links?.length > 3" class="flex justify-center gap-1 mt-4">
            <Link
                v-for="link in workLogs.links"
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
