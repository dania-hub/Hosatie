<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import Input from "@/components/ui/input/Input.vue";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close', 'add-medication', 'dispensation-record', 'edit-medication', 'delete-medication']);

// حالة التحكم في نافذة تأكيد الحذف
const showDeleteConfirmation = ref(false);
const medicationIndexToDelete = ref(null);

// حالة التحكم في نافذة تعديل الدواء
const showEditModal = ref(false);
const editingIndex = ref(null);
const editingDosage = ref('');

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
    // استخراج الرقم فقط من الجرعة (مثال: "2 حبة يومياً" أو "2.5 حبة يومياً" ← "2" أو "2.5")
    const dosageText = props.patient.medications[medIndex].dosage;
    const dosageMatch = dosageText.match(/(\d+(?:\.\d+)?)/);
    const dosageNumber = dosageMatch ? dosageMatch[1] : '';
    editingDosage.value = dosageNumber;
    showEditModal.value = true;
};

// حفظ التعديل
const saveEdit = () => {
    const newDosage = parseFloat(editingDosage.value);
    if (!isNaN(newDosage) && newDosage > 0) {
        emit('edit-medication', editingIndex.value, newDosage);
        showEditModal.value = false;
        editingIndex.value = null;
        editingDosage.value = '';
    } else {
        alert('يرجى إدخال جرعة صحيحة (رقم موجب).');
    }
};

// إلغاء التعديل
const cancelEdit = () => {
    showEditModal.value = false;
    editingIndex.value = null;
    editingDosage.value = '';
};

// حساب الكمية الشهرية المحدثة
const updatedMonthlyQuantity = computed(() => {
    const dosage = parseFloat(editingDosage.value) || 0;
    return Math.round(dosage * 30);
});
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:stethoscope-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    ملف المريض الطبي
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8">
                
                <!-- Patient Info Section -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:user-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        البيانات الشخصية
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">الاسم الرباعي</label>
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.nameDisplay }}</div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">الرقم الوطني</label>
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.nationalIdDisplay }}</div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">تاريخ الميلاد</label>
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.birthDisplay }}</div>
                        </div>
                    </div>
                </div>

                <!-- Medications Section -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:pill-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            الأدوية والجرعات
                        </h3>
                        <div class="flex gap-3">
                            <button 
                                @click="$emit('dispensation-record')"
                                class="px-4 py-2 rounded-xl text-[#2E5077] bg-white border border-gray-200 font-medium hover:bg-gray-50 transition-colors duration-200 flex items-center gap-2 shadow-sm"
                            >
                                <Icon icon="solar:history-bold-duotone" class="w-5 h-5" />
                                سجل الصرف
                            </button>
                            <button 
                                @click="$emit('add-medication')"
                                class="px-4 py-2 rounded-xl text-white bg-[#4DA1A9] font-medium hover:bg-[#3a8c94] transition-colors duration-200 flex items-center gap-2 shadow-lg shadow-[#4DA1A9]/20"
                            >
                                <Icon icon="solar:add-circle-bold" class="w-5 h-5" />
                                إضافة دواء
                            </button>
                        </div>
                    </div>

                    <div v-if="patient.medications && patient.medications.length" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <table class="w-full text-right">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">إسم الدواء</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">الجرعة</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">الكمية الشهرية</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">تاريخ الإسناد</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">بواسطة</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077] text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="(med, medIndex) in patient.medications" :key="medIndex" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 font-medium text-gray-700">{{ med.drugName }}</td>
                                    <td class="p-4 text-gray-600">
                                        <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-sm font-medium">
                                            {{ med.dosage }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-600">{{ med.monthlyQuantity || '-' }}</td>
                                    <td class="p-4 text-gray-500 text-sm">{{ med.assignmentDate || '-' }}</td>
                                    <td class="p-4 text-gray-500 text-sm">{{ med.assignedBy || '-' }}</td>
                                    <td class="p-4">
                                        <div class="flex justify-center gap-2">
                                            <button 
                                                @click="handleEditMedication(medIndex)"
                                                class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors"
                                                title="تعديل"
                                            >
                                                <Icon icon="solar:pen-bold-duotone" class="w-5 h-5" />
                                            </button>
                                            <button 
                                                @click="handleDeleteMedication(medIndex)"
                                                class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                                title="حذف"
                                            >
                                                <Icon icon="solar:trash-bin-trash-bold-duotone" class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center py-12 bg-white rounded-2xl border border-dashed border-gray-200">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                            <Icon icon="solar:pill-broken" class="w-8 h-8 text-gray-400" />
                        </div>
                        <p class="text-gray-500 font-medium">لا توجد أدوية مسجلة لهذا المريض</p>
                        <button 
                            @click="$emit('add-medication')"
                            class="mt-4 text-[#4DA1A9] hover:underline text-sm font-medium"
                        >
                            إضافة دواء جديد
                        </button>
                    </div>
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

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirmation" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="cancelDelete">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon icon="solar:trash-bin-trash-bold-duotone" class="w-8 h-8 text-red-500" />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">حذف الدواء</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من حذف هذا الدواء من ملف المريض؟
                    <br>
                    <span class="text-sm text-red-500">لا يمكن التراجع عن هذا الإجراء</span>
                </p>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="cancelDelete" 
                    class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="confirmDelete" 
                    class="flex-1 px-4 py-2.5 rounded-xl bg-red-500 text-white font-medium hover:bg-red-600 transition-colors duration-200 shadow-lg shadow-red-500/20"
                >
                    حذف نهائي
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="cancelEdit">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="bg-[#2E5077] px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <Icon icon="solar:pen-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                    تعديل الجرعة
                </h3>
                <button @click="cancelEdit" class="text-white/70 hover:text-white">
                    <Icon icon="mingcute:close-fill" class="w-5 h-5" />
                </button>
            </div>
            
            <div class="p-6 space-y-6">
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-[#2E5077]">الجرعة اليومية (عدد الحبوب)</label>
                    <Input
                        v-model="editingDosage"
                        type="number"
                        min="1"
                        class="bg-white border-gray-200 focus:border-[#4DA1A9]"
                        placeholder="أدخل العدد"
                    />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-semibold text-[#2E5077]">الكمية الشهرية المتوقعة</label>
                    <div class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-500 font-medium">
                        {{ updatedMonthlyQuantity }}
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="cancelEdit" 
                    class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="saveEdit" 
                    class="flex-1 px-4 py-2.5 rounded-xl bg-[#2E5077] text-white font-medium hover:bg-[#1a2f4d] transition-colors duration-200 shadow-lg shadow-[#2E5077]/20"
                >
                    حفظ التغييرات
                </button>
            </div>
        </div>
    </div>
</template>