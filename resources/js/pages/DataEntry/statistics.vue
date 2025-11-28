<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios';
import { Icon } from "@iconify/vue";
import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";

// ----------------------------------------------------
// 1. تعريف الـ Endpoint ومتغيرات الحالة
// ----------------------------------------------------
const API_URL = '/api/dashboard/stats';

// متغير لتخزين الإحصائيات (قيم افتراضية)
const stats = ref({
    totalRegistered: 0,
    todayRegistered: 0,
    weekRegistered: 0,
    isLoading: true, // لتتبع حالة التحميل
});

// ----------------------------------------------------
// 2. دالة جلب البيانات باستخدام Axios
// ----------------------------------------------------
const fetchStats = async () => {
    stats.value.isLoading = true;

    try {
        const response = await axios.get(API_URL);
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        stats.value.totalRegistered = response.data.totalRegistered;
        stats.value.todayRegistered = response.data.todayRegistered;
        stats.value.weekRegistered = response.data.weekRegistered;
        
    } catch (error) {
        // في حالة فشل الاتصال:
        // 1. سيتم تسجيل الخطأ في الـ Console (للمطور).
        console.error("Error fetching dashboard statistics:", error);
        // 2. ستبقى قيم الإحصائيات على القيم الافتراضية (0).
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
    <div class="drawer" dir="rtl">
        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />
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
                            <p class="text text-xl font-bold text-[#2E5077]">العدد الكلي للمسجلين</p>
                        </div>
                        <p class="number text-4xl font-bold text-left text-white">{{ stats.totalRegistered }}</p>
                    </div>

                    <div class="card bg-[#c9dcdd] text-white p-4 w-full md:w-72 h-36 rounded-xl shadow-lg border-4 border-[#4DA1A9] flex flex-col justify-between transition duration-300 ease-in-out
                        group-hover:blur-xs
                        hover:!blur-none">
                        <div class="content flex items-center gap-2 justify-end">
                            <Icon icon="material-symbols:add-box-rounded" class="icon w-10 h-10 text-[#4DA1A9]" />
                            <p class="text text-xl font-bold text-[#2E5077]">عدد المسجلين خلال اليوم</p>
                        </div>
                        <p class="number text-4xl font-bold text-left text-white">{{ stats.todayRegistered }}</p>
                    </div>

                    <div class="card bg-[#cce8e0] text-white p-4 w-full md:w-72 h-36 rounded-xl shadow-lg border-4 border-[#79D7BE] flex flex-col justify-between transition duration-300 ease-in-out
                        group-hover:blur-xs
                        hover:!blur-none">
                        <div class="content flex items-center gap-2 justify-end">
                            <Icon icon="simple-line-icons:calender" class="icon w-10 h-10 text-[#79D7BE]" />
                            <p class="text text-xl font-bold text-[#2E5077]">عدد المسجلين خلال الأسبوع</p>
                        </div>
                        <p class="number text-4xl font-bold text-left text-white">{{ stats.weekRegistered }}</p>
                    </div>
                </div>
            </main>
        </div>

        <Sidebar />
    </div>
</template>

<style scoped>
/* لا حاجة لـ CSS هنا */
</style>