<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close', 'add-medication', 'dispensation-record', 'delete-medication', 'update-medication']);

const showDeleteConfirmation = ref(false);
const medicationIndexToDelete = ref(null);

// حالة التحكم في نافذة تعديل الدواء
const showEditModal = ref(false);
const editingIndex = ref(null);
const editingDosage = ref(''); // الجرعة الجديدة (كـ string للإدخال)

// فتح نافذة تأكيد الحذف
const handleDeleteMedication = (medIndex) => {
    medicationIndexToDelete.value = medIndex;
    showDeleteConfirmation.value = true;
};

// تأكيد الحذف وإصدار الحدث
const confirmDelete = () => {
    emit('delete-medication', medicationIndexToDelete.value);
    showDeleteConfirmation.value = false;
    medicationIndexToDelete.value = null;
};

// إلغاء الحذف وإغلاق النافذة
const cancelDelete = () => {
    showDeleteConfirmation.value = false;
    medicationIndexToDelete.value = null;
};

// فتح نافذة تعديل الدواء
const handleEditMedication = (medIndex) => {
    editingIndex.value = medIndex;
    // استخراج الرقم فقط من النص إذا كان النص يحتوي على "قرص واحد"
    const currentDosageText = props.patient.medications[medIndex].dosage;
    const numericDosage = parseInt(currentDosageText.match(/\d+/)?.[0] || '1');
    editingDosage.value = numericDosage;
    showEditModal.value = true;
};

// حفظ التعديل وتحديث البيانات محلياً (للجدول)
const saveEdit = () => {
    const newDosage = parseInt(editingDosage.value);
    if (newDosage > 0) {
        // إصدار الحدث للمكون الأب مع البيانات اللازمة
        emit('update-medication', { index: editingIndex.value, newDosage });

        // إغلاق النافذة
        showEditModal.value = false;
        editingIndex.value = null;
        editingDosage.value = '';
    } else {
        alert('يرجى إدخال جرعة صحيحة (رقم موجب).');
    }
};

// إلغاء التعديل وإغلاق النافذة
const cancelEdit = () => {
    showEditModal.value = false;
    editingIndex.value = null;
    editingDosage.value = '';
};

// حساب الكمية الشهرية المحدثة (computed للتحديث التلقائي)
const updatedMonthlyQuantity = computed(() => {
    const dosage = parseInt(editingDosage.value) || 0;
    return dosage * 30; // افتراض: 30 يوماً في الشهر
});
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full max-w-[95vw] sm:max-w-[780px] max-h-[95vh] sm:max-h-[90vh] overflow-y-auto transform transition-all duration-300 rtl"
        >
            <div
                class="flex items-center justify-between p-4 sm:pr-6 sm:pl-6 pb-2.5 bg-[#F6F4F0] rounded-t-xl sticky top-0 z-10 border-b border-[#B8D7D9]"
            >
                <h2 class="text-xl sm:text-2xl font-bold text-[#2E5077] flex items-center pt-1.5">
                    <Icon
                        icon="jam:write-f"
                        class="w-7 h-7 sm:w-9 sm:h-9 ml-2 text-[#2E5077]"
                    />
                    نموذج عرض حالة المريض
                </h2>

                <Button
                    @click="$emit('close')"
                    variant="ghost"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900 cursor-pointer"
                >
                    <Icon
                        icon="ri:close-large-fill"
                        class="w-6 h-6 text-[#2E5077] mt-3"
                    />
                </Button>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 space-y-4 sm:space-y-6">
                <!-- المعلومات الشخصية (غير معدلة) -->
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center">
                        <Icon icon="tabler:user" class="w-5 h-5 ml-2" />
                        المعلومات الشخصية
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">الرقم الوطني</Label>
                            <div class="relative w-full sm:max-w-xs">
                                <Input
                                    readonly
                                    :model-value="patient.nationalIdDisplay"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-white cursor-default focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">الإسم رباعي</Label>
                            <div class="relative w-full">
                                <Input
                                    readonly
                                    :model-value="patient.nameDisplay"
                                    class="h-9 text-right rounded-2xl w-81 border-[#B8D7D9] bg-white cursor-default focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">تاريخ الميلاد</Label>
                            <div class="relative w-full sm:max-w-xs">
                                <Input
                                    readonly
                                    :model-value="patient.birthDisplay"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-white cursor-default focus:ring-0"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- إسناد جرعة دوائية -->
                <div class="space-y-4 pt-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center">
                        <Icon icon="tabler:pill" class="w-5 h-5 ml-2" />
                        إسناد جرعة دوائية
                    </h3>

                    <div class="flex justify-end gap-3">
                        <Button @click="$emit('add-medication')" class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-28 rounded-[30px] transition-all duration-200 ease-in relative overflow-hiddentext-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                            <Icon icon="tabler:plus" class="w-4 h-4 ml-1" />
                            إضافة دواء
                        </Button>
                        <Button @click="$emit('dispensation-record')" class="inline-flex items-center h-11 px-[11px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]">
                            سجل الصرف
                        </Button>
                    </div>

                    <div v-if="patient.medications && patient.medications.length" class="overflow-x-auto bg-white rounded-xl shadow border border-gray-200">
                        <table class="table w-full text-right min-w-[700px] border-collapse">
                            <thead class="bg-[#9aced2] text-black text-sm">
                                <tr>
                                    <th class="p-3 border border-gray-300">إسم الدواء</th>
                                    <th class="p-3 border border-gray-300">الجرعة</th>
                                    <th class="p-3 border border-gray-300">الكمية الشهرية</th>
                                    <th class="p-3 border border-gray-300">تاريخ الإسناد</th>
                                    <th class="p-3 border border-gray-300">بواسطة</th>
                                    <th class="p-3 border border-gray-300">العملية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(med, medIndex) in patient.medications" :key="medIndex" class="hover:bg-gray-50 border-b border-gray-200">
                                    <td class="p-3 border border-gray-300">{{ med.drugName }}</td>
                                    <td class="p-3 border border-gray-300">{{ med.dosage }}</td>
                                    <td class="p-3 border border-gray-300">{{ med.monthlyQuantity }}</td>
                                    <td class="p-3 border border-gray-300">{{ med.assignmentDate }}</td>
                                    <td class="p-3 border border-gray-300">{{ med.assignedBy }}</td>
                                    <td class="p-3 border border-gray-300 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button @click="handleEditMedication(medIndex)">
                                                <Icon
                                                    icon="line-md:pencil"
                                                    class="w-5 h-5 text-yellow-500 cursor-pointer hover:scale-110 transition-transform"
                                                />
                                            </button>
                                            <button @click="handleDeleteMedication(medIndex)">
                                                <Icon
                                                    icon="material-symbols:delete-outline"
                                                    class="w-5 h-5 text-red-600 cursor-pointer hover:scale-110 transition-transform"
                                                />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-center text-gray-500 py-4">لا توجد جرعات دوائية مسجلة لهذا المريض.</p>
                </div>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 pt-2 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-[#B8D7D9]">
                <Button @click="$emit('close')" class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">موافق</Button>
                <Button @click="$emit('close')" variant="ghost" class="inline-flex items-center h-11 px-[19px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]">إلغاء</Button>
            </div>
        </div>
    </div>

    <!-- نافذة تأكيد الحذف (غير معدلة) -->
    <div
        v-if="showDeleteConfirmation"
        class="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="cancelDelete"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm z-[65]"
        ></div>

        <div
            class="relative bg-white rounded-xl shadow-2xl w-full sm:w-[400px] max-w-[90vw] p-6 sm:p-8 text-center rtl z-[70] transform transition-all duration-300 scale-100"
        >
            <div class="flex flex-col items-center">
                <Icon
                    icon="tabler:alert-triangle-filled"
                    class="w-16 h-16 text-yellow-500 mb-4"
                />
                <p class="text-xl font-bold text-[#2E5077] mb-3">
                    تأكيد عملية الحذف
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في حذف الدواء من هذا المريض؟ لا يمكن التراجع عن هذا الإجراء.
                </p>
                <div class="flex gap-4 justify-center w-full">
                    <button
                        @click="confirmDelete"
                        class="bg-yellow-500 text-white font-semibold py-2 px-6 rounded-full hover:bg-yellow-700 transition-colors duration-200"
                    >
                        حذف
                    </button>
                    <button
                        @click="cancelDelete"
                        class="bg-gray-300 text-[#374151] font-semibold py-2 px-6 rounded-full hover:bg-gray-400 transition-colors duration-200"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- نافذة تعديل الدواء (جديدة) -->
    <div
        v-if="showEditModal"
        class="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="cancelEdit"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm z-[65]"
        ></div>

        <div
            class="relative bg-white rounded-xl shadow-2xl w-full sm:w-[500px] max-w-[90vw] p-6 sm:p-8 rtl z-[70] transform transition-all duration-300 scale-100"
        >
            <div class="flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-[#2E5077] flex items-center">
                        <Icon icon="line-md:pencil" class="w-6 h-6 ml-2 text-yellow-500" />
                        تعديل الجرعة اليومية
                    </h3>
                    <Button
                        @click="cancelEdit"
                        variant="ghost"
                        class="p-2 h-auto text-gray-500 hover:text-gray-900"
                    >
                        <Icon icon="ri:close-large-fill" class="w-5 h-5" />
                    </Button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4">
                        <Label class="text-right font-medium text-[#2E5077]">الجرعة اليومية (عدد الحبوب أو الوحدات)</Label>
                        <Input
                            v-model="editingDosage"
                            type="number"
                            min="1"
                            class="h-9 text-right rounded-2xl border-[#B8D7D9] bg-white"
                            placeholder="أدخل الجرعة الجديدة"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <Label class="text-right font-medium text-[#2E5077]">الكمية الشهرية المحدثة</Label>
                        <Input
                            readonly
                            :model-value="updatedMonthlyQuantity"
                            class="h-9 text-right rounded-2xl border-[#B8D7D9] bg-gray-100 cursor-default"
                        />
                    </div>
                </div>

                <div class="flex gap-4 justify-end mt-6">
                    <Button
                        @click="saveEdit"
                        class="bg-[#4DA1A9] text-white font-semibold py-2 px-6 rounded-full hover:bg-[#5e8c90f9] transition-colors duration-200"
                    >
                        حفظ
                    </Button>
                    <Button
                        @click="cancelEdit"
                        variant="ghost"
                        class="bg-gray-300 text-[#374151] font-semibold py-2 px-6 rounded-full hover:bg-gray-400 transition-colors duration-200"
                    >
                        إلغاء
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>