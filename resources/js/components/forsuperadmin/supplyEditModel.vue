<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import axios from "axios";

// إعداد axios
const api = axios.create({
    baseURL: '/api',
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة interceptor لإضافة التوكن تلقائيًا
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

const props = defineProps({
    isOpen: Boolean,
    supplier: Object,
    availableManagers: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const editForm = ref({
    id: "",
    name: "",
    code: "",
    address: "",
    city: "",
    phone: "",
    managerId: "",
});

// البيانات الأصلية للمقارنة
const originalEditForm = ref({});

// أخطاء التحقق
const editErrors = ref({
    name: false,
    code: false,
    city: false,
    phone: false,
});

// قائمة المدراء (يتم جلبها من API)
const managersList = ref([]);
const loadingManagers = ref(false);

const fetchManagers = async () => {
    loadingManagers.value = true;
    try {
        // جلب كل مدراء الموردين
        const response = await api.get('/super-admin/users?type=supplier_admin');
        if (response.data && response.data.data) {
            const currentSupplierId = props.supplier?.id;
            managersList.value = response.data.data
                .filter(u => {
                    // التحقق من الحالة
                    if (u.status !== 'active' && u.status !== 'pending_activation') return false;

                    // إذا كان المستخدم معيناً لمورد
                    if (u.supplier) {
                        // إظهاره فقط إذا كان معيناً لهذا المورد الحالي
                        if (currentSupplierId) {
                            return u.supplier.id == currentSupplierId;
                        }
                        // إذا لم يكن لدينا معرف مورد حالي (إنشاء جديد مثلاً)، نستبعد المعينين فعلاً
                        return false;
                    }

                    // إذا لم يكن معيناً لأي مورد (متاح)
                    return true;
                })
                .map(u => ({
                    id: u.id,
                    name: u.fullName || u.full_name || u.name,
                    email: u.email,
                    status: u.status
                }));
        }
    } catch (error) {
        console.error("Error fetching managers:", error);
    } finally {
        loadingManagers.value = false;
    }
};

// حالة التحقق من رقم الهاتف
const phoneExists = ref(false);
const checkingPhone = ref(false);
const phoneMessage = ref("");

// حالة نافذة التأكيد
const isEditConfirmationModalOpen = ref(false);

// التحقق من صحة النموذج
const validateEditForm = () => {
    let isValid = true;
    const data = editForm.value;

    editErrors.value.name = !data.name || data.name.trim().length < 2;
    if (editErrors.value.name) isValid = false;

    editErrors.value.code = !data.code || data.code.trim().length < 1;
    if (editErrors.value.code) isValid = false;

    editErrors.value.city = !data.city || (data.city !== 'طرابلس' && data.city !== 'بنغازي');
    if (editErrors.value.city) isValid = false;

    // رقم الهاتف اختياري ولكن إذا تم إدخاله يجب أن يكون صالحاً
    if (data.phone && data.phone.trim() !== "") {
        const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
        const isValidFormat = phoneRegex.test(data.phone.trim());
        editErrors.value.phone = !isValidFormat;
        if (editErrors.value.phone) isValid = false;
        // التحقق من وجود الرقم فقط إذا تغير عن الرقم الأصلي
        if (isValidFormat && data.phone !== originalEditForm.value.phone && phoneExists.value) {
            isValid = false;
        }
    } else {
        editErrors.value.phone = false;
    }

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isEditFormValid = computed(() => {
    const data = editForm.value;
    
    if (!data.name || data.name.trim().length < 2) return false;
    if (!data.code || data.code.trim().length < 1) return false;
    if (!data.city || (data.city !== 'طرابلس' && data.city !== 'بنغازي')) return false;
    
    // التحقق من رقم الهاتف إذا كان موجوداً وتغير عن الأصلي
    if (data.phone && data.phone.trim() !== "") {
        const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
        if (!phoneRegex.test(data.phone.trim())) return false;
        if (data.phone !== originalEditForm.value.phone && phoneExists.value) return false;
    }
    
    return true;
});

// التحقق من وجود تغييرات
const isEditFormModified = computed(() => {
    const current = editForm.value;
    const original = originalEditForm.value;

    if (!original.id) return false;

    if (current.name !== original.name) return true;
    if (current.code !== original.code) return true;
    if (current.address !== original.address) return true;
    if (current.city !== original.city) return true;
    if (current.phone !== original.phone) return true;
    if (current.managerId !== original.managerId) return true;

    return false;
});

// إرسال النموذج
const submitEdit = () => {
    if (validateEditForm() && isEditFormModified.value) {
        isEditConfirmationModalOpen.value = true;
    }
};

// تأكيد التعديل
const confirmEdit = () => {
    const updatedSupplier = {
        id: editForm.value.id,
        name: editForm.value.name,
        code: editForm.value.code,
        address: editForm.value.address,
        city: editForm.value.city,
        phone: editForm.value.phone || null,
        managerId: editForm.value.managerId || null,
    };
    
    emit('save', updatedSupplier);
    closeEditConfirmationModal();
    closeEditModal();
};

// إغلاق النافذة الرئيسية
const closeEditModal = () => {
    emit('close');
};

// إغلاق نافذة التأكيد
const closeEditConfirmationModal = () => {
    isEditConfirmationModalOpen.value = false;
};

// تهيئة البيانات عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.supplier) {
        // جلب قائمة المدراء عند فتح النافذة
        fetchManagers();

        const initialData = {
            id: props.supplier.id,
            name: props.supplier.name || "",
            code: props.supplier.code || "",
            address: props.supplier.address || "",
            city: props.supplier.city || "",
            phone: props.supplier.phone || "",
            managerId: props.supplier.managerId || props.supplier.admin?.id || "",
        };

        originalEditForm.value = { ...initialData };
        editForm.value = initialData;

        // إعادة تعيين الأخطاء
        editErrors.value = {
            name: false,
            code: false,
            city: false,
            phone: false,
        };
        
        // إعادة تعيين حالة التحقق من رقم الهاتف
        phoneExists.value = false;
        phoneMessage.value = "";
    }
});

// التحقق من وجود رقم الهاتف
const checkPhoneExists = async (phone) => {
    if (!phone || phone.trim() === "") {
        phoneExists.value = false;
        phoneMessage.value = "";
        return;
    }

    // التحقق من التنسيق أولاً
    const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
    if (!phoneRegex.test(phone.trim())) {
        phoneExists.value = false;
        phoneMessage.value = "";
        return;
    }

    // إذا كان الرقم هو نفسه الرقم الأصلي، لا نحتاج للتحقق
    if (phone.trim() === originalEditForm.value.phone) {
        phoneExists.value = false;
        phoneMessage.value = "";
        return;
    }

    checkingPhone.value = true;
    try {
        const response = await api.get(`/super-admin/suppliers/check-phone/${phone.trim()}`);
        if (response.data && response.data.data) {
            phoneExists.value = response.data.data.exists;
            phoneMessage.value = response.data.data.exists ? "رقم الهاتف موجود بالفعل في النظام" : "";
        }
    } catch (error) {
        console.error("Error checking phone:", error);
        phoneExists.value = false;
        phoneMessage.value = "";
    } finally {
        checkingPhone.value = false;
    }
};

// متغير للـ timeout
let phoneCheckTimeout = null;

// مراقبة تغييرات رقم الهاتف للتحقق من الصيغة فوراً
watch(() => editForm.value.phone, (newPhone) => {
    // التحقق من الصيغة فوراً
    if (newPhone && newPhone.trim() !== "") {
        const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
        editErrors.value.phone = !phoneRegex.test(newPhone.trim());
    } else {
        editErrors.value.phone = false;
    }
    
    // التحقق من وجود الرقم بعد 500ms
    if (phoneCheckTimeout) {
        clearTimeout(phoneCheckTimeout);
    }
    
    phoneCheckTimeout = setTimeout(() => {
        checkPhoneExists(newPhone);
    }, 500); // انتظار 500ms بعد توقف المستخدم عن الكتابة
});
</script>

<template>
    <!-- Modal الرئيسي -->
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[90] flex items-center justify-center p-4"
    >
        <div
            @click="closeEditModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform transition-all scale-100"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تعديل بيانات المورد
                </h2>
                <button @click="closeEditModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitEdit" class="p-8 space-y-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- اسم المورد -->
                    <div class="space-y-2">
                        <Label for="edit-name" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:box-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم المورد
                        </Label>
                        <div class="relative">
                            <Input
                                id="edit-name"
                                v-model="editForm.name"
                                placeholder="أدخل اسم المورد"
                                type="text"
                                :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.name, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.name }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="editErrors.name" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال اسم المورد (على الأقل حرفين)
                        </p>
                    </div>

                    <!-- كود المورد -->
                    <div class="space-y-2">
                        <Label for="edit-code" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hashtag-circle-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            كود المورد
                        </Label>
                        <div class="relative">
                            <Input
                                id="edit-code"
                                v-model="editForm.code"
                                placeholder="أدخل كود المورد"
                                type="text"
                                :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.code, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.code }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="editErrors.code" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال كود المورد
                        </p>
                    </div>
                    
                    <!-- المدينة -->
                    <div class="space-y-2">
                        <Label for="edit-city" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:city-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المدينة
                        </Label>
                        <div class="relative">
                            <select
                                id="edit-city"
                                v-model="editForm.city"
                                :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.city, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.city }"
                                class="h-10 text-right w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all px-4 appearance-none focus:outline-none"
                            >
                                <option value="">اختر المدينة</option>
                                <option value="طرابلس">طرابلس</option>
                                <option value="بنغازي">بنغازي</option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="editErrors.city" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء اختيار المدينة
                        </p>
                    </div>

                    <!-- العنوان -->
                    <div class="space-y-2">
                        <Label for="edit-address" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:map-point-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            العنوان (اختياري)
                        </Label>
                        <div class="relative">
                            <Input
                                id="edit-address"
                                v-model="editForm.address"
                                placeholder="أدخل العنوان"
                                type="text"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                    </div>

                    <!-- رقم الهاتف -->
                    <div class="space-y-2">
                        <Label for="edit-phone" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف (اختياري)
                        </Label>
                        <div class="relative">
                            <Input
                                id="edit-phone"
                                v-model="editForm.phone"
                                placeholder="مثال: 0211234567"
                                type="tel"
                                maxlength="10"
                                :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.phone || phoneExists, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.phone && !phoneExists }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                            <span v-if="checkingPhone" class="absolute left-3 top-2.5 text-xs text-gray-400">
                                جاري التحقق...
                            </span>
                        </div>
                        <p v-if="editErrors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            يجب أن يبدأ الرقم بـ 021 أو 092 أو 091 أو 093 أو 094 متبوعاً بـ 7 أرقام
                        </p>
                        <p v-if="phoneExists && !editErrors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ phoneMessage }}
                        </p>
                    </div>

                    <!-- فاصل -->
                    <div class="md:col-span-2 border-t border-gray-200 my-2"></div>

                    <!-- المسؤول -->
                    <div class="space-y-2 ">
                        <Label for="edit-manager" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المسؤول (اختياري)
                        </Label>
                        <div class="relative">
                            <select
                                id="edit-manager"
                                v-model="editForm.managerId"
                                class="h-10 text-right w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all px-4 appearance-none focus:outline-none"
                            >
                                <option value="">بدون مسؤول</option>
                                <option v-for="manager in managersList" 
                                        :key="manager.id" 
                                        :value="manager.id">
                                    {{ manager.name }}
                                </option>
                            </select>
                            <Icon v-if="!loadingManagers" icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                            <Icon v-else icon="svg-spinners:ring-resize" class="w-5 h-5 text-[#4DA1A9] absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="editForm.managerId" class="text-xs text-gray-500 mt-1">
                            المسؤول المختار: 
                            <span class="font-semibold text-[#4DA1A9]">
                                {{ managersList.find(m => m.id === editForm.managerId)?.name }}
                            </span>
                        </p>
                        <p v-if="!editForm.managerId" class="text-xs text-gray-400 mt-1">
                            يمكنك تعيين مسؤول مورد من قائمة المسؤولين المتاحين.
                        </p>
                    </div>

                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        type="button"
                        @click="closeEditModal"
                        class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="!isEditFormModified || !isEditFormValid"
                        class="px-8 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:shadow-xl hover:-translate-y-1 transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                        حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة تأكيد التعديل -->
    <div v-if="isEditConfirmationModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeEditConfirmationModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon
                        icon="solar:pen-new-square-bold-duotone"
                        class="w-10 h-10 text-[#4DA1A9]"
                    />
                </div>
                
                <h3 class="text-xl font-bold text-[#2E5077]">
                    تأكيد التعديل
                </h3>
                
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من رغبتك في حفظ التعديلات على المورد
                    <span class="font-bold text-[#2E5077]">"{{ editForm.name }}"</span>؟
                    <br>
                    <span class="text-sm text-[#4DA1A9]">سيتم تحديث بيانات المورد في النظام</span>
                </p>
            </div>
            
            <div class="flex justify-center bg-gray-50 px-6 py-4 gap-3 border-t border-gray-100">
                <button
                    @click="closeEditConfirmationModal"
                    class="px-15 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmEdit"
                    class="px-15 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1"
                >
                    حفظ التعديلات
                </button>
            </div>
        </div>
    </div>
</template>
