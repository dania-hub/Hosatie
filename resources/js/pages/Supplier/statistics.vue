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
    shipments: {
        total: 0,
        pending: 0,
        approved: 0,
        fulfilled: 0,
        rejected: 0
    },
    drugs: {
        total: 0,
        lowStock: 0
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
        const response = await api.get('/supplier/dashboard/stats');
        // BaseApiController يُرجع البيانات في response.data.data
        const data = response.data?.data || response.data || {};
        
        // تحديث متغير stats بالبيانات الواردة من الـ API
        stats.value.shipments = {
            total: data.totalShipments || 0,
            pending: data.pendingShipments || 0,
            approved: data.approvedShipments || 0,
            fulfilled: data.fulfilledShipments || 0,
            rejected: data.rejectedShipments || 0
        };
        
        stats.value.drugs = {
            total: data.totalDrugs || 0,
            lowStock: data.lowStockDrugs || 0
        };
        
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
        <LoadingState 
            v-if="stats.isLoading" 
            message="جاري تحميل الإحصائيات..."
        />

        <!-- حالة الخطأ -->
        <ErrorState 
            v-else-if="stats.error" 
            title="خطأ في تحميل البيانات"
            :message="stats.error" 
            :retry="fetchStats"
        />

        <!-- الإحصائيات -->
        <div v-else class="space-y-8">
            <!-- قسم: الشحنات -->
            <div>
                <h2 class="text-2xl font-bold text-[#2E5077] mb-6 text-right flex items-center gap-3">
                    <Icon icon="solar:delivery-bold-duotone" class="w-8 h-8 text-[#4DA1A9]" />
                    الشحنات
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- طلبات التوريد الداخلية -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#2E5077] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl">
                                <Icon icon="solar:delivery-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">طلبات التوريد الداخلية</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.shipments.total }}</p>
                    </div>

                    <!-- قيد الانتظار -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#4DA1A9] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#4DA1A9]/10 rounded-xl">
                                <Icon icon="solar:clock-circle-bold-duotone" class="icon w-8 h-8 text-[#4DA1A9]" />
                            </div>
                            <p class="text text-lg font-bold text-[#4DA1A9]" style="text-align: right;">قيد الانتظار</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#4DA1A9]" style="text-align: right; width: 100%;">{{ stats.shipments.pending }}</p>
                    </div>

                    <!-- طلبات التوريد الخارجية -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#79D7BE] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#79D7BE]/10 rounded-xl">
                                <Icon icon="solar:check-circle-bold-duotone" class="icon w-8 h-8 text-[#79D7BE]" />
                            </div>
                            <p class="text text-lg font-bold text-[#79D7BE]" style="text-align: right;">طلبات التوريد الخارجية</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#79D7BE]" style="text-align: right; width: 100%;">{{ stats.shipments.approved }}</p>
                    </div>

                    <!-- تم الإرسال -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#2E5077] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl">
                                <Icon icon="solar:plain-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">تم الإرسال</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.shipments.fulfilled }}</p>
                    </div>

                    <!-- مرفوضة -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#4DA1A9] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#4DA1A9]/10 rounded-xl">
                                <Icon icon="solar:close-circle-bold-duotone" class="icon w-8 h-8 text-[#4DA1A9]" />
                            </div>
                            <p class="text text-lg font-bold text-[#4DA1A9]" style="text-align: right;">مرفوضة</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#4DA1A9]" style="text-align: right; width: 100%;">{{ stats.shipments.rejected }}</p>
                    </div>
                </div>
            </div>

            <!-- قسم: الأدوية -->
            <div>
                <h2 class="text-2xl font-bold text-[#2E5077] mb-6 text-right flex items-center gap-3">
                    <Icon icon="solar:pill-bold-duotone" class="w-8 h-8 text-[#4DA1A9]" />
                    الأدوية
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- إجمالي الأدوية -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#2E5077] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#2E5077]/10 rounded-xl">
                                <Icon icon="solar:pill-bold-duotone" class="icon w-8 h-8 text-[#2E5077]" />
                            </div>
                            <p class="text text-lg font-bold text-[#2E5077]" style="text-align: right;">إجمالي الأدوية</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#2E5077]" style="text-align: right; width: 100%;">{{ stats.drugs.total }}</p>
                    </div>

                    <!-- أدوية منخفضة المخزون -->
                    <div class="card bg-white p-6 rounded-2xl shadow-lg border-2 border-[#79D7BE] flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1" dir="rtl">
                        <div class="content flex items-center gap-3 mb-4" style="justify-content: flex-start;">
                            <div class="p-3 bg-[#79D7BE]/10 rounded-xl">
                                <Icon icon="solar:danger-triangle-bold-duotone" class="icon w-8 h-8 text-[#79D7BE]" />
                            </div>
                            <p class="text text-lg font-bold text-[#79D7BE]" style="text-align: right;">أدوية منخفضة المخزون</p>
                        </div>
                        <p class="number text-5xl font-bold text-[#79D7BE]" style="text-align: right; width: 100%;">{{ stats.drugs.lowStock }}</p>
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
