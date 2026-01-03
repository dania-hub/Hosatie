<script setup>
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: Boolean,
    department: Object
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100" dir="rtl">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:buildings-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    عرض بيانات القسم
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8 overflow-y-auto max-h-[70vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Department ID -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:file-text-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم القسم
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="department.id || 'غير متوفر'" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Department Name -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:case-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم القسم
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="department.name || 'غير متوفر'" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Manager Name -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم المدير
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="department.managerName || 'بدون مدير'" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Manager ID -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:card-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم ملف المدير
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="department.managerId || 'غير متوفر'" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Manager Email -->
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:letter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            البريد الإلكتروني للمدير
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="department.managerEmail || 'غير متوفر'" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-default focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <!-- Account Status -->
                    <div class="space-y-2 md:col-span-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:shield-user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            حالة القسم
                        </label>
                        <div class="mt-1">
                            <span
                                :class="[
                                    'px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 w-fit',
                                    department.isActive
                                        ? 'bg-green-100 text-green-700 border border-green-200'
                                        : 'bg-red-100 text-red-700 border border-red-200',
                                ]"
                            >
                                <Icon :icon="department.isActive ? 'solar:check-circle-bold' : 'solar:close-circle-bold'" class="w-5 h-5" />
                                {{ department.isActive ? "مفعل" : "معطل" }}
                            </span>
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
