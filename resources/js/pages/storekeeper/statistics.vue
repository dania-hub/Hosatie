<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue"; 

// ----------------------------------------------------
// 0. منطق رسالة النجاح (Success Alert Logic) - يجب تعريفه قبل الاستخدام
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
    isLoading: false, 
});

// ----------------------------------------------------
// 2. دالة جلب البيانات باستخدام Axios
// ----------------------------------------------------
const fetchStats = async () => {
    stats.value.isLoading = true;

    try {
        const response = await api.get(API_URL);
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        stats.value.totalRegistered = response.totalRegistered || 0;
        stats.value.todayRegistered = response.todayRegistered || 0;
        stats.value.weekRegistered = response.weekRegistered || 0;
        
        showSuccessAlert("✅ تم تحميل الإحصائيات بنجاح.");
    } catch (error) {
        console.error("Error fetching dashboard statistics:", error);
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
    <main class="flex-1 p-4 sm:p-8 pt-20 sm:pt-30">
        
        <div 
            v-if="stats.isLoading" 
            class="flex justify-center items-center h-20 text-[#2E5077] font-semibold"
        >
            جاري تحميل الإحصائيات...
            <Icon icon="svg-spinners:ring-resize" class="w-6 h-6 mr-2" />
        </div>

        <div v-else class="cards flex flex-col md:flex-row md:justify-between items-center mb-6 group gap-4">

            <div class="card bg-[#A0B0BF] text-white p-4 w-full md:w-72 h-36 rounded-xl shadow-lg border-4 border-[#2E5077] flex flex-col justify-between transition duration-300 ease-in-out
                group-hover:blur-xs
                hover:!blur-none">
                <div class="content flex items-center gap-2 justify-end">
                    <Icon icon="lucide:users" class="icon w-10 h-10 text-[#2E5077]" />
                    <p class="text text-xl font-bold text-[#2E5077]"> إجمالي عدد الطلبات </p>
                </div>
                <p class="number text-4xl font-bold text-left text-white">{{ stats.totalRegistered }}</p>
            </div>

            <div class="card bg-[#c9dcdd] text-white p-4 w-full md:w-72 h-36 rounded-xl shadow-lg border-4 border-[#4DA1A9] flex flex-col justify-between transition duration-300 ease-in-out
                group-hover:blur-xs
                hover:!blur-none">
                <div class="content flex items-center gap-2 justify-end">
                    <Icon icon="material-symbols:add-box-rounded" class="icon w-10 h-10 text-[#4DA1A9]" />
                    <p class="text text-xl font-bold text-[#2E5077]">عدد الأصناف التي وصلت للحد الحرج</p>
                </div>
                <p class="number text-4xl font-bold text-left text-white">{{ stats.todayRegistered }}</p>
            </div>

            <div class="card bg-[#cce8e0] text-white p-4 w-full md:w-72 h-36 rounded-xl shadow-lg border-4 border-[#79D7BE] flex flex-col justify-between transition duration-300 ease-in-out
                group-hover:blur-xs
                hover:!blur-none">
                <div class="content flex items-center gap-2 justify-end">
                    <Icon icon="simple-line-icons:calender" class="icon w-10 h-10 text-[#79D7BE]" />
                    <p class="text text-xl font-bold text-[#2E5077]"> عدد طلبات قيد الاستلام</p>
                </div>
                <p class="number text-4xl font-bold text-left text-white">{{ stats.weekRegistered }}</p>
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
/* لا حاجة لـ CSS هنا */
</style>