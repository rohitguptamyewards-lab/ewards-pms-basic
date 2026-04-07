<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    teamMembers: { type: Array, default: () => [] },
    replicateFrom: { type: Object, default: null },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isAnalyst = computed(() => currentUser.value?.role === 'analyst');

// Pre-fill from replicateFrom if present
const r = props.replicateFrom;
const form = useForm({
    name: r ? r.name + ' (Copy)' : '',
    description: r?.description || '',
    objective: r?.objective || '',
    tags: r?.tags ? (Array.isArray(r.tags) ? r.tags.join(', ') : (typeof r.tags === 'string' ? JSON.parse(r.tags).join(', ') : '')) : '',
    owner_id: r?.owner_id ? String(r.owner_id) : '',
    priority: r?.priority || 'medium',
    status: 'active',
    work_type: r?.work_type || '',
    task_type: r?.task_type || '',
    custom_task_type: r?.custom_task_type || '',
    ticket_link: r?.ticket_link || '',
    analyst_id: r?.analyst_id ? String(r.analyst_id) : '',
    analyst_testing_id: r?.analyst_testing_id ? String(r.analyst_testing_id) : '',
    developer_id: r?.developer_id ? String(r.developer_id) : '',
    document_link: r?.document_link || '',
    ai_chat_link: r?.ai_chat_link || '',
});

// Auto-set analyst_id if current user is analyst and not replicating
if (isAnalyst.value && !r) {
    form.analyst_id = String(currentUser.value.id);
}

// File attachments (uploaded after project is created)
const pendingFiles = ref([]);
const uploading = ref(false);

function handleFileSelect(event) {
    const files = Array.from(event.target.files);
    files.forEach(f => {
        pendingFiles.value.push(f);
    });
    event.target.value = '';
}

function removeFile(index) {
    pendingFiles.value.splice(index, 1);
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

const fileIcon = (name) => {
    const ext = name.split('.').pop().toLowerCase();
    if (['pdf'].includes(ext)) return 'text-red-500';
    if (['doc', 'docx'].includes(ext)) return 'text-blue-500';
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'text-green-500';
    if (['png', 'jpg', 'jpeg', 'gif'].includes(ext)) return 'text-purple-500';
    return 'text-gray-500';
};

const analysts = computed(() => props.teamMembers.filter(m => m.role === 'analyst' || m.role === 'manager'));
const developers = computed(() => props.teamMembers.filter(m => m.role === 'employee'));

async function submit() {
    const data = {
        ...form.data(),
        tags: form.tags ? form.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
        owner_id: form.owner_id ? parseInt(form.owner_id) : null,
        analyst_id: form.analyst_id ? parseInt(form.analyst_id) : null,
        analyst_testing_id: form.analyst_testing_id ? parseInt(form.analyst_testing_id) : null,
        developer_id: form.developer_id ? parseInt(form.developer_id) : null,
    };

    form.transform(() => data).post('/projects', {
        onSuccess: async (page) => {
            // Upload pending files if any
            if (pendingFiles.value.length > 0) {
                const projectId = page.props?.project?.id;
                if (projectId) {
                    uploading.value = true;
                    for (const file of pendingFiles.value) {
                        const fd = new FormData();
                        fd.append('file', file);
                        try {
                            await axios.post(`/api/v1/projects/${projectId}/attachments`, fd);
                        } catch (e) {
                            console.error('File upload failed:', e);
                        }
                    }
                    uploading.value = false;
                }
            }
        }
    });
}
</script>

<template>
    <Head :title="replicateFrom ? 'Replicate Project' : 'Create Project'" />

    <div class="mx-auto max-w-3xl space-y-4">
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <Link href="/projects" class="hover:text-[#4e1a77]">Projects</Link>
            <span>/</span>
            <span class="text-gray-900 font-medium">{{ replicateFrom ? 'Replicate' : 'Create' }}</span>
        </div>

        <!-- Replicate notice -->
        <div v-if="replicateFrom" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm text-blue-700">
            Replicating from: <strong>{{ replicateFrom.name }}</strong> &mdash; Edit the fields below and create.
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h1 class="text-lg font-bold text-gray-900">{{ replicateFrom ? 'Replicate Project' : 'New Project' }}</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-5 px-6 py-5">
                <!-- Project Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Project Name *</label>
                    <input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" :class="{ 'border-red-400 bg-red-50': form.errors.name }" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea v-model="form.description" rows="2" class="mt-1 block w-full resize-none rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                </div>

                <!-- Objective -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Objective</label>
                    <textarea v-model="form.objective" rows="2" class="mt-1 block w-full resize-none rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                </div>

                <!-- Row: Work Type + Task Type + Priority -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Work Type</label>
                        <select v-model="form.work_type" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="">Select...</option>
                            <option value="frontend_work">Frontend Work</option>
                            <option value="backend_work">Backend Work</option>
                            <option value="figma">Figma</option>
                            <option value="trigger_part">Trigger Part</option>
                            <option value="data_mapping">Data Mapping</option>
                            <option value="full_stack">Full Stack</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Task Type</label>
                        <select v-model="form.task_type" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="">Select...</option>
                            <option value="new_project">New Project</option>
                            <option value="addition_on_existing">Addition on Existing</option>
                            <option value="bug_fix">Bug Fix</option>
                            <option value="data_mapping">Data Mapping</option>
                            <option value="integration">Integration</option>
                            <option value="other">Other</option>
                        </select>
                        <!-- Custom task type input when "other" is selected -->
                        <input
                            v-if="form.task_type === 'other'"
                            v-model="form.custom_task_type"
                            type="text"
                            placeholder="Enter custom task type..."
                            class="mt-2 block w-full rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Priority</label>
                        <select v-model="form.priority" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <!-- Row: Owner + Analyst + Developer + Analyst Testing -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Owner *</label>
                        <select v-model="form.owner_id" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" :class="{ 'border-red-400 bg-red-50': form.errors.owner_id }">
                            <option value="">Select owner</option>
                            <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }} ({{ m.role }})</option>
                        </select>
                        <p v-if="form.errors.owner_id" class="mt-1 text-xs text-red-500">{{ form.errors.owner_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Analyst</label>
                        <select v-model="form.analyst_id" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="">Select analyst</option>
                            <option v-for="m in analysts" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Developer</label>
                        <select v-model="form.developer_id" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="">Select developer</option>
                            <option v-for="m in developers" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Analyst Testing</label>
                        <select v-model="form.analyst_testing_id" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="">Select analyst for testing</option>
                            <option v-for="m in analysts" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Row: Tags + Ticket Link -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tags (comma separated)</label>
                        <input v-model="form.tags" type="text" placeholder="e.g. payment, mobile, api" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ticket Link</label>
                        <input v-model="form.ticket_link" type="url" placeholder="https://..." class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    </div>
                </div>

                <!-- Row: Document Link + AI Chat Link -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Document Link</label>
                        <input v-model="form.document_link" type="url" placeholder="Google Docs / Sheets URL" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">AI Chat Link</label>
                        <input v-model="form.ai_chat_link" type="url" placeholder="ChatGPT / Claude URL" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    </div>
                </div>

                <!-- File Attachments -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Attach Files</label>
                    <div class="rounded-lg border-2 border-dashed border-gray-300 p-4 text-center hover:border-[#4e1a77] transition-colors">
                        <input
                            type="file"
                            multiple
                            @change="handleFileSelect"
                            class="hidden"
                            ref="fileInput"
                            id="fileUpload"
                        />
                        <label for="fileUpload" class="cursor-pointer">
                            <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                            </svg>
                            <p class="mt-1 text-sm text-gray-500">Click to attach files (doc, sheets, images, etc.)</p>
                            <p class="text-xs text-gray-400">Max 20MB per file</p>
                        </label>
                    </div>

                    <!-- Pending files list -->
                    <div v-if="pendingFiles.length" class="mt-3 space-y-2">
                        <div
                            v-for="(file, idx) in pendingFiles"
                            :key="idx"
                            class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 bg-gray-50"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="h-4 w-4 shrink-0" :class="fileIcon(file.name)" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                <span class="text-sm text-gray-700 truncate">{{ file.name }}</span>
                                <span class="text-xs text-gray-400 shrink-0">({{ formatFileSize(file.size) }})</span>
                            </div>
                            <button type="button" @click="removeFile(idx)" class="text-gray-400 hover:text-red-500 shrink-0 ml-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <p v-if="pendingFiles.length" class="mt-1 text-xs text-gray-400">Files will be uploaded after project is created</p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                    <Link href="/projects" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</Link>
                    <button type="submit" :disabled="form.processing || uploading" class="rounded-lg bg-[#4e1a77] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] disabled:opacity-50">
                        {{ form.processing ? 'Creating...' : uploading ? 'Uploading files...' : (replicateFrom ? 'Create Replica' : 'Create Project') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
