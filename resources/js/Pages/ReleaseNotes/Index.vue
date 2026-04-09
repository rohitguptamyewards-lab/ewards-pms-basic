<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    project: Object,
    releaseNotes: { type: Array, default: () => [] },
    canCreate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const notes = ref([...props.releaseNotes]);
const showCreateNote = ref(false);
const newNote = ref({ title: '', description: '', links: [] });
const newNoteFiles = ref(null);
const creatingNote = ref(false);
const newLinkForm = ref({ label: '', url: '' });

function addNoteLink() {
    if (!newLinkForm.value.url) return;
    newNote.value.links.push({ ...newLinkForm.value });
    newLinkForm.value = { label: '', url: '' };
}

function removeNoteLink(idx) {
    newNote.value.links.splice(idx, 1);
}

async function createReleaseNote() {
    if (!newNote.value.title) return;
    creatingNote.value = true;
    try {
        const formData = new FormData();
        formData.append('title', newNote.value.title);
        if (newNote.value.description) formData.append('description', newNote.value.description);
        if (newNoteFiles.value?.files) {
            for (const file of newNoteFiles.value.files) {
                formData.append('files[]', file);
            }
        }
        newNote.value.links.forEach((link, i) => {
            formData.append(`links[${i}][label]`, link.label || '');
            formData.append(`links[${i}][url]`, link.url);
        });

        const { data } = await axios.post(`/api/v1/projects/${props.project.id}/release-notes`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        notes.value.unshift(data);
        newNote.value = { title: '', description: '', links: [] };
        if (newNoteFiles.value) newNoteFiles.value.value = '';
        showCreateNote.value = false;
    } catch (e) {
        alert('Failed: ' + (e.response?.data?.message || e.message));
    }
    creatingNote.value = false;
}

async function deleteNote(id) {
    if (!confirm('Delete this release note?')) return;
    try {
        await axios.delete(`/api/v1/release-notes/${id}`);
        notes.value = notes.value.filter(n => n.id !== id);
    } catch (e) { alert('Delete failed: ' + (e.response?.data?.message || e.message)); }
}

async function deleteFile(noteId, fileId) {
    if (!confirm('Delete this file?')) return;
    try {
        await axios.delete(`/api/v1/release-note-files/${fileId}`);
        const note = notes.value.find(n => n.id === noteId);
        if (note) note.files = note.files.filter(f => f.id !== fileId);
    } catch (e) { alert('Failed: ' + (e.response?.data?.message || e.message)); }
}

async function deleteLink(noteId, linkId) {
    if (!confirm('Delete this link?')) return;
    try {
        await axios.delete(`/api/v1/release-note-links/${linkId}`);
        const note = notes.value.find(n => n.id === noteId);
        if (note) note.links = note.links.filter(l => l.id !== linkId);
    } catch (e) { alert('Failed: ' + (e.response?.data?.message || e.message)); }
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
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
    if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'sheet';
    if (mimeType.includes('doc') || mimeType.includes('word')) return 'doc';
    return 'file';
}

const fileIconColors = {
    image: 'text-pink-500 bg-pink-50', pdf: 'text-red-500 bg-red-50',
    sheet: 'text-green-500 bg-green-50', doc: 'text-blue-500 bg-blue-50',
    file: 'text-gray-500 bg-gray-50',
};
</script>

<template>
    <Head :title="`Release Notes - ${project?.name}`" />

    <div class="space-y-4">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <Link href="/projects" class="hover:text-[#4e1a77]">Projects</Link>
            <span>/</span>
            <Link :href="`/projects/${project?.id}`" class="hover:text-[#4e1a77]">{{ project?.name }}</Link>
            <span>/</span>
            <span class="text-gray-900 font-medium">Release Notes</span>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Release Notes</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ project?.name }} &middot; {{ notes.length }} note(s)</p>
            </div>
            <button v-if="canCreate && !showCreateNote" @click="showCreateNote = true" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Release Note
            </button>
        </div>

        <!-- Create Form -->
        <div v-if="showCreateNote" class="rounded-xl border border-[#ddd0f7] bg-[#faf8ff] p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-[#4e1a77]">New Release Note</h3>
                <button @click="showCreateNote = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500 mb-1 block">Title *</label>
                <input v-model="newNote.title" placeholder="e.g. v2.1.0 Release" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500 mb-1 block">Description</label>
                <textarea v-model="newNote.description" rows="4" placeholder="Release description, changelog, notes..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500 mb-1 block">Attach Files</label>
                <input ref="newNoteFiles" type="file" multiple class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#f5f0ff] file:text-[#4e1a77] hover:file:bg-[#e8ddf0]" />
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500 mb-1 block">Links</label>
                <div class="flex gap-2 mb-2">
                    <input v-model="newLinkForm.label" placeholder="Label (optional)" class="flex-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    <input v-model="newLinkForm.url" placeholder="URL *" class="flex-[2] rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" @keydown.enter="addNoteLink" />
                    <button @click="addNoteLink" class="rounded-lg border border-[#4e1a77] px-3 py-1.5 text-xs font-medium text-[#4e1a77] hover:bg-[#f5f0ff]">Add</button>
                </div>
                <div v-if="newNote.links.length" class="flex flex-wrap gap-2">
                    <span v-for="(link, idx) in newNote.links" :key="idx" class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-700">
                        {{ link.label || link.url }}
                        <button @click="removeNoteLink(idx)" class="text-blue-400 hover:text-blue-700">&times;</button>
                    </span>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button @click="showCreateNote = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                <button @click="createReleaseNote" :disabled="!newNote.title || creatingNote" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] disabled:opacity-50">
                    {{ creatingNote ? 'Creating...' : 'Create' }}
                </button>
            </div>
        </div>

        <!-- Notes List -->
        <div v-for="note in notes" :key="note.id" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="px-6 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ note.title }}</h3>
                        <p class="text-xs text-gray-400 mt-1">
                            By {{ note.author_name }} <span class="capitalize">({{ note.author_role }})</span>
                            &middot; {{ formatDate(note.created_at) }}
                        </p>
                    </div>
                    <button v-if="canDelete" @click="deleteNote(note.id)" class="shrink-0 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors">Delete</button>
                </div>
                <p v-if="note.description" class="text-sm text-gray-700 mt-4 whitespace-pre-wrap leading-relaxed">{{ note.description }}</p>
            </div>

            <!-- Files Section -->
            <div v-if="note.files?.length" class="border-t border-gray-100 px-6 py-4">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-3">Attached Files</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div v-for="file in note.files" :key="file.id" class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded shrink-0" :class="fileIconColors[fileIcon(file.mime_type)]">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                            </div>
                            <div class="min-w-0">
                                <a :href="`/storage/${file.stored_path}`" target="_blank" class="text-sm font-medium text-gray-700 hover:text-[#4e1a77] truncate block">{{ file.original_name }}</a>
                                <p class="text-[10px] text-gray-400">{{ formatFileSize(file.size) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a :href="`/storage/${file.stored_path}`" target="_blank" class="text-xs text-[#4e1a77] hover:underline">Download</a>
                            <button v-if="canDelete" @click="deleteFile(note.id, file.id)" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Links Section -->
            <div v-if="note.links?.length" class="border-t border-gray-100 px-6 py-4">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-3">Links</p>
                <div class="flex flex-wrap gap-2">
                    <div v-for="link in note.links" :key="link.id" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm group hover:border-[#4e1a77] transition-colors">
                        <svg class="h-4 w-4 text-gray-400 group-hover:text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.556a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L4.343 8.06" /></svg>
                        <a :href="link.url" target="_blank" class="text-gray-700 hover:text-[#4e1a77]">{{ link.label || link.url }}</a>
                        <button v-if="canDelete" @click="deleteLink(note.id, link.id)" class="text-gray-300 hover:text-red-500">&times;</button>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="!notes.length" class="text-center text-sm text-gray-400 py-12">No release notes yet. Create one to get started.</p>
    </div>
</template>
