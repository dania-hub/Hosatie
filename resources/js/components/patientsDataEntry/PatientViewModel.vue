<script setup>
import { Icon } from "@iconify/vue";
import Input from "@/components/ui/input/Input.vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:eye-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    عرض بيانات المريض
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- File Number -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:file-text-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الملف
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="patient.fileNumber || 'غير متوفر'" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- National ID -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:card-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الرقم الوطني
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="patient.nationalId" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Full Name -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الاسم الرباعي
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="patient.name" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Birth Date -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:calendar-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            تاريخ الميلاد
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="patient.birth" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="patient.phone" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:letter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            البريد الإلكتروني
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="patient.email && !patient.email.includes('@example.com') ? patient.email : 'غير متوفر'" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100">
                <button 
                    @click="$emit('close')" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                >
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</template>