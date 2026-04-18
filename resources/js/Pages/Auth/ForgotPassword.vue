<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const status = computed(() => page.props.flash?.success);

const form = useForm({ email: '' });

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
    <div class="flex min-h-screen bg-white">
        <div class="flex w-full flex-col justify-center px-8 sm:px-12 lg:w-[45%] lg:px-16 xl:px-24">
            <div class="w-full max-w-md mx-auto">
                <img src="/ewards-logo.svg" alt="eWards" class="h-9 mb-8" />

                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 leading-tight">Forgot Password?</h1>
                <p class="mt-2 text-sm text-gray-500">Enter your registered email and we'll send a reset link.</p>

                <div v-if="status" class="mt-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="mt-8 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Email <span class="text-orange-500">*</span>
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            autofocus
                            placeholder="Enter your email"
                            class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-gray-50/50 px-4 py-3 text-sm focus:border-[#4e1a77] focus:bg-white focus:ring-1 focus:ring-[#4e1a77] transition-colors"
                            :class="{ 'border-red-400 bg-red-50': form.errors.email }"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center rounded-lg bg-[#4e1a77] px-4 py-3 text-sm font-bold uppercase tracking-wider text-white shadow-sm hover:bg-[#3d1560] disabled:opacity-50 transition-colors"
                    >
                        <svg v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        Send Reset Link
                    </button>

                    <p class="text-center text-sm text-gray-500">
                        Remember your password?
                        <a href="/login" class="font-semibold text-[#4e1a77] hover:text-[#3d1560]">Back to login</a>
                    </p>
                </form>
            </div>
        </div>

        <!-- Right branding panel (matches Login.vue) -->
        <div class="hidden lg:flex lg:w-[55%] items-center justify-center relative overflow-hidden bg-gradient-to-br from-[#e8d5f5] via-[#d4b8e8] to-[#c49ddb]">
            <div class="absolute -top-20 -right-20 h-80 w-80 rounded-full bg-[#4e1a77]/10 blur-3xl"></div>
            <div class="absolute -bottom-20 -left-20 h-60 w-60 rounded-full bg-[#f5941e]/10 blur-3xl"></div>
            <div class="relative z-10 max-w-lg px-12 text-center">
                <div class="rounded-2xl bg-white/80 backdrop-blur-sm p-8 shadow-lg">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[#4e1a77]/10 mx-auto mb-4">
                        <svg class="h-8 w-8 text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </div>
                    <p class="text-gray-700 text-sm leading-relaxed">Enter your email address and we'll send you a secure link to reset your password.</p>
                    <p class="mt-3 text-xs text-gray-400">The reset link expires in 60 minutes.</p>
                </div>
            </div>
        </div>
    </div>
</template>
