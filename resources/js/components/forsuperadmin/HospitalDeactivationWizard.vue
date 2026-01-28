<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

const props = defineProps({
    isOpen: Boolean,
    hospital: Object,
});

const emit = defineEmits(["close", "success"]);

// API settings
const api = axios.create({
    baseURL: '/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('auth_token') || localStorage.getItem('token')}`
    }
});

// State
const step = ref(1); // 1: Initial Check, 2: Actions, 3: Managers, 4: Finalizing
const loading = ref(true);
const processing = ref(false);
const data = ref(null);
const error = ref(null);

// Form Data for Deactivation
const formData = ref({
    target_hospital_id: null,
    target_employee_entity_id: null,
    deactivate_employees: false,
    manager_action: 'deactivate',
    manager_new_role: 'data_entry',
    store_keeper_action: 'deactivate',
    store_keeper_new_role: 'data_entry',
});

// Fetch deactivation data
const fetchData = async () => {
    if (!props.hospital?.id) return;
    
    loading.value = true;
    error.value = null;
    try {
        const response = await api.get(`/super-admin/hospitals/${props.hospital.id}/deactivation-data`);
        data.value = response.data.data;
        
        // Default target hospital (first in list)
        if (data.value.alternativeHospitals && data.value.alternativeHospitals.length > 0) {
            formData.value.target_hospital_id = data.value.alternativeHospitals[0].id;
            formData.value.target_employee_entity_id = data.value.alternativeHospitals[0].id;
        }
    } catch (err) {
        console.error("Error fetching deactivation data:", err);
        error.value = err.response?.data?.message || "فشل في جلب بيانات التحقق.";
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    if (props.isOpen) {
        fetchData();
    }
});

// Watch for opening the wizard
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        // Reset state
        step.value = 1;
        error.value = null;
        formData.value = {
            target_hospital_id: null,
            target_employee_entity_id: null,
            deactivate_employees: false,
            manager_action: 'deactivate',
            manager_new_role: 'data_entry',
            store_keeper_action: 'deactivate',
            store_keeper_new_role: 'data_entry',
        };
        fetchData();
    }
});

const handleClose = () => {
    if (!processing.value) {
        emit("close");
    }
};

const nextStep = () => {
    if (step.value === 1 && data.value.hasBlockers) return;
    step.value++;
};

const prevStep = () => {
    step.value--;
};

const finalizeDeactivation = async () => {
    processing.value = true;
    error.value = null;
    try {
        const response = await api.patch(`/super-admin/hospitals/${props.hospital.id}/deactivate`, formData.value);
        emit("success", response.data.message);
        emit("close");
    } catch (err) {
        console.error("Error deactivating hospital:", err);
        error.value = err.response?.data?.message || "فشل في إكمال عملية الإيقاف.";
    } finally {
        processing.value = false;
    }
};

const canMoveToNext = computed(() => {
    if (step.value === 1) return !loading.value && !data.value?.hasBlockers;
    if (step.value === 2) return formData.value.target_hospital_id !== null;
    return true;
});

</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-[150] flex items-center justify-center p-4">
        <div @click="handleClose" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh] rtl">
            <!-- Header -->
            <div class="bg-[#2E5077] p-5 text-white flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white/10 rounded-lg">
                        <Icon icon="solar:hospital-broken" class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">معالج إيقاف تفعيل المستشفى</h3>
                        <p class="text-xs text-blue-100 opacity-80">{{ props.hospital.name }}</p>
                    </div>
                </div>
                <button @click="handleClose" class="p-2 hover:bg-white/10 rounded-full transition-colors">
                    <Icon icon="material-symbols:close" class="w-6 h-6" />
                </button>
            </div>

            <!-- Steps Progress -->
            <div class="bg-gray-50 border-b border-gray-200 p-4">
                <div class="flex items-center justify-center gap-2 max-w-md mx-auto">
                    <div v-for="i in 3" :key="i" class="flex items-center">
                        <div :class="[
                            'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300',
                            step >= i ? 'bg-[#4DA1A9] text-white' : 'bg-gray-200 text-gray-500'
                        ]">
                            {{ i }}
                        </div>
                        <div v-if="i < 3" :class="[
                            'w-12 h-1 transition-all duration-300',
                            step > i ? 'bg-[#4DA1A9]' : 'bg-gray-200'
                        ]"></div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-6 bg-white min-h-[300px]">
                
                <!-- Loading State -->
                <div v-if="loading" class="flex flex-col items-center justify-center h-full py-10">
                    <Icon icon="line-md:loading-twotone-loop" class="w-12 h-12 text-[#4DA1A9] mb-4" />
                    <p class="text-gray-600">جاري فحص حالة المستشفى والعمليات المرتبطة...</p>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="bg-red-50 border border-red-200 p-4 rounded-xl flex items-start gap-4">
                    <Icon icon="material-symbols:error-outline" class="w-8 h-8 text-red-500 flex-shrink-0" />
                    <div>
                        <h4 class="font-bold text-red-800">حدث خطأ</h4>
                        <p class="text-red-700 text-sm mt-1">{{ error }}</p>
                        <button @click="fetchData" class="mt-3 text-red-800 font-bold text-xs underline">إعادة المحاولة</button>
                    </div>
                </div>

                <!-- Step 1: Preliminary Checks -->
                <div v-else-if="step === 1" class="space-y-6 animate-fade-in">
                    <div v-if="data.hasBlockers" class="bg-amber-50 border border-amber-200 p-5 rounded-xl">
                        <div class="flex items-center gap-3 text-amber-800 mb-4">
                            <Icon icon="solar:shield-warning-bold" class="w-8 h-8" />
                            <h4 class="text-lg font-bold">يوجد عمليات نشطة تمنع الإيقاف</h4>
                        </div>
                        <ul class="space-y-2">
                            <li v-for="(blocker, index) in data.blockers" :key="index" class="flex items-center gap-2 text-amber-700 text-sm">
                                <Icon icon="ph:dot-bold" class="w-4 h-4" />
                                {{ blocker }}
                            </li>
                        </ul>
                        <p class="mt-4 text-sm text-gray-600">يجب إكمال أو إلغاء هذه الطلبات قبل التمكن من إيقاف تفعيل المستشفى.</p>
                    </div>

                    <div v-else>
                        <div class="flex items-center gap-3 text-[#2E5077] mb-6">
                            <Icon icon="solar:check-circle-bold" class="w-8 h-8 text-green-500" />
                            <h4 class="text-lg font-bold">نجح فحص الإيقاف الأولي</h4>
                        </div>
                        
                        <p class="text-gray-600 mb-6 text-sm">تم العثور على الكيانات التالية المرتبطة بالمستشفى. سيقوم المعالج بمساعدتك في إعادة توزيعها:</p>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 flex items-center gap-4">
                                <div class="p-3 bg-blue-500/10 rounded-lg text-blue-600">
                                    <Icon icon="solar:users-group-two-rounded-bold" class="w-6 h-6" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-blue-700">{{ data.counts.patients }}</p>
                                    <p class="text-xs text-blue-600 uppercase font-bold">مريض مسجل</p>
                                </div>
                            </div>
                            <div class="bg-teal-50 p-4 rounded-xl border border-teal-100 flex items-center gap-4">
                                <div class="p-3 bg-teal-500/10 rounded-lg text-teal-600">
                                    <Icon icon="solar:user-hand-up-bold" class="w-6 h-6" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-teal-700">{{ data.counts.employees }}</p>
                                    <p class="text-xs text-teal-600 uppercase font-bold">موظف نشط</p>
                                </div>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-xl border border-purple-100 flex items-center gap-4">
                                <div class="p-3 bg-purple-500/10 rounded-lg text-purple-600">
                                    <Icon icon="solar:notification-lines-remove-bold" class="w-6 h-6" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-purple-700">{{ data.counts.complaints }}</p>
                                    <p class="text-xs text-purple-600 uppercase font-bold">شكاوى مفتوحة</p>
                                </div>
                            </div>
                            <div class="bg-orange-50 p-4 rounded-xl border border-orange-100 flex items-center gap-4">
                                <div class="p-3 bg-orange-500/10 rounded-lg text-orange-600">
                                    <Icon icon="solar:transfer-horizontal-bold" class="w-6 h-6" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-orange-700">{{ data.counts.transfers }}</p>
                                    <p class="text-xs text-orange-600 uppercase font-bold">طلبات نقل معلقة</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Redistribution -->
                <div v-else-if="step === 2" class="space-y-8 animate-fade-in">
                    <!-- Complaints & Transfers Automatic Info -->
                    <div class="bg-gray-50 border border-gray-200 p-4 rounded-xl">
                        <div class="flex items-center gap-3 text-gray-700 mb-2">
                            <Icon icon="solar:magic-stick-bold" class="w-5 h-5" />
                            <h4 class="font-bold">إجـراءات تلقـائية</h4>
                        </div>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• سيتم إغلاق جميع الشكاوى الـ ({{ data.counts.complaints }}) مع رد آلي توضيحي.</li>
                            <li>• سيتم إلغاء جميع طلبات النقل الـ ({{ data.counts.transfers }}).</li>
                        </ul>
                    </div>

                    <!-- Relocate Patients -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-3 text-[#2E5077]">
                            <Icon icon="solar:user-speak-bold" class="w-6 h-6" />
                            <h4 class="font-bold">إعادة توزيع المرضى ({{ data.counts.patients }})</h4>
                        </div>
                        <div class="form-control w-full">
                            <label class="label">
                                <span class="label-text font-bold">اختر المستشفى البديل لنقل ملفات المرضى:</span>
                            </label>
                            <select v-model="formData.target_hospital_id" class="select select-bordered w-full rounded-xl bg-gray-50 focus:border-[#4DA1A9]">
                                <option disabled :value="null">اختر مستشفى من القائمة...</option>
                                <option v-for="h in data.alternativeHospitals" :key="h.id" :value="h.id">
                                    {{ h.name }}
                                </option>
                            </select>
                            <p class="text-[10px] text-gray-500 mt-1 mr-2">* تظهر فقط المستشفيات النشطة في مدينة ({{ data.hospital.city }})</p>
                        </div>
                    </div>

                    <!-- Relocate Employees -->
                    <div class="space-y-4 border-t pt-6">
                        <div class="flex items-center gap-3 text-[#2E5077]">
                            <Icon icon="solar:users-group-bold" class="w-6 h-6" />
                            <h4 class="font-bold">إعادة توزيع الموظفين ({{ data.counts.employees }})</h4>
                        </div>
                        
                        <div class="flex flex-col gap-3">
                            <label class="flex items-center gap-3 bg-gray-50 p-4 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-colors">
                                <input type="radio" :value="false" v-model="formData.deactivate_employees" class="radio radio-primary" />
                                <div>
                                    <p class="font-bold text-sm">نقل الموظفين إلى جهة أخرى</p>
                                    <p class="text-xs text-gray-500">سيتم تغيير تبعية جميع الموظفين النشطين إلى الجهة المختارة.</p>
                                </div>
                            </label>

                            <div v-if="!formData.deactivate_employees" class="mr-10 transition-all">
                                <select v-model="formData.target_employee_entity_id" class="select select-sm select-bordered w-full rounded-lg bg-white">
                                    <option v-for="h in data.alternativeHospitals" :key="h.id" :value="h.id">
                                        {{ h.name }}
                                    </option>
                                </select>
                            </div>

                            <label class="flex items-center gap-3 bg-gray-50 p-4 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-colors">
                                <input type="radio" :value="true" v-model="formData.deactivate_employees" class="radio radio-error" />
                                <div>
                                    <p class="font-bold text-sm text-red-600">تعطيل حسابات جميع الموظفين</p>
                                    <p class="text-xs text-gray-500">سيتم إيقاف تفعيل حسابات الجميع مؤقتاً (يحتاجون تنشيط يدوي لاحقاً).</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Managers -->
                <div v-else-if="step === 3" class="space-y-6 animate-fade-in">
                    <div class="flex items-center gap-3 text-[#2E5077] mb-4">
                        <Icon icon="solar:shield-user-bold" class="w-6 h-6" />
                        <h4 class="text-lg font-bold">التعامل مع حسابات الإدارة</h4>
                    </div>

                    <!-- Hospital Admin -->
                    <div v-if="data.managers.hospitalAdmin" class="bg-gray-50 p-5 rounded-2xl border border-gray-200">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                {{ data.managers.hospitalAdmin.name.charAt(0) }}
                            </div>
                            <div>
                                <p class="font-bold text-gray-800">{{ data.managers.hospitalAdmin.name }}</p>
                                <p class="text-xs text-gray-500">مدير المستشفى</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" value="deactivate" v-model="formData.manager_action" class="radio radio-xs radio-primary" />
                                <span>إيقاف تفعيل الحساب (موصى به عند مغادرة العمل)</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" value="change_role" v-model="formData.manager_action" class="radio radio-xs radio-primary" />
                                <span>تغيير الدور ونقله إلى المستشفى البديل</span>
                            </label>

                            <div v-if="formData.manager_action === 'change_role'" class="mr-6 transition-all">
                                <label class="label pt-0">
                                    <span class="label-text-alt font-bold text-gray-500">اختر الدور الجديد:</span>
                                </label>
                                <select v-model="formData.manager_new_role" class="select select-xs select-bordered w-full max-w-xs rounded-lg bg-white text-gray-700 border-gray-300 focus:border-[#4DA1A9]">
                                    <option value="doctor">طبيب</option>
                                    <option value="pharmacist">صيدلي</option>
                                    <option value="data_entry">مدخل بيانات</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Warehouse Manager -->
                    <div v-if="data.managers.warehouseManager" class="bg-gray-50 p-5 rounded-2xl border border-gray-200">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center text-teal-600 font-bold">
                                {{ data.managers.warehouseManager.name.charAt(0) }}
                            </div>
                            <div>
                                <p class="font-bold text-gray-800">{{ data.managers.warehouseManager.name }}</p>
                                <p class="text-xs text-gray-500">مسؤول المخزن</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" value="deactivate" v-model="formData.store_keeper_action" class="radio radio-xs radio-primary" />
                                <span>إيقاف تفعيل الحساب</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" value="change_role" v-model="formData.store_keeper_action" class="radio radio-xs radio-primary" />
                                <span>تغيير الدور ونقله إلى المستشفى البديل</span>
                            </label>

                            <div v-if="formData.store_keeper_action === 'change_role'" class="mr-6 transition-all">
                                <label class="label pt-0">
                                    <span class="label-text-alt font-bold text-gray-500">اختر الدور الجديد:</span>
                                </label>
                                <select v-model="formData.store_keeper_new_role" class="select select-xs select-bordered w-full max-w-xs rounded-lg bg-white text-gray-700 border-gray-300 focus:border-[#4DA1A9]">
                                    <option value="hospital_admin">مدير مستشفى</option>
                                    <option value="warehouse_manager">مسؤول مخزن</option>
                                    <option value="department_head">رئيس قسم</option>
                                    <option value="doctor">طبيب</option>
                                    <option value="pharmacist">صيدلي</option>
                                    <option value="data_entry">مدخل بيانات</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-xl border border-blue-100">
                        <p class="text-xs text-blue-800 leading-relaxed">
                            <Icon icon="tabler:info-circle" class="inline w-3 h-3 ml-1" />
                            <strong>تغيير الدور:</strong> سيتم تحويل المستخدم إلى الدور المختار ونقله إلى المستشفى المختار في الخطوة السابقة، مما يجعله يفقد صلاحياته الإدارية في المستشفى الحالي ولكنه يبقى نشطاً في النظام.
                        </p>
                    </div>
                </div>

            </div>

            <!-- Footer / Buttons -->
            <div class="p-6 bg-gray-50 border-t border-gray-200 flex justify-between gap-4">
                <button 
                    v-if="step > 1 && !processing" 
                    @click="prevStep" 
                    class="px-8 py-2 bg-white border border-gray-300 rounded-xl font-bold text-gray-600 hover:bg-gray-100 transition-all flex items-center justify-center min-w-[120px]"
                >
                    السابق
                </button>
                
                <div class="flex-1"></div>

                <div class="flex gap-3">
                    <button 
                        @click="handleClose" 
                        class="px-8 py-2 bg-transparent text-gray-500 font-bold hover:text-gray-800 transition-all"
                        v-if="!processing"
                    >
                        إلغاء
                    </button>

                    <button 
                        v-if="step < 3"
                        @click="nextStep" 
                        :disabled="!canMoveToNext"
                        class="px-8 py-2 bg-[#4DA1A9] text-white rounded-xl font-bold hover:bg-[#3a8c94] transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center min-w-[120px] shadow-lg shadow-teal-500/20"
                    >
                        {{ step === 1 ? 'بدء الإيقاف' : 'المتابعة' }}
                    </button>

                    <button 
                        v-else
                        @click="finalizeDeactivation" 
                        :disabled="processing"
                        class="px-10 py-2 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition-all disabled:opacity-50 flex items-center gap-2 shadow-lg shadow-red-500/20"
                    >
                        <template v-if="processing">
                            <Icon icon="line-md:loading-twotone-loop" class="w-5 h-5" />
                            جاري التنفيذ...
                        </template>
                        <template v-else>
                            إنهاء وإيقاف المستشفى
                            <Icon icon="solar:shield-check-bold" class="w-5 h-5" />
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Base custom styles to match the system's aesthetic */
.select {
    height: 2.75rem;
    min-height: 0;
}
.radio {
    border-color: #4DA1A9;
}
.radio-primary {
    --p: 77, 161, 169;
}
</style>
