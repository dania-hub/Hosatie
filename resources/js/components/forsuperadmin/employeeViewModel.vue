<script setup>
import { Icon } from "@iconify/vue";
import Input from "@/components/ui/input/Input.vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object // Keeping the prop name as 'patient' to avoid breaking changes, though it represents an employee
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
                        <Icon icon="solar:user-id-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    نموذج عرض حالة الموظف
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- National ID -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:card-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الرقم الوطني
                        </label>
                        <Input
                            readonly
                            :model-value="patient.nationalIdDisplay"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 font-mono font-bold text-[#2E5077]"
                        />
                    </div>

                    <!-- Name -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الاسم رباعي
                        </label>
                        <Input
                            readonly
                            :model-value="patient.name"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 font-bold text-[#2E5077]"
                        />
                    </div>

                    <!-- Role -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-id-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الدور الوظيفي
                        </label>
                        <Input
                            readonly
                            :model-value="patient.role"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 font-bold text-[#2E5077]"
                        />
                    </div>
    <!-- Birth Date -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:calendar-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            تاريخ الميلاد
                        </label>
                        <Input
                            readonly
                            :model-value="patient.birthDisplay"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 font-mono font-bold text-[#2E5077]"
                        />
                    </div>
                    <!-- Hospital -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hospital-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المستشفى
                        </label>
                        <Input
                            readonly
                            :model-value="patient.hospital || '-'"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 font-bold text-[#2E5077]"
                        />
                    </div>

                

                  

                    <!-- Phone -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف
                        </label>
                        <Input
                            readonly
                            :model-value="patient.phone"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 font-mono font-bold text-[#2E5077]"
                        />
                    </div>

                    <!-- Email -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:letter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            البريد الإلكتروني
                        </label>
                        <Input
                            readonly
                            :model-value="patient.email"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 font-mono font-bold text-[#2E5077]"
                        />
                    </div>

                    <!-- Account Status -->
                    <div class="space-y-2 md:col-span-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:shield-user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            حالة الحساب
                        </label>
                        <div class="mt-1">
                            <span
                                :class="[
                                    'px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 w-fit',
                                    patient.isActive
                                        ? 'bg-green-100 text-green-700 border border-green-200'
                                        : 'bg-red-100 text-red-700 border border-red-200',
                                ]"
                            >
                                <Icon :icon="patient.isActive ? 'solar:check-circle-bold' : 'solar:close-circle-bold'" class="w-5 h-5" />
                                {{ patient.isActive ? "مفعل" : "معطل" }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100">
                <button
                    @click="$emit('close')"
                    class="px-8 py-3 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-bold hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2"
                >
                    <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                    موافق
                </button>
            </div>
        </div>
    </div>
</template>