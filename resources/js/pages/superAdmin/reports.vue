<script setup>
import { ref, onMounted, watch } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import LoadingState from "@/components/Shared/LoadingState.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import DefaultLayout from "@/components/DefaultLayout.vue"; 

// ----------------------------------------------------
// 1. إعداد API
// ----------------------------------------------------
const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
  },
  error => Promise.reject(error)
);

// ----------------------------------------------------
// 2. الحالة (State)
// ----------------------------------------------------
const currentTab = ref('hospitals');
const isLoading = ref(false);
const error = ref(null);
const reportData = ref([]);

// تفاصيل الطلبات الشهرية
const viewMode = ref('list'); // 'list' | 'details'
const selectedMonthData = ref(null);
const monthDetails = ref([]);
const isDetailLoading = ref(false);

// نافذة العناصر (Items Modal)
const isItemsModalOpen = ref(false);
const itemsData = ref([]);
const isItemsLoading = ref(false);
const selectedRequestForItems = ref(null);

// تفاصيل النشاط (Activity Modal)
const isActivityModalOpen = ref(false);
const selectedActivity = ref(null);

// تعريف التبويبات
const tabs = [
    { id: 'hospitals', name: 'المؤسسات الصحية', icon: 'solar:hospital-bold-duotone' },
    { id: 'dispensings', name: 'عمليات الصرف', icon: 'solar:pill-bold-duotone' },
    { id: 'requests', name: 'الطلبات الشهرية', icon: 'solar:chart-2-bold-duotone' },
    { id: 'activities', name: 'سجل النشاطات', icon: 'solar:history-bold-duotone' }
];

// ----------------------------------------------------
// 3. جلب البيانات
// ----------------------------------------------------
const fetchReport = async () => {
    isLoading.value = true;
    error.value = null;
    reportData.value = [];

    const endpoints = {
        hospitals: '/super-admin/reports/hospitals',
        dispensings: '/super-admin/reports/dispensings',
        requests: '/super-admin/reports/requests-monthly',
        activities: '/super-admin/reports/activities'
    };

    try {
        const response = await api.get(endpoints[currentTab.value]);
        // الرد يأتي عادة في data.data
        reportData.value = response.data.data || response.data || [];
        
    } catch (err) {
        console.error(`Error fetching ${currentTab.value} report:`, err);
        error.value = err.response?.data?.message || 'فشل تحميل التقرير';
    } finally {
        isLoading.value = false;
    }
};

const showMonthDetails = async (monthItem) => {
    selectedMonthData.value = monthItem;
    viewMode.value = 'details';
    monthDetails.value = [];
    isDetailLoading.value = true;
    
    try {
        const response = await api.get('/super-admin/reports/requests-monthly/details', {
            params: { month: monthItem.month }
        });
        monthDetails.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching month details:', err);
        // يمكن إضافة تنبيه هنا
    } finally {
        isDetailLoading.value = false;
    }
};

const openItemsModal = async (req) => {
    isItemsModalOpen.value = true;
    selectedRequestForItems.value = req;
    itemsData.value = [];
    isItemsLoading.value = true;

    try {
        const response = await api.get('/super-admin/reports/request-items', {
            params: {
                type: req.type,
                id: req.id
            }
        });
        itemsData.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching items:', err);
    } finally {
        isItemsLoading.value = false;
    }
};

const closeItemsModal = () => {
    isItemsModalOpen.value = false;
    selectedRequestForItems.value = null;
    itemsData.value = [];
};

const openActivityModal = (log) => {
    selectedActivity.value = log;
    isActivityModalOpen.value = true;
};

const closeActivityModal = () => {
    isActivityModalOpen.value = false;
    selectedActivity.value = null;
};

const backToMonths = () => {
    viewMode.value = 'list';
    selectedMonthData.value = null;
    monthDetails.value = [];
};

/* ----------------------------------------------------------------
   Departments Modal Logic
---------------------------------------------------------------- */
const isDepartmentsModalOpen = ref(false);
const hospitalDepartments = ref([]);
const isDepartmentsLoading = ref(false);
const selectedHospitalForDept = ref(null);

const openDepartmentsModal = async (hospital) => {
    isDepartmentsModalOpen.value = true;
    selectedHospitalForDept.value = hospital;
    hospitalDepartments.value = [];
    isDepartmentsLoading.value = true;

    try {
        const response = await api.get('/super-admin/reports/hospital-departments', {
            params: { hospital_id: hospital.id }
        });
        hospitalDepartments.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching departments:', err);
    } finally {
        isDepartmentsLoading.value = false;
    }
};

const closeDepartmentsModal = () => {
    isDepartmentsModalOpen.value = false;
    selectedHospitalForDept.value = null;
    hospitalDepartments.value = [];
};

/* ----------------------------------------------------------------
   Pharmacies Modal Logic
---------------------------------------------------------------- */
const isPharmaciesModalOpen = ref(false);
const hospitalPharmacies = ref([]);
const isPharmaciesLoading = ref(false);
const selectedHospitalForPharm = ref(null);

const openPharmaciesModal = async (hospital) => {
    isPharmaciesModalOpen.value = true;
    selectedHospitalForPharm.value = hospital;
    hospitalPharmacies.value = [];
    isPharmaciesLoading.value = true;

    try {
        const response = await api.get('/super-admin/reports/hospital-pharmacies', {
            params: { hospital_id: hospital.id }
        });
        hospitalPharmacies.value = response.data.data || [];
    } catch (err) {
        console.error('Error fetching pharmacies:', err);
    } finally {
        isPharmaciesLoading.value = false;
    }
};

const closePharmaciesModal = () => {
    isPharmaciesModalOpen.value = false;
    selectedHospitalForPharm.value = null;
    hospitalPharmacies.value = [];
};

// ----------------------------------------------------
// 5. Activity Log Helpers
// ----------------------------------------------------
const fieldTranslations = {
    'name': 'الاسم',
    'name_ar': 'الاسم (عربي)',
    'name_en': 'الاسم (إنجليزي)',
    'full_name': 'الاسم الكامل',
    'first_name': 'الاسم الأول',
    'last_name': 'اسم العائلة',
    'email': 'البريد الإلكتروني',
    'phone': 'رقم الهاتف',
    'mobile': 'رقم الجوال',
    'address': 'العنوان',
    'status': 'الحالة',
    'type': 'النوع',
    'role': 'الدور الوظيفي',
    'password': 'كلمة المرور',
    'hospital_id': 'مؤسسة صحية',
    'supplier_id': 'مورد',
    'pharmacy_id': 'صيدلية',
    'warehouse_id': 'مخزن',
    'department_id': 'قسم',
    'created_at': 'تاريخ الإنشاء',
    'updated_at': 'تاريخ التحديث',
    'deleted_at': 'تاريخ الحذف',
    'description': 'الوصف',
    'notes': 'ملاحظات',
    'qty': 'الكمية',
    'stock': 'المخزون',
    'price': 'السعر',
    'cost': 'التكلفة',
    'expiry_date': 'تاريخ الصلاحية',
    'batch_number': 'رقم التشغيلة',
    'national_id': 'الرقم الوطني',
    'license_number': 'رقم الترخيص',
    'city': 'المدينة',
    'region': 'المنطقة',
};

const formatKey = (key) => {
    return fieldTranslations[key] || key;
};

const formatValue = (key, value) => {
    if (value === null || value === undefined || value === '') return '-';
    if (key === 'password') return '********';
    if ((key.includes('_at') || key.includes('date')) && value) {
        try {
            return new Date(value).toLocaleString('ar-LY');
        } catch (e) { return value; }
    }
    if (key === 'status') return getStatusText(value);
    // Boolean mapping
    if (typeof value === 'boolean') return value ? 'نعم' : 'لا';
    if (value === 1 || value === '1') {
        if (key.startsWith('is_')) return 'نعم';
    }
    if (value === 0 || value === '0') {
         if (key.startsWith('is_')) return 'لا';
    }
    
    return value;
};

const isVisibleField = (key) => {
    const hidden = [
        'fcm_token', 'remember_token', 'device_token', 
        'email_verified_at', 'two_factor_secret', 
        'two_factor_recovery_codes', 'created_by', 
        'updated_by', 'deleted_by', 'pivot', 
        'id', 'record_id'
    ];
    return !hidden.includes(key);
};

// مراقبة تغيير التبويب
watch(currentTab, () => {
    viewMode.value = 'list';
    fetchReport();
});

onMounted(() => {
    fetchReport();
});

// ----------------------------------------------------
// 4. دوال مساعدة
// ----------------------------------------------------
const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('ar-LY');
};

const getStatusClass = (status) => {
    // توحيد الحالات
    const s = status ? status.toLowerCase() : '';
    switch (s) {
        case 'active': case 'متوفر': case 'نشط': return 'bg-green-100 text-green-700';
        case 'inactive': case 'معطل': case 'غير نشط': case 'غير متوفر': return 'bg-red-100 text-red-700';
        case 'pending': case 'pending_activation': return 'bg-yellow-100 text-yellow-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};

const getStatusText = (status) => {
    const map = {
        'active': 'نشط',
        'inactive': 'غير نشط',
        'pending': 'الانتظار',
        'pending_activation': 'بانتظار التفعيل'
    };
    return map[status] || status;
};

</script>

<template>
<DefaultLayout>
    <main class="flex-1 p-4 sm:p-8 pt-20 sm:pt-5 min-h-screen bg-[#F8FAFC]">
        
        <!-- Header -->
        <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[#2E5077] mb-2 flex items-center gap-3">
                    <Icon icon="carbon:report-data" class="w-10 h-10 text-[#4DA1A9]" />
                    التقارير الشاملة
                </h1>
                <p class="text-gray-500">استعراض وتحليل بيانات النظام والتقارير التفصيلية</p>
            </div>
            
            <!-- زر تحديث -->
            <button 
                @click="fetchReport" 
                class="px-4 py-2 bg-white border border-gray-200 text-[#2E5077] rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-2 shadow-sm font-medium"
                :disabled="isLoading"
            >
                <Icon icon="solar:refresh-linear" class="w-5 h-5" :class="{ 'animate-spin': isLoading }" />
                تحديث البيانات
            </button>
        </div>

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-2xl p-2 shadow-sm border border-gray-100 mb-8 overflow-x-auto">
            <div class="flex space-x-2 space-x-reverse min-w-max">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="currentTab = tab.id"
                    class="flex items-center gap-2 px-6 py-3 rounded-xl transition-all duration-200 font-bold whitespace-nowrap"
                    :class="[
                        currentTab === tab.id 
                            ? 'bg-[#2E5077] text-white shadow-lg shadow-[#2E5077]/20 transform -translate-y-0.5' 
                            : 'text-gray-500 hover:bg-gray-50 hover:text-[#2E5077]'
                    ]"
                >
                    <Icon :icon="tab.icon" class="w-5 h-5" />
                    {{ tab.name }}
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 min-h-[400px] overflow-hidden relative">
            
            <!-- Loading -->
            <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm z-10">
                <LoadingState message="جاري إعداد التقرير..." />
            </div>

            <!-- Error -->
            <div v-else-if="error" class="p-12">
                <ErrorState :message="error" :retry="fetchReport" />
            </div>

            <!-- Data Tables -->
            <div v-else class="p-6">
                
                <!-- 1. Hospitals Report -->
                <div v-if="currentTab === 'hospitals'" class="overflow-x-auto">
                     <EmptyState v-if="reportData.length === 0" title="لا توجد بيانات" message="لم يتم العثور على مؤسسات مسجلة" />
                     <table v-else class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-[#2E5077]">
                                <th class="p-4 rounded-r-xl">المؤسسة</th>
                                <th class="p-4">عدد الأقسام</th>
                                <th class="p-4">عدد الصيدليات</th>
                                <th class="p-4">عدد الموظفين</th>
                                <th class="p-4">المخازن</th>
                                <th class="p-4">الوصفات النشطة</th>
                                <th class="p-4 rounded-l-xl">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="hospital in reportData" :key="hospital.id" class="hover:bg-[#F8FAFC] transition-colors group">
                                <td class="p-4 font-bold text-[#2E5077] group-hover:text-[#4DA1A9] transition-colors">
                                    {{ hospital.name }}
                                    <div class="text-xs text-gray-400 font-normal mt-1">{{ hospital.code }}</div>
                                </td>
                                <td class="p-4 text-gray-600">
                                    <button @click="openDepartmentsModal(hospital)" class="text-[#2E5077] hover:underline font-bold">
                                        {{ hospital.statistics?.departmentsCount || 0 }}
                                    </button>
                                </td>
                                <td class="p-4 text-gray-600">
                                    <button @click="openPharmaciesModal(hospital)" class="text-[#2E5077] hover:underline font-bold">
                                        {{ hospital.statistics?.pharmaciesCount || 0 }}
                                    </button>
                                </td>
                                <td class="p-4 font-bold">{{ hospital.statistics?.staffCount || 0 }}</td>
                                <td class="p-4 font-bold">{{ hospital.statistics?.warehousesCount || 0 }}</td>
                                <td class="p-4 font-bold text-[#4DA1A9]">{{ hospital.statistics?.activePrescriptions || 0 }}</td>
                                <td class="p-4">
                                    <span :class="['px-3 py-1 rounded-lg text-xs font-bold', getStatusClass(hospital.status || hospital.statusArabic)]">
                                        {{ hospital.statusArabic || hospital.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>


                <!-- 3. Dispensings Report -->
                <div v-if="currentTab === 'dispensings'" class="overflow-x-auto">
                    <EmptyState v-if="reportData.length === 0" title="لا توجد عمليات صرف" message="سجل الصرف فارغ" />
                    <table v-else class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-[#2E5077]">
                                <th class="p-4 rounded-r-xl">الصيدلية</th>
                                <th class="p-4">المريض</th>
                                <th class="p-4">الدواء</th>
                                <th class="p-4">الكمية</th>
                                <th class="p-4">الصيدلي</th>
                                <th class="p-4">التاريخ</th>
                                <th class="p-4 rounded-l-xl">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="dispensing in reportData" :key="dispensing.id" class="hover:bg-[#F8FAFC] transition-colors">
                                <td class="p-4 font-bold text-[#2E5077]">{{ dispensing.pharmacy }}</td>
                                <td class="p-4 text-gray-600 font-medium">{{ dispensing.patient }}</td>
                                <td class="p-4 text-[#2E5077] font-bold">{{ dispensing.drug }}</td>
                                <td class="p-4 font-mono font-bold">{{ dispensing.quantity }}</td>
                                <td class="p-4 text-gray-500 text-sm">{{ dispensing.pharmacist }}</td>
                                <td class="p-4 text-gray-500 font-mono text-sm" dir="ltr">{{ dispensing.date }}</td>
                                <td class="p-4">
                                     <span class="px-2 py-1 rounde-lg text-xs font-bold" 
                                        :class="dispensing.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                         {{ dispensing.statusArabic }}
                                     </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. Monthly Requests Report -->
                <div v-if="currentTab === 'requests'">
                     <!-- List View -->
                     <div v-if="viewMode === 'list'">
                        <EmptyState v-if="reportData.length === 0" title="لا توجد بيانات" message="لا توجد بيانات للطلبات الشهرية" />
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div 
                                v-for="(item, index) in reportData" 
                                :key="index" 
                                @click="showMonthDetails(item)"
                                class="bg-white border border-gray-200 rounded-2xl p-6 hover:shadow-lg transition-all cursor-pointer transform hover:-translate-y-1"
                            >
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-bold text-[#2E5077]">{{ item.monthName }}</h3>
                                    <div class="w-10 h-10 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center">
                                        <Icon icon="solar:calendar-bold" class="w-6 h-6 text-[#4DA1A9]" />
                                    </div>
                                </div>
                                <div class="border-b border-gray-100 pb-2 mb-2 text-xs text-gray-400 font-mono">{{ item.month }}</div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">الطلبات الداخلية</span>
                                        <span class="font-bold text-[#2E5077]">{{ item.internalRequests }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">الطلبات الخارجية</span>
                                        <span class="font-bold text-blue-600">{{ item.externalRequests }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">طلبات التحويل</span>
                                        <span class="font-bold text-orange-600">{{ item.transferRequests }}</span>
                                    </div>
                                    <div class="pt-3 mt-3 border-t border-gray-100 flex justify-between items-center">
                                        <span class="font-bold text-[#2E5077]">الإجمالي</span>
                                        <div class="bg-[#2E5077] text-white px-3 py-1 rounded-full text-sm font-bold shadow-sm">
                                            {{ item.total }}
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 text-center text-xs text-[#4DA1A9] flex items-center justify-center gap-1 font-medium bg-cyan-50 py-2 rounded-lg">
                                    <Icon icon="solar:eye-broken" class="w-4 h-4" />
                                    عرض التفاصيل
                                </div>
                            </div>
                        </div>
                     </div>

                     <!-- Details View -->
                     <div v-else>
                        <div class="mb-6 flex items-center gap-4">
                            <button @click="backToMonths" class="p-2 hover:bg-gray-100 rounded-xl transition-colors text-gray-600">
                                <Icon icon="solar:arrow-right-bold" class="w-6 h-6" />
                            </button>
                            <div>
                                <h2 class="text-xl font-bold text-[#2E5077] flex items-center gap-2">
                                    تفاصيل الطلبات
                                    <span v-if="selectedMonthData" class="text-[#4DA1A9] text-base font-normal">
                                        ({{ selectedMonthData.monthName }})
                                    </span>
                                </h2>
                            </div>
                        </div>

                        <div v-if="isDetailLoading" class="min-h-[300px] flex items-center justify-center">
                            <LoadingState message="جاري تحميل التفاصيل..." />
                        </div>
                        <div v-else-if="monthDetails.length === 0" class="min-h-[300px] flex flex-col items-center justify-center">
                            <EmptyState title="لا توجد طلبات" message="لم يتم تسجيل أي طلبات في هذا الشهر" />
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-right border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-[#2E5077]">
                                        <th class="p-4 rounded-r-xl">رقم الطلب</th>
                                        <th class="p-4">نوع الطلب</th>
                                        <th class="p-4">مرسل الطلب</th>
                                        <th class="p-4">مستقبل الطلب</th>
                                        <th class="p-4">التاريخ</th>
                                        <th class="p-4">عدد المواد</th>
                                        <th class="p-4 rounded-l-xl">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="req in monthDetails" :key="req.id" class="hover:bg-[#F8FAFC] transition-colors">
                                        <td class="p-4 font-mono text-sm font-bold text-[#2E5077]">{{ req.displayId }}</td>
                                        <td class="p-4">
                                            <span class="px-2 py-1 bg-gray-100 rounded text-gray-700 text-xs font-bold"
                                                :class="{
                                                    'bg-blue-50 text-blue-700': req.type === 'external',
                                                    'bg-green-50 text-green-700': req.type === 'internal',
                                                    'bg-orange-50 text-orange-700': req.type === 'transfer'
                                                }"
                                            >
                                                {{ req.typeArabic }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-gray-600 font-medium">{{ req.sender }}</td>
                                        <td class="p-4 text-gray-600 font-medium">{{ req.receiver }}</td>
                                        <td class="p-4 text-gray-500 font-mono text-sm" dir="ltr">{{ req.date }}</td>
                                        <td class="p-4 font-bold text-center">
                                            <button 
                                                @click.stop="openItemsModal(req)"
                                                class="px-3 py-1 bg-gray-100 hover:bg-[#4DA1A9] hover:text-white rounded-lg transition-colors text-[#2E5077] font-bold text-sm inline-flex items-center gap-1"
                                            >
                                                {{ req.itemsCount }}
                                                <Icon icon="solar:eye-broken" class="w-3 h-3" />
                                            </button>
                                        </td>
                                        <td class="p-4">
                                            <span :class="['px-3 py-1 rounded-lg text-xs font-bold', getStatusClass(req.status)]">
                                                {{ req.statusArabic || req.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                     </div>
                </div>

                <!-- 5. Activities Report -->
                <div v-if="currentTab === 'activities'" class="overflow-x-auto">
                    <EmptyState v-if="reportData.length === 0" title="لا توجد نشاطات" message="سجل النشاطات فارغ" />
                    <table v-else class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-[#2E5077]">
                                <th class="p-4 rounded-r-xl">المستخدم</th>
                                <th class="p-4">نوع المستخدم</th>
                                <th class="p-4">النشاط</th>
                                <th class="p-4">تفاصيل النشاط</th>
                                <th class="p-4 rounded-l-xl">التوقيت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="log in reportData" :key="log.id" class="hover:bg-[#F8FAFC] transition-colors">
                                <td class="p-4 font-bold text-[#2E5077]">{{ log.userName || 'غير معروف' }}</td>
                                <td class="p-4 text-gray-600 text-sm">{{ log.userTypeArabic || log.userType }}</td>
                                <td class="p-4">
                                    <span class="font-medium inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-100 text-gray-700">
                                        {{ log.actionArabic || log.action }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <button 
                                        @click="openActivityModal(log)"
                                        class="flex items-center gap-2 text-[#4DA1A9] hover:text-[#2E5077] transition-colors font-bold text-sm bg-cyan-50 hover:bg-gray-100 px-3 py-1 rounded-lg"
                                    >
                                        <Icon icon="solar:document-text-bold-duotone" class="w-4 h-4" />
                                        <span>عرض التفاصيل</span>
                                        <span class="text-xs text-gray-400 font-normal font-mono">({{ log.tableName }} #{{ log.recordId }})</span>
                                    </button>
                                </td>
                                <td class="p-4 text-gray-500 font-mono text-sm" dir="ltr">{{ log.createdAt }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- Items Modal -->
        <div v-if="isItemsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeItemsModal">
            <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col shadow-2xl animate-fade-in-up">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <div>
                        <h3 class="text-xl font-bold text-[#2E5077]">تفاصيل المواد</h3>
                        <div class="text-sm text-gray-500 mt-1 font-mono" v-if="selectedRequestForItems">
                           {{ selectedRequestForItems.displayId }}
                        </div>
                    </div>
                    <button @click="closeItemsModal" class="p-2 hover:bg-white rounded-full transition-colors text-gray-500 hover:text-red-500 hover:shadow-sm">
                        <Icon icon="solar:close-circle-bold" class="w-8 h-8" />
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto flex-1">
                    <div v-if="isItemsLoading" class="flex flex-col items-center justify-center py-12">
                         <Icon icon="svg-spinners:3-dots-fade" class="w-10 h-10 text-[#4DA1A9] mb-4" />
                         <span class="text-gray-500">جاري تحميل المواد...</span>
                    </div>

                    <EmptyState v-else-if="itemsData.length === 0" title="لا توجد مواد" message="القائمة فارغة لهذا الطلب" />

                    <div v-else class="space-y-3">
                         <div v-for="(item, idx) in itemsData" :key="idx" class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-[#4DA1A9]/30 hover:bg-[#F8FAFC] transition-all">
                             <div class="flex items-center gap-4">
                                 <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                                      :class="item.type === 'patient' ? 'bg-orange-50 text-orange-600' : 'bg-blue-50 text-blue-600'">
                                     <Icon :icon="item.type === 'patient' ? 'solar:user-bold' : 'solar:pill-bold'" class="w-5 h-5" />
                                 </div>
                                 <div>
                                     <div class="font-bold text-[#2E5077]">{{ item.name }}</div>
                                     <div v-if="item.details" class="text-xs text-gray-500 mt-1">{{ item.details }}</div>
                                     <div v-else-if="item.type === 'drug'" class="text-xs text-gray-500 mt-1 flex gap-2">
                                          <span>الموافقة: <b>{{ item.approved_qty }}</b></span>
                                     </div>
                                 </div>
                             </div>
                             <div class="text-center">
                                 <div class="text-xs text-gray-500 mb-1">الكمية المطلوبة</div>
                                 <div class="font-bold text-lg text-[#2E5077] bg-gray-50 px-3 py-1 rounded-lg border border-gray-100">
                                     {{ item.qty }}
                                 </div>
                             </div>
                         </div>
                    </div>
                </div>
                
                <div class="p-4 border-t border-gray-100 bg-gray-50 flex justify-end">
                    <button @click="closeItemsModal" class="px-6 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-100 font-bold transition-colors">
                        إغلاق
                    </button>
                </div>
            </div>
        </div>

        <!-- Activity Details Modal -->
        <div v-if="isActivityModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeActivityModal">
            <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col shadow-2xl animate-fade-in-up">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <div>
                        <h3 class="text-xl font-bold text-[#2E5077]">تفاصيل النشاط</h3>
                        <div class="text-xs text-gray-500 mt-1 font-mono" v-if="selectedActivity">
                            {{ selectedActivity.actionArabic }} | {{ selectedActivity.createdAt }}
                        </div>
                    </div>
                    <button @click="closeActivityModal" class="p-2 hover:bg-white rounded-full transition-colors text-gray-500 hover:text-red-500 hover:shadow-sm">
                        <Icon icon="solar:close-circle-bold" class="w-8 h-8" />
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                    <div v-if="!selectedActivity?.details?.old && !selectedActivity?.details?.new" class="flex flex-col items-center justify-center py-8">
                         <EmptyState title="لا توجد تفاصيل" message="لم يتم تسجيل تغييرات تفصيلية لهذا النشاط" />
                    </div>
                    <div v-else class="space-y-6">
                        <!-- Old Values -->
                        <div v-if="selectedActivity.details.old" class="border rounded-xl overflow-hidden">
                             <div class="bg-red-50 p-3 border-b border-red-100 font-bold text-red-700 flex items-center gap-2">
                                <Icon icon="solar:rewind-back-circle-bold" class="w-5 h-5" />
                                البيانات السابقة
                             </div>
                             <div class="p-4 bg-red-50/10">
                                 <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-6">
                                     <template v-for="(val, key) in selectedActivity.details.old" :key="key">
                                         <div v-if="isVisibleField(key)" class="flex flex-col border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                                             <span class="text-xs text-gray-400 font-bold mb-1">{{ formatKey(key) }}</span>
                                             <span class="text-gray-700 font-medium text-sm break-all" :class="{'font-mono': key === 'password'}">
                                                 {{ formatValue(key, val) }}
                                             </span>
                                         </div>
                                     </template>
                                 </div>
                             </div>
                        </div>

                        <!-- New Values -->
                        <div v-if="selectedActivity.details.new" class="border rounded-xl overflow-hidden">
                             <div class="bg-green-50 p-3 border-b border-green-100 font-bold text-green-700 flex items-center gap-2">
                                <Icon icon="solar:play-circle-bold" class="w-5 h-5" />
                                البيانات الجديدة
                             </div>
                             <div class="p-4 bg-green-50/10">
                                 <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-6">
                                     <template v-for="(val, key) in selectedActivity.details.new" :key="key">
                                         <div v-if="isVisibleField(key)" class="flex flex-col border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                                             <span class="text-xs text-gray-400 font-bold mb-1">{{ formatKey(key) }}</span>
                                             <span class="text-gray-700 font-bold text-sm break-all" :class="{'text-green-600': true, 'font-mono': key === 'password'}">
                                                 {{ formatValue(key, val) }}
                                             </span>
                                         </div>
                                     </template>
                                 </div>
                             </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-100 bg-gray-50 flex justify-end">
                    <button @click="closeActivityModal" class="px-6 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-100 font-bold transition-colors">
                        إغلاق
                    </button>
                </div>
            </div>
        </div>

    </main>
        <!-- Departments Modal -->
        <div v-if="isDepartmentsModalOpen" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col animate-fade-in-up">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-3xl">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-blue-100 text-blue-600 rounded-xl">
                            <Icon icon="solar:hospital-bold-duotone" class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-[#2E5077]">أقسام المستشفى</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ selectedHospitalForDept?.name }}</p>
                        </div>
                    </div>
                    <button @click="closeDepartmentsModal" class="p-2 hover:bg-white rounded-full transition-colors text-gray-500 hover:text-red-500 hover:shadow-sm">
                        <Icon icon="solar:close-circle-bold" class="w-8 h-8" />
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                     <div v-if="isDepartmentsLoading" class="flex justify-center py-12">
                        <LoadingState message="جاري تحميل الأقسام..." />
                    </div>
                    <EmptyState v-else-if="hospitalDepartments.length === 0" title="لا توجد أقسام" message="لم يتم تسجيل أي أقسام لهذا المستشفى" />
                    <div v-else class="overflow-hidden rounded-xl border border-gray-100">
                        <table class="w-full text-right">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="p-4 text-sm font-bold text-gray-600 w-1/2">القسم</th>
                                    <th class="p-4 text-sm font-bold text-gray-600">رئيس القسم</th>
                                    <th class="p-4 text-sm font-bold text-gray-600 text-center">الحالة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="dept in hospitalDepartments" :key="dept.id" class="hover:bg-gray-50/50">
                                    <td class="p-4 font-bold text-[#2E5077]">{{ dept.name }}</td>
                                    <td class="p-4 text-gray-600">{{ dept.head }}</td>
                                    <td class="p-4 text-center">
                                        <span :class="['px-3 py-1 rounded-lg text-xs font-bold', getStatusClass(dept.status)]">
                                            {{ getStatusText(dept.status) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 bg-gray-50/50 rounded-b-3xl">
                     <button @click="closeDepartmentsModal" class="w-full py-3 bg-white border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
                        إغلاق
                    </button>
                </div>
            </div>
        </div>

        <!-- Pharmacies Modal -->
        <div v-if="isPharmaciesModalOpen" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-5xl max-h-[85vh] flex flex-col animate-fade-in-up">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-3xl">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-green-100 text-green-600 rounded-xl">
                            <Icon icon="solar:pill-bold-duotone" class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-[#2E5077]">صيدليات المستشفى ومخزونها</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ selectedHospitalForPharm?.name }}</p>
                        </div>
                    </div>
                    <button @click="closePharmaciesModal" class="p-2 hover:bg-white rounded-full transition-colors text-gray-500 hover:text-red-500 hover:shadow-sm">
                        <Icon icon="solar:close-circle-bold" class="w-8 h-8" />
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                     <div v-if="isPharmaciesLoading" class="flex justify-center py-12">
                        <LoadingState message="جاري تحميل الصيدليات..." />
                    </div>
                    <EmptyState v-else-if="hospitalPharmacies.length === 0" title="لا توجد صيدليات" message="لم يتم تسجيل أي صيدليات" />
                    <div v-else class="space-y-6">
                        <div v-for="pharmacy in hospitalPharmacies" :key="pharmacy.id" class="rounded-2xl border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 border-b border-gray-100 p-4 flex justify-between items-center">
                                <h4 class="font-bold text-[#2E5077] flex items-center gap-2">
                                     <Icon icon="solar:shop-bold-duotone" class="text-green-500" />
                                     {{ pharmacy.name }}
                                </h4>
                                <span class="text-sm text-gray-500 bg-white px-3 py-1 rounded-lg border border-gray-200">
                                   عدد الأصناف: {{ pharmacy.inventory.length }}
                                </span>
                            </div>
                            
                            <div v-if="pharmacy.inventory.length === 0" class="p-8 text-center text-gray-500">
                                المخزون فارغ
                            </div>
                            <table v-else class="w-full text-right text-sm">
                                <thead class="bg-white border-b border-gray-100/50">
                                    <tr>
                                        <th class="p-3 text-gray-500 font-medium w-1/2">اسم الدواء</th>
                                        <th class="p-3 text-gray-500 font-medium">الكمية</th>
                                        <th class="p-3 text-gray-500 font-medium text-left">تاريخ الصلاحية</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(item, idx) in pharmacy.inventory" :key="idx" class="hover:bg-gray-50/30">
                                        <td class="p-3 font-bold text-[#2E5077]">{{ item.drug_name }}</td>
                                        <td class="p-3 font-mono font-bold">{{ item.quantity }}</td>
                                        <td class="p-3 font-mono text-left">
                                            <span v-if="item.expiry_date && item.expiry_date !== '-'" 
                                                  :class="new Date(item.expiry_date) < new Date() ? 'text-red-500 font-bold bg-red-50 px-2 py-1 rounded' : 'text-green-600 bg-green-50 px-2 py-1 rounded'">
                                                {{ item.expiry_date }}
                                            </span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 bg-gray-50/50 rounded-b-3xl">
                     <button @click="closePharmaciesModal" class="w-full py-3 bg-white border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
                        إغلاق
                    </button>
                </div>
            </div>
        </div>

</DefaultLayout>
</template>

<style scoped>
/* Custom Scrollbar for tabs */
.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 2px;
}
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>