<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue"; 

// ----------------------------------------------------
// 1. تعريف الـ Endpoint ومتغيرات الحالة
// ----------------------------------------------------
const API_BASE_URL = '/api/pharmacist';

// تهيئة نسخة خاصة من Axios مع الـ baseURL والتوكن
const api = axios.create({
    baseURL: API_BASE_URL,
    timeout: 10000,
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
    isLoading: true, 
});

// ----------------------------------------------------
// 2. دالة جلب البيانات باستخدام Axios
// ----------------------------------------------------
const fetchStats = async () => {
    stats.value.isLoading = true;

    try {
        const response = await api.get('/dashboard/stats');
        
        // BaseApiController يُرجع البيانات بداخل data
        const responseData = response.data?.data ?? response.data;
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        if (responseData) {
            stats.value.totalRegistered = responseData.totalRegistered ?? 0;
            stats.value.todayRegistered = responseData.todayRegistered ?? 0;
            stats.value.weekRegistered = responseData.weekRegistered ?? 0;
        }
        
    } catch (error) {
        console.error("Error fetching dashboard statistics:", error);
        // في حالة الخطأ، نعرض القيم الافتراضية (0)
        stats.value.totalRegistered = 0;
        stats.value.todayRegistered = 0;
        stats.value.weekRegistered = 0;
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
                    <p class="text text-xl font-bold text-[#2E5077]"> عدد عمليات الصرف المنجزة اليوم </p>
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
                    <p class="text text-xl font-bold text-[#2E5077]"> عدد المرضى الذين تم خدمتهم هذا الأسبوع</p>
                </div>
                <p class="number text-4xl font-bold text-left text-white">{{ stats.weekRegistered }}</p>
            </div>
        </div>
    </main>
</DefaultLayout>
</template>

<style scoped>
/* لا حاجة لـ CSS هنا */
</style>