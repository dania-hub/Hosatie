<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import LoadingState from "@/components/Shared/LoadingState.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";

import DefaultLayout from "@/components/DefaultLayout.vue"; 

// ----------------------------------------------------
// 1. تعريف الـ Endpoint ومتغيرات الحالة
// ----------------------------------------------------
const API_URL = '/api/super-admin/dashboard/stats';

// تكوين Axios مع التوكن
const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// إضافة interceptor لإضافة التوكن تلقائيًا
api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  error => {
    return Promise.reject(error);
  }
);

// متغير لتخزين الإحصائيات
const stats = ref({
    hospitals: {
        total: 0,
        active: 0,
        inactive: 0
    },
    users: {
        total: 0,
        active: 0,
        inactive: 0
    },
    patients: {
        total: 0,
        active: 0,
        newThisMonth: 0
    },
    drugs: {
        total: 0,
        available: 0,
        lowStock: 0
    },
    prescriptions: {
        total: 0,
        active: 0,
        thisMonth: 0
    },
    complaints: {
        total: 0,
        pending: 0,
        thisMonth: 0
    },
    isLoading: true,
    error: null
});

// ----------------------------------------------------
// 2. دالة جلب البيانات باستخدام Axios
// ----------------------------------------------------
const fetchStats = async () => {
    stats.value.isLoading = true;
    stats.value.error = null;

    try {
        const response = await api.get('/super-admin/dashboard/stats');
        const data = response.data?.data || response.data || {};
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        if (data.hospitals) {
            stats.value.hospitals = {
                total: data.hospitals.total || 0,
                active: data.hospitals.active || 0,
                inactive: data.hospitals.inactive || 0
            };
        }
        
        if (data.users) {
            stats.value.users = {
                total: data.users.total || 0,
                active: data.users.active || 0,
                inactive: data.users.inactive || 0
            };
        }
        
        if (data.patients) {
            stats.value.patients = {
                total: data.patients.total || 0,
                active: data.patients.active || 0,
                newThisMonth: data.patients.newThisMonth || 0
            };
        }
        
        if (data.drugs) {
            stats.value.drugs = {
                total: data.drugs.total || 0,
                available: data.drugs.available || 0,
                lowStock: data.drugs.lowStock || 0
            };
        }
        
        if (data.prescriptions) {
            stats.value.prescriptions = {
                total: data.prescriptions.total || 0,
                active: data.prescriptions.active || 0,
                thisMonth: data.prescriptions.thisMonth || 0
            };
        }
        
        if (data.complaints) {
            stats.value.complaints = {
                total: data.complaints.total || 0,
                pending: data.complaints.pending || 0,
                thisMonth: data.complaints.thisMonth || 0
            };
        }
        
    } catch (error) {
        console.error("Error fetching dashboard statistics:", error);
        stats.value.error = error.response?.data?.message || 'فشل تحميل الإحصائيات';
    } finally {
        stats.value.isLoading = false;
    }
};

// ----------------------------------------------------
// 3. جلب البيانات عند تحميل المكون
// ----------------------------------------------------
onMounted(() => {
    fetchStats();
});
</script>

<template>
<DefaultLayout>
    <main class="flex-1 p-4 sm:p-8 pt-20 sm:pt-5 min-h-screen">
        <!-- حالة التحميل -->
        <div v-if="stats.isLoading" class="py-20">
            <LoadingState message="جاري تحميل الإحصائيات..." />
        </div>

        <!-- حالة الخطأ -->
        <div v-else-if="stats.error" class="py-20">
            <ErrorState :message="stats.error" :retry="fetchStats" />
        </div>

        <!-- الإحصائيات -->
        <div v-else class="space-y-8 animate-fade-in-up">
            <!-- شبكة البطاقات المحسنة -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                
                <!-- إحصائيات المؤسسات -->
                <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                    <div class="h-2 bg-[#2E5077]"></div>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">المؤسسات الصحية</h3>
                                <p class="text-3xl font-bold text-[#2E5077]">{{ stats.hospitals.total }}</p>
                            </div>
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl group-hover:bg-[#2E5077]/20 transition-colors">
                                <Icon icon="solar:buildings-2-bold-duotone" class="w-8 h-8 text-[#2E5077]" />
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1 text-emerald-600 font-medium">
                                <Icon icon="solar:check-circle-bold" class="w-4 h-4" />
                                {{ stats.hospitals.active }} نشطة
                            </span>
                             <span class="flex items-center gap-1 text-red-500 font-medium">
                                <Icon icon="solar:close-circle-bold" class="w-4 h-4" />
                                {{ stats.hospitals.inactive }} غير نشطة
                            </span>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات المستخدمين -->
                <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                    <div class="h-2 bg-[#4DA1A9]"></div>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">المستخدمين</h3>
                                <p class="text-3xl font-bold text-[#2E5077]">{{ stats.users.total }}</p>
                            </div>
                            <div class="p-3 bg-[#4DA1A9]/10 rounded-xl group-hover:bg-[#4DA1A9]/20 transition-colors">
                                <Icon icon="solar:users-group-rounded-bold-duotone" class="w-8 h-8 text-[#4DA1A9]" />
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1 text-emerald-600 font-medium">
                                <Icon icon="solar:user-check-bold" class="w-4 h-4" />
                                {{ stats.users.active }} نشط
                            </span>
                             <span class="flex items-center gap-1 text-red-500 font-medium">
                                <Icon icon="solar:user-block-bold" class="w-4 h-4" />
                                {{ stats.users.inactive }} محظور
                            </span>
                        </div>
                    </div>
                </div>

                 <!-- إحصائيات المرضى -->
                <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                    <div class="h-2 bg-[#79D7BE]"></div>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">المرضى</h3>
                                <p class="text-3xl font-bold text-[#2E5077]">{{ stats.patients.total }}</p>
                            </div>
                            <div class="p-3 bg-[#79D7BE]/10 rounded-xl group-hover:bg-[#79D7BE]/20 transition-colors">
                                <Icon icon="solar:user-heart-bold-duotone" class="w-8 h-8 text-[#79D7BE]" />
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1 text-[#2E5077] font-medium">
                                <Icon icon="solar:calendar-add-bold" class="w-4 h-4" />
                                +{{ stats.patients.newThisMonth }} جديد هذا الشهر
                            </span>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات الأدوية -->
                <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                    <div class="h-2 bg-[#F6C445]"></div>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">الأدوية</h3>
                                <p class="text-3xl font-bold text-[#2E5077]">{{ stats.drugs.total }}</p>
                            </div>
                            <div class="p-3 bg-[#F6C445]/10 rounded-xl group-hover:bg-[#F6C445]/20 transition-colors">
                                <Icon icon="solar:pill-bold-duotone" class="w-8 h-8 text-[#F6C445]" />
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1 text-emerald-600 font-medium">
                                <Icon icon="solar:box-bold" class="w-4 h-4" />
                                {{ stats.drugs.available }} متوفر
                            </span>
                             <span v-if="stats.drugs.lowStock > 0" class="flex items-center gap-1 text-red-500 font-medium">
                                <Icon icon="solar:danger-triangle-bold" class="w-4 h-4" />
                                {{ stats.drugs.lowStock }} منخفض
                            </span>
                        </div>
                    </div>
                </div>
                
                 <!-- إحصائيات الوصفات -->
                <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                    <div class="h-2 bg-[#6A9C89]"></div>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">الوصفات الطبية</h3>
                                <p class="text-3xl font-bold text-[#2E5077]">{{ stats.prescriptions.total }}</p>
                            </div>
                            <div class="p-3 bg-[#6A9C89]/10 rounded-xl group-hover:bg-[#6A9C89]/20 transition-colors">
                                <Icon icon="solar:document-text-bold-duotone" class="w-8 h-8 text-[#6A9C89]" />
                            </div>
                        </div>
                         <div class="flex items-center gap-4 text-sm">
                            <span class="flex items-center gap-1 text-[#2E5077] font-medium">
                                <Icon icon="solar:calendar-mark-bold" class="w-4 h-4" />
                                {{ stats.prescriptions.thisMonth }} هذا الشهر
                            </span>
                        </div>
                    </div>
                </div>

                 <!-- إحصائيات الشكاوى -->
                <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                    <div class="h-2 bg-[#E76F51]"></div>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">الشكاوى</h3>
                                <p class="text-3xl font-bold text-[#2E5077]">{{ stats.complaints.total }}</p>
                            </div>
                            <div class="p-3 bg-[#E76F51]/10 rounded-xl group-hover:bg-[#E76F51]/20 transition-colors">
                                <Icon icon="solar:chat-round-call-bold-duotone" class="w-8 h-8 text-[#E76F51]" />
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span v-if="stats.complaints.pending > 0" class="flex items-center gap-1 text-[#E76F51] font-medium">
                                <Icon icon="solar:clock-circle-bold" class="w-4 h-4" />
                                {{ stats.complaints.pending }} قيد المراجعة
                            </span>
                            <span v-else class="flex items-center gap-1 text-emerald-600 font-medium">
                                <Icon icon="solar:check-circle-bold" class="w-4 h-4" />
                                الكل مكتمل
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</DefaultLayout>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.5s ease-out forwards;
}

/* تحسين الخطوط للأرقام */
.text-3xl {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    letter-spacing: -0.02em;
}
</style>
