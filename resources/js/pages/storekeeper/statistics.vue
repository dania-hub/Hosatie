<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue"; 
import ErrorState from "@/components/Shared/ErrorState.vue"; 
import LoadingState from "@/components/Shared/LoadingState.vue";
import Toast from "@/components/Shared/Toast.vue";
 

// ----------------------------------------------------
// 0. نظام التنبيهات المطور (Toast System)
// ----------------------------------------------------
const isAlertVisible = ref(false);
const alertMessage = ref("");
const alertType = ref("success");
let alertTimeout = null;

const showAlert = (message, type = "success") => {
    if (alertTimeout) {
        clearTimeout(alertTimeout);
    }

    alertMessage.value = message;
    alertType.value = type;
    isAlertVisible.value = true;

    alertTimeout = setTimeout(() => {
        isAlertVisible.value = false;
    }, 4000);
};

const showSuccessAlert = (message) => showAlert(message, "success");
const showErrorAlert = (message) => showAlert(message, "error");
const showWarningAlert = (message) => showAlert(message, "warning");
const showInfoAlert = (message) => showAlert(message, "info");

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
            showErrorAlert(' انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.');
        } else if (error.response?.status === 403) {
            showWarningAlert(' ليس لديك الصلاحية للوصول إلى هذه البيانات.');
        } else if (!error.response) {
            showErrorAlert(' فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
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
    totalInternal: 0,
    totalExternal: 0,
    criticalItems: 0,
    preparingRequests: 0
});

const isLoading = ref(true);
const error = ref(null);

// ----------------------------------------------------
// 2. دالة جلب البيانات باستخدام Axios
// ----------------------------------------------------
const fetchStats = async () => {
    isLoading.value = true;
    error.value = null;

    try {
        const response = await api.get(API_URL);
        
        // BaseApiController::sendSuccess يلف البيانات في response.data
        // والـ interceptor يعيد response.data من axios، لذا response يحتوي على {success, message, data}
        // نحتاج للوصول إلى response.data للحصول على البيانات الفعلية
        const data = response?.data || response;
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        stats.value.totalInternal = data?.totalInternal ?? 0;
        stats.value.totalExternal = data?.totalExternal ?? 0;
        stats.value.criticalItems = data?.criticalItems ?? 0;
        stats.value.preparingRequests = data?.preparingRequests ?? 0;
        
        console.log('Statistics data:', { response, data, stats: stats.value }); // للتصحيح
        
    } catch (err) {
        console.error("Error fetching dashboard statistics:", err);
        error.value = err.response?.data?.message || err.message || 'فشل تحميل الإحصائيات';
    } finally {
        isLoading.value = false;
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
        <div v-if="isLoading" class="flex justify-center h-64 items-center">
             <LoadingState title="جاري تحميل الإحصائيات..." />
        </div>

        <!-- حالة الخطأ -->
        <div v-else-if="error" class="flex justify-center h-64 items-center">
            <ErrorState :message="error" :retry="fetchStats" />
        </div>

        <!-- الإحصائيات -->
        <div v-else class="space-y-8">
            <div>
                <h2 class="text-2xl font-bold text-[#2E5077] mb-6 text-right flex items-center gap-3">
                    <Icon icon="solar:chart-2-bold-duotone" class="w-8 h-8 text-[#4DA1A9]" />
                    الإحصائيات
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- كارد الطلبات الداخلية -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#2E5077] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl">
                                <Icon icon="solar:home-smile-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">الطلبات الداخلية</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.totalInternal }}</p>
                    </div>

                    <!-- كارد الطلبات الخارجية -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#4DA1A9] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#4DA1A9]/10 rounded-xl">
                                <Icon icon="solar:document-text-bold-duotone" class="icon w-8 h-8 text-[#4DA1A9]" />
                            </div>
                            <p class="text text-lg font-bold text-[#4DA1A9]" style="text-align: right;">الطلبات الخارجية</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#4DA1A9]" style="text-align: right; width: 100%;">{{ stats.totalExternal }}</p>
                    </div>

                    <!-- كارد الأصناف الحرجة -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#2E5077] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl">
                                <Icon icon="solar:danger-triangle-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">عدد الأصناف التي وصلت للحد الحرج</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.criticalItems }}</p>
                    </div>

                    <!-- كارد طلبات قيد الاستلام -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#79D7BE] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#79D7BE]/10 rounded-xl">
                                <Icon icon="solar:clock-circle-bold-duotone" class="icon w-8 h-8 text-[#79D7BE]" />
                            </div>
                            <p class="text text-lg font-bold text-[#79D7BE]" style="text-align: right;">عدد طلبات قيد الاستلام</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#79D7BE]" style="text-align: right; width: 100%;">{{ stats.preparingRequests }}</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</DefaultLayout>

        <Toast
            :show="isAlertVisible"
            :message="alertMessage"
            :type="alertType"
            @close="isAlertVisible = false"
        />
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
