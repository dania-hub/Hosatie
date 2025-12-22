<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue"; 

// ----------------------------------------------------
// 1. تعريف الـ Endpoint ومتغيرات الحالة
// ----------------------------------------------------
const API_URL = '/api/doctor/dashboard/stats';

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
    totalRegistered: 0,
    todayRegistered: 0,
    weekRegistered: 0,
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
        const response = await api.get('/doctor/dashboard/stats');
        const data = response.data?.data || response.data || {};
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        stats.value.totalRegistered = data.totalRegistered || 0;
        stats.value.todayRegistered = data.todayRegistered || 0;
        stats.value.weekRegistered = data.weekRegistered || 0;
        
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
        <div 
            v-if="stats.isLoading" 
            class="flex flex-col justify-center items-center h-64 text-[#2E5077]"
        >
            <Icon icon="svg-spinners:ring-resize" class="w-12 h-12 mb-4" />
            <p class="font-semibold text-lg">جاري تحميل الإحصائيات...</p>
        </div>

        <!-- حالة الخطأ -->
        <div 
            v-else-if="stats.error" 
            class="bg-red-50 border-2 border-red-300 rounded-2xl p-6 text-center shadow-md"
        >
            <Icon icon="solar:danger-circle-bold-duotone" class="w-12 h-12 text-red-500 mx-auto mb-2" />
            <p class="text-red-700 font-semibold">{{ stats.error }}</p>
        </div>

        <!-- الإحصائيات -->
        <div v-else class="space-y-8">
            <div>
                <h2 class="text-2xl font-bold text-[#2E5077] mb-6 text-right flex items-center gap-3">
                    <Icon icon="solar:chart-2-bold-duotone" class="w-8 h-8 text-[#4DA1A9]" />
                    الإحصائيات
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#2E5077] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl">
                                <Icon icon="solar:users-group-rounded-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">إجمالي عدد المرضى</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.totalRegistered }}</p>
                    </div>

                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#4DA1A9] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#4DA1A9]/10 rounded-xl">
                                <Icon icon="solar:document-add-bold-duotone" class="icon w-8 h-8 text-[#4DA1A9]" />
                            </div>
                            <p class="text text-lg font-bold text-[#4DA1A9]" style="text-align: right;">عدد الكشوفات اليوم</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#4DA1A9]" style="text-align: right; width: 100%;">{{ stats.todayRegistered }}</p>
                    </div>

                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#79D7BE] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#79D7BE]/10 rounded-xl">
                                <Icon icon="solar:calendar-mark-bold-duotone" class="icon w-8 h-8 text-[#79D7BE]" />
                            </div>
                            <p class="text text-lg font-bold text-[#79D7BE]" style="text-align: right;">عدد الحالات التي تتابعها</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#79D7BE]" style="text-align: right; width: 100%;">{{ stats.weekRegistered }}</p>
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
