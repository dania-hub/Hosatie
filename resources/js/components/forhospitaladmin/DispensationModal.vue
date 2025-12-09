<script setup>
import { Icon } from "@iconify/vue";
import { onMounted, ref } from "vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object,
    dispensationHistory: Array
});

const emit = defineEmits(['close']);

// عرض البيانات الممررة كـ prop
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
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full max-w-[95vw] sm:max-w-[700px] max-h-[95vh] sm:max-h-[90vh] overflow-y-auto transform transition-all duration-300 rtl"
            dir="rtl"
        >
            <div
                class="flex items-center justify-between p-4 sm:pr-6 sm:pl-6 pb-2.5 bg-[#F6F4F0] rounded-t-xl sticky top-0 z-10 border-b border-[#B8D7D9]"
            >
                <h2 class="text-xl sm:text-2xl font-bold text-[#2E5077] flex items-center pt-1.5">
                    <Icon icon="tabler:history" class="w-6 h-6 sm:w-8 sm:h-8 ml-2 text-[#2E5077]" />
                    سجل الصرف - {{ patient.nameDisplay }}
                </h2>

                <button
                    @click="$emit('close')"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900 cursor-pointer"
                >
                    <Icon icon="ri:close-large-fill" class="w-6 h-6 text-[#2E5077] mt-3" />
                </button>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 space-y-6">
                <div v-if="dispensationHistory.length > 0" class="overflow-x-auto bg-white rounded-xl shadow border border-gray-200">
                    <table class="table w-full text-right min-w-[500px] border-collapse">
                        <thead class="bg-[#9aced2] text-black text-sm">
                            <tr>
                                <th class="p-3 border border-gray-300">إسم الدواء</th>
                                <th class="p-3 border border-gray-300">الكمية</th>
                                <th class="p-3 border border-gray-300">تاريخ الصرف</th>
                                <th class="p-3 border border-gray-300">بواسطة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in dispensationHistory" :key="index" class="hover:bg-gray-50 border-b border-gray-200">
                                <td class="p-3 border border-gray-300">{{ item.drugName }}</td>
                                <td class="p-3 border border-gray-300">{{ item.quantity }}</td>
                                <td class="p-3 border border-gray-300">{{ item.date }}</td>
                                <td class="p-3 border border-gray-300">{{ item.assignedBy }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-center text-gray-500 py-4">لا يوجد سجل صرف لهذا المريض.</p>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 pt-2 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-[#B8D7D9]">
                <button
                    @click="$emit('close')" 
                    class="inline-flex h-11 items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                >
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</template>