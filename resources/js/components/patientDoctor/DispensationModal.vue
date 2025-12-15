<script setup>
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object,
    dispensationHistory: Array
});

const emit = defineEmits(['close']);
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:history-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    سجل الصرف - {{ patient.nameDisplay }}
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-6">
                <div v-if="dispensationHistory.length > 0" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full text-right">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="p-4 text-sm font-bold text-[#2E5077]">إسم الدواء</th>
                                <th class="p-4 text-sm font-bold text-[#2E5077]">الكمية</th>
                                <th class="p-4 text-sm font-bold text-[#2E5077]">تاريخ الصرف</th>
                                <th class="p-4 text-sm font-bold text-[#2E5077]">بواسطة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="(item, index) in dispensationHistory" :key="index" class="hover:bg-gray-50/50 transition-colors">
                                <td class="p-4 font-medium text-gray-700">{{ item.drugName || '-' }}</td>
                                <td class="p-4 text-gray-600">
                                    <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-sm font-medium">
                                        {{ item.quantity || '-' }}
                                    </span>
                                </td>
                                <td class="p-4 text-gray-500 text-sm">{{ item.date || '-' }}</td>
                                <td class="p-4 text-gray-500 text-sm">{{ item.assignedBy || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div v-else class="py-12 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
                    <Icon icon="solar:history-bold-duotone" class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-gray-500 font-medium">لا يوجد سجل صرف لهذا المريض</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="$emit('close')" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</template>