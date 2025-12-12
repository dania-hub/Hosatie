<script setup>
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    patient: Object // Keeping the prop name as 'patient' to avoid breaking changes, though it represents an employee
});

const emit = defineEmits(['close']);
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

       <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform transition-all scale-100"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
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

            <div class="p-8 space-y-8">
                <!-- المعلومات الشخصية -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:user-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        المعلومات الشخصية
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">الرقم الوطني</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077] font-mono">
                                {{ patient.nationalIdDisplay }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">الاسم رباعي</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077]">
                                {{ patient.nameDisplay }}
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">تاريخ الميلاد</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077] font-mono">
                                {{ patient.birthDisplay }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- المعلومات الوظيفية -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:case-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        المعلومات الوظيفية
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">الدور الوظيفي</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077]">
                                {{ patient.role }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">القسم</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077]">
                                {{ patient.department || '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- معلومات الإتصال والحالة -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:phone-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات الإتصال والحالة
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">رقم الهاتف</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077] font-mono">
                                {{ patient.phone }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">البريد الإلكتروني</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077] font-mono">
                                {{ patient.email }}
                            </div>
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label class="text-gray-500 font-medium">حالة الحساب</Label>
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
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    @click="$emit('close')"
                    class="px-8 py-3 rounded-xl bg-[#2E5077] text-white font-bold hover:bg-[#1a3b5e] transition-all duration-200 shadow-lg shadow-[#2E5077]/20"
                >
                    موافق
                </button>
            </div>
        </div>
    </div>
</template>