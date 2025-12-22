<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue"; 

// ----------------------------------------------------
// 0. منطق رسالة النجاح (Success Alert Logic)
// ----------------------------------------------------
const isSuccessAlertVisible = ref(false);
const successMessage = ref("");
let alertTimeout = null;

const showSuccessAlert = (message) => {
    if (alertTimeout) {
        clearTimeout(alertTimeout);
    }
    
    successMessage.value = message;
    isSuccessAlertVisible.value = true;
    
    alertTimeout = setTimeout(() => {
        isSuccessAlertVisible.value = false;
        successMessage.value = "";
    }, 4000);
};

// إعداد axios مع base URL و interceptor للتوكن
const api = axios.create({
    baseURL: '/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة interceptor لإضافة التوكن تلقائياً
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

// إضافة interceptor للتعامل مع الأخطاء
api.interceptors.response.use(
    (response) => {
        return response.data;
    },
    (error) => {
        console.error('API Error:', error.response?.data || error.message);
        if (error.response?.status === 401) {
            showSuccessAlert('❌ انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.');
        } else if (error.response?.status === 403) {
            showSuccessAlert('❌ ليس لديك الصلاحية للوصول إلى هذه البيانات.');
        } else if (!error.response) {
            showSuccessAlert('❌ فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
        }
        return Promise.reject(error);
    }
);

// ----------------------------------------------------
// 1. تعريف الـ Endpoint ومتغيرات الحالة
// ----------------------------------------------------
const API_URL = '/storekeeper/dashboard/stats';

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
        const response = await api.get(API_URL);
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        stats.value.totalRegistered = response.totalRegistered || 0;
        stats.value.todayRegistered = response.todayRegistered || 0;
        stats.value.weekRegistered = response.weekRegistered || 0;
        
        showSuccessAlert("✅ تم تحميل الإحصائيات بنجاح.");
    } catch (error) {
        console.error("Error fetching dashboard statistics:", error);
        stats.value.error = error.response?.data?.message || 'فشل تحميل الإحصائيات';
        if (!error.response || (error.response.status !== 401 && error.response.status !== 403)) {
            showSuccessAlert("❌ فشل في تحميل الإحصائيات.");
        }
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
                                <Icon icon="solar:document-text-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">إجمالي عدد الطلبات</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.totalRegistered }}</p>
                    </div>

                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#4DA1A9] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#4DA1A9]/10 rounded-xl">
                                <Icon icon="solar:danger-triangle-bold-duotone" class="icon w-8 h-8 text-[#4DA1A9]" />
                            </div>
                            <p class="text text-lg font-bold text-[#4DA1A9]" style="text-align: right;">عدد الأصناف التي وصلت للحد الحرج</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#4DA1A9]" style="text-align: right; width: 100%;">{{ stats.todayRegistered }}</p>
                    </div>

                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#79D7BE] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#79D7BE]/10 rounded-xl">
                                <Icon icon="solar:clock-circle-bold-duotone" class="icon w-8 h-8 text-[#79D7BE]" />
                            </div>
                            <p class="text text-lg font-bold text-[#79D7BE]" style="text-align: right;">عدد طلبات قيد الاستلام</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#79D7BE]" style="text-align: right; width: 100%;">{{ stats.weekRegistered }}</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</DefaultLayout>

    <Transition
        enter-active-class="transition duration-300 ease-out transform"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition duration-200 ease-in transform"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
    >
        <div 
            v-if="isSuccessAlertVisible" 
            class="fixed top-4 right-55 z-[1000] p-4 text-right bg-[#a2c4c6] text-white rounded-lg shadow-xl max-w-xs transition-all duration-300"
            dir="rtl"
        >
            {{ successMessage }}
        </div>
    </Transition>
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
