<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue"; 

// ----------------------------------------------------
// 1. تعريف الـ Endpoint ومتغيرات الحالة
// ----------------------------------------------------
const API_BASE_URL = '/api/pharmacist';

// تكوين Axios مع التوكن
const api = axios.create({
    baseURL: API_BASE_URL,
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// إضافة التوكن تلقائياً من localStorage
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// متغير لتخزين الإحصائيات
const stats = ref({
    totalRegistered: 0,
    todayRegistered: 0,
    weekRegistered: 0,
    supplyRequestsCount: 0,
    receivedRequestsCount: 0,
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
        const response = await api.get('/dashboard/stats');
        
        // BaseApiController يُرجع البيانات بداخل data
        const responseData = response.data?.data ?? response.data;
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        if (responseData) {
            stats.value.totalRegistered = responseData.totalRegistered ?? 0;
            stats.value.todayRegistered = responseData.todayRegistered ?? 0;
            stats.value.weekRegistered = responseData.weekRegistered ?? 0;
            stats.value.supplyRequestsCount = responseData.supplyRequestsCount ?? 0;
            stats.value.receivedRequestsCount = responseData.receivedRequestsCount ?? 0;
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
        <!-- حالة التحميل -->
        <LoadingState v-if="stats.isLoading" message="جاري تحميل الإحصائيات..." />

        <!-- حالة الخطأ -->
        <ErrorState v-else-if="stats.error" :message="stats.error" :retry="fetchStats" />

        <!-- الإحصائيات -->
        <div v-else class="space-y-8">
            <!-- قسم: عمليات الصرف والخدمات -->
            <div>
                <h2 class="text-2xl font-bold text-[#2E5077] mb-6 text-right flex items-center gap-3">
                    <Icon icon="solar:check-read-bold-duotone" class="w-8 h-8 text-[#4DA1A9]" />
                    عمليات الصرف والخدمات
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#2E5077] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl">
                                <Icon icon="solar:check-read-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">عمليات الصرف المنجزة اليوم</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.totalRegistered }}</p>
                    </div>

                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#79D7BE] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#79D7BE]/10 rounded-xl">
                                <Icon icon="solar:users-group-rounded-bold-duotone" class="icon w-8 h-8 text-[#79D7BE]" />
                            </div>
                            <p class="text text-lg font-bold text-[#79D7BE]" style="text-align: right;">المرضى المخدمين هذا الأسبوع</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#79D7BE]" style="text-align: right; width: 100%;">{{ stats.weekRegistered }}</p>
                    </div>

                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#4DA1A9] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#4DA1A9]/10 rounded-xl">
                                <Icon icon="solar:danger-triangle-bold-duotone" class="icon w-8 h-8 text-[#4DA1A9]" />
                            </div>
                            <p class="text text-lg font-bold text-[#4DA1A9]" style="text-align: right;">الأصناف عند الحد الحرج</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#4DA1A9]" style="text-align: right; width: 100%;">{{ stats.todayRegistered }}</p>
                    </div>
                </div>
            </div>

            <!-- قسم: طلبات التوريد -->
            <div>
                <h2 class="text-2xl font-bold text-[#2E5077] mb-6 text-right flex items-center gap-3">
                    <Icon icon="solar:document-text-bold-duotone" class="w-8 h-8 text-[#4DA1A9]" />
                    طلبات التوريد
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#2E5077] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl">
                                <Icon icon="solar:document-text-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">إجمالي طلبات التوريد</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.supplyRequestsCount }}</p>
                    </div>

                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#4DA1A9] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#4DA1A9]/10 rounded-xl">
                                <Icon icon="solar:box-check-bold-duotone" class="icon w-8 h-8 text-[#4DA1A9]" />
                            </div>
                            <p class="text text-lg font-bold text-[#4DA1A9]" style="text-align: right;">عمليات الاستلام المنجزة</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#4DA1A9]" style="text-align: right; width: 100%;">{{ stats.receivedRequestsCount }}</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</DefaultLayout>
</template>

<style scoped>
/* تحسينات إضافية للبطاقات */
.card {
    backdrop-filter: blur(10px);
    direction: rtl;
    text-align: right;
}

.card:hover {
    transform: translateY(-4px);
}

/* تحسين الخطوط */
.number {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    letter-spacing: -0.02em;
    text-align: right !important;
    direction: rtl;
    display: block;
    width: 100%;
}

/* تحسين المسافات */
.content {
    min-height: 60px;
    direction: rtl;
    text-align: right;
}

.content p {
    text-align: right !important;
}

/* تحسين الظلال */
.shadow-lg {
    box-shadow: 0 10px 25px -5px rgba(46, 80, 119, 0.1), 0 10px 10px -5px rgba(46, 80, 119, 0.04);
}

.hover\:shadow-xl:hover {
    box-shadow: 0 20px 25px -5px rgba(46, 80, 119, 0.15), 0 10px 10px -5px rgba(46, 80, 119, 0.08);
}

/* ضمان محاذاة جميع النصوص لليمين */
.card * {
    text-align: right !important;
    direction: rtl;
}
</style>
