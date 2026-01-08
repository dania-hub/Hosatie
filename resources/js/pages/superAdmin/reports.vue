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

// تعريف التبويبات
const tabs = [
    { id: 'hospitals', name: 'المؤسسات الصحية', icon: 'solar:hospital-bold-duotone' },
    { id: 'drugs', name: 'الأدوية', icon: 'solar:bottle-bold-duotone' },
    { id: 'users', name: 'المستخدمين', icon: 'solar:users-group-rounded-bold-duotone' },
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
        drugs: '/super-admin/reports/drugs',
        users: '/super-admin/reports/users',
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

// مراقبة تغيير التبويب
watch(currentTab, () => {
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
                                <th class="p-4">النوع</th>
                                <th class="p-4">المدينة</th>
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
                                <td class="p-4 text-gray-600">{{ hospital.typeArabic || hospital.type }}</td>
                                <td class="p-4 text-gray-600">{{ hospital.city }}</td>
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

                <!-- 2. Drugs Report -->
                <div v-if="currentTab === 'drugs'" class="overflow-x-auto">
                    <EmptyState v-if="reportData.length === 0" title="لا توجد أدوية" message="قائمة الأدوية فارغة" />
                    <table v-else class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-[#2E5077]">
                                <th class="p-4 rounded-r-xl">اسم الدواء</th>
                                <th class="p-4">الاسم العلمي</th>
                                <th class="p-4">التصنيف</th>
                                <th class="p-4">متوفر في (مخزن)</th>
                                <th class="p-4">إجمالي الكميات</th>
                                <th class="p-4 rounded-l-xl">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="drug in reportData" :key="drug.id" class="hover:bg-[#F8FAFC] transition-colors">
                                <td class="p-4 font-bold text-[#2E5077]">
                                    {{ drug.name }}
                                    <div class="text-xs text-gray-400 font-mono mt-1">{{ drug.strength }}</div>
                                </td>
                                <td class="p-4 text-gray-600 font-mono text-sm">{{ drug.genericName || '-' }}</td>
                                <td class="p-4">
                                    <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs">{{ drug.category }}</span>
                                </td>
                                <td class="p-4 font-bold">{{ drug.statistics?.warehousesCount || 0 }}</td>
                                <td class="p-4 font-bold text-[#4DA1A9]">{{ drug.statistics?.totalStock || 0 }}</td>
                                <td class="p-4">
                                    <span :class="['px-3 py-1 rounded-lg text-xs font-bold', getStatusClass(drug.status)]">
                                        {{ drug.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 3. Users Report -->
                <div v-if="currentTab === 'users'" class="overflow-x-auto">
                    <EmptyState v-if="reportData.length === 0" title="لا يوجد مستخدمين" message="قائمة المستخدمين فارغة" />
                    <table v-else class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-[#2E5077]">
                                <th class="p-4 rounded-r-xl">الاسم والبيانات</th>
                                <th class="p-4">الدور الوظيفي</th>
                                <th class="p-4">الجهة التابع لها</th>
                                <th class="p-4">رقم الهاتف</th>
                                <th class="p-4">آخر دخول</th>
                                <th class="p-4 rounded-l-xl">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="user in reportData" :key="user.id" class="hover:bg-[#F8FAFC] transition-colors">
                                <td class="p-4 font-bold text-[#2E5077]">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-[#2E5077]/10 flex items-center justify-center text-[#2E5077]">
                                            <Icon icon="solar:user-bold" class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <div>{{ user.fullName || user.name }}</div>
                                            <div class="text-xs text-gray-400 font-normal">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-gray-600">{{ user.typeArabic || user.type }}</td>
                                <td class="p-4 text-gray-600">
                                    {{ user.hospital?.name || user.supplier?.name || user.pharmacy?.name || user.warehouse?.name || '-' }}
                                </td>
                                <td class="p-4 font-mono text-sm text-gray-600">{{ user.phone }}</td>
                                <td class="p-4 text-gray-600 text-sm" dir="ltr">{{ user.lastLogin || '-' }}</td>
                                <td class="p-4">
                                    <span :class="['px-3 py-1 rounded-lg text-xs font-bold', getStatusClass(user.status)]">
                                        {{ user.statusArabic || user.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. Monthly Requests Report -->
                <div v-if="currentTab === 'requests'">
                     <EmptyState v-if="reportData.length === 0" title="لا توجد بيانات" message="لا توجد بيانات للطلبات الشهرية" />
                     <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="(item, index) in reportData" :key="index" class="bg-white border border-gray-200 rounded-2xl p-6 hover:shadow-lg transition-shadow">
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
                                <th class="p-4">الهدف (جدول)</th>
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
                                <td class="p-4 text-gray-500 text-sm font-mono">
                                    {{ log.tableName }} #{{ log.recordId }}
                                </td>
                                <td class="p-4 text-gray-500 font-mono text-sm" dir="ltr">{{ log.createdAt }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </main>
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