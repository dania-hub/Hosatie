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

// متغير لتخزين الإحصائيات مع الحقول الإضافية
const stats = ref({
    hospitals: { total: 0, active: 0, inactive: 0 },
    users: { total: 0, active: 0, inactive: 0 },
    patients: { total: 0, active: 0, newThisMonth: 0 },
    drugs: { total: 0, available: 0, lowStock: 0, byCategory: [] },
    prescriptions: { total: 0, active: 0, thisMonth: 0 },
    complaints: { total: 0, pending: 0, thisMonth: 0 },
    dispensing: { total: 0, thisMonth: 0, reverted: 0 },
    requests: {
        internal: { total: 0, pending: 0 },
        external: { total: 0, pending: 0 }
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
                lowStock: data.drugs.lowStock || 0,
                byCategory: data.drugs.byCategory || []
            };
        }
        
        if (data.dispensing) {
            stats.value.dispensing = data.dispensing;
        }

        if (data.requests) {
            stats.value.requests = data.requests;
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
        <div v-else class="space-y-10 animate-fade-in-up" dir="rtl">
            
            <!-- عنوان الصفحة والملخص السريع -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-l from-[#2E5077] to-[#4DA1A9] p-8 rounded-[2rem] text-white shadow-xl shadow-[#2E5077]/10">
                <div>
                    <h1 class="text-2xl font-bold mb-2">لوحة إحصائيات النظام</h1>
                    <p class="text-[#f0f9f9] text-sm md:text-base opacity-90">مرحباً بك في لوحة تحكم الإحصائيات العامة للنظام. هنا تتوفر نظرة شاملة على كافة موارد العمليات.</p>
                </div>
                <div class="flex gap-4">
                    <div class="bg-white/10 backdrop-blur-md px-6 py-3 rounded-2xl border border-white/20 text-center">
                        <p class="text-xs opacity-70 mb-1">إجمالي المرضى</p>
                        <p class="text-xl font-black">{{ stats.patients.total }}</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md px-6 py-3 rounded-2xl border border-white/20 text-center">
                        <p class="text-xs opacity-70 mb-1">وصفات الشهر</p>
                        <p class="text-xl font-black">{{ stats.prescriptions.thisMonth }}</p>
                    </div>
                </div>
            </div>

            <!-- شبكة البطاقات الأساسية -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- إحصائيات المؤسسات -->
                <div class="stat-card group border-b-4 border-[#2E5077]">
                    <div class="flex justify-between items-start mb-6">
                        <div class="p-3 bg-[#2E5077]/5 rounded-2xl text-[#2E5077] group-hover:bg-[#2E5077] group-hover:text-white transition-all duration-300">
                            <Icon icon="solar:buildings-2-bold-duotone" class="w-8 h-8" />
                        </div>
                        <span class="text-xs font-bold px-2 py-1 bg-gray-100 rounded-lg text-gray-500 italic">إحصائيات المنشآت</span>
                    </div>
                    <h3 class="text-gray-500 text-sm font-bold mb-1">المؤسسات الصحية</h3>
                    <div class="flex items-end gap-2 mb-4">
                        <p class="text-4xl font-black text-[#2E5077]">{{ stats.hospitals.total }}</p>
                        <span class="text-xs text-gray-400 pb-1">مؤسسة</span>
                    </div>
                    <div class="space-y-2 border-t pt-4 border-gray-100">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-400">نشطة حالياً</span>
                            <span class="text-emerald-600 font-bold">{{ stats.hospitals.active }}</span>
                        </div>
                        <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-emerald-500 h-full" :style="{ width: stats.hospitals.total > 0 ? (stats.hospitals.active / stats.hospitals.total * 100) + '%' : '0%' }"></div>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات المستخدمين -->
                <div class="stat-card group border-b-4 border-[#4DA1A9]">
                    <div class="flex justify-between items-start mb-6">
                        <div class="p-3 bg-[#4DA1A9]/5 rounded-2xl text-[#4DA1A9] group-hover:bg-[#4DA1A9] group-hover:text-white transition-all duration-300">
                            <Icon icon="solar:users-group-rounded-bold-duotone" class="w-8 h-8" />
                        </div>
                        <span class="text-xs font-bold px-2 py-1 bg-gray-100 rounded-lg text-gray-500 italic"> المستخدمين</span>
                    </div>
                    <h3 class="text-gray-500 text-sm font-bold mb-1">المستخدمين (طواقم)</h3>
                    <div class="flex items-end gap-2 mb-4">
                        <p class="text-4xl font-black text-[#2E5077]">{{ stats.users.total }}</p>
                        <span class="text-xs text-gray-400 pb-1">حساب</span>
                    </div>
                    <div class="space-y-2 border-t pt-4 border-gray-100">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-400">غير نشط / محظور</span>
                            <span class="text-red-500 font-bold">{{ stats.users.inactive }}</span>
                        </div>
                        <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-red-400 h-full" :style="{ width: stats.users.total > 0 ? (stats.users.inactive / stats.users.total * 100) + '%' : '0%' }"></div>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات الأدوية -->
                <div class="stat-card group border-b-4 border-[#F6C445]">
                    <div class="flex justify-between items-start mb-6">
                        <div class="p-3 bg-[#F6C445]/5 rounded-2xl text-[#F6C445] group-hover:bg-[#F6C445] group-hover:text-white transition-all duration-300">
                            <Icon icon="solar:pill-bold-duotone" class="w-8 h-8" />
                        </div>
                    </div>
                    <h3 class="text-gray-500 text-sm font-bold mb-1">الأدوية</h3>
                    <div class="flex items-end gap-2 mb-4">
                        <p class="text-4xl font-black text-[#2E5077]">{{ stats.drugs.total }}</p>
                        <span class="text-xs text-gray-400 pb-1">صنف</span>
                    </div>
                    <div class="flex gap-4 border-t pt-4 border-gray-100">
                        <div class="w-full text-center">
                            <p class="text-xs text-gray-400">إجمالي الأدوية المتوفرة حالياً</p>
                            <p class="font-black text-emerald-600 text-lg">{{ stats.drugs.available }}</p>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات الشكاوى -->
                <div class="stat-card group border-b-4 border-[#E76F51]">
                    <div class="flex justify-between items-start mb-6">
                        <div class="p-3 bg-[#E76F51]/5 rounded-2xl text-[#E76F51] group-hover:bg-[#E76F51] group-hover:text-white transition-all duration-300">
                            <Icon icon="solar:chat-round-call-bold-duotone" class="w-8 h-8" />
                        </div>
                        <span class="text-xs font-bold px-2 py-1 bg-red-50 rounded-lg text-red-500 italic">بانتظار المراجعة</span>
                    </div>
                    <h3 class="text-gray-500 text-sm font-bold mb-1">طلبات المراجعة</h3>
                    <div class="flex items-end gap-2 mb-4">
                        <p class="text-4xl font-black text-[#2E5077]">{{ stats.complaints.pending }}</p>
                        <span class="text-xs text-gray-400 pb-1">شكوى معلقة</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-400 border-t pt-4 border-gray-100">
                        <Icon icon="solar:clock-circle-linear" class="w-4 h-4" />
                        <span>إجمالي الشكاوى الكلي: {{ stats.complaints.total }}</span>
                    </div>
                </div>

            </div>

            <!-- الصف الثاني: تفاصيل العمليات -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- إحصائيات الصرف -->
                <div class="lg:col-span-2 bg-white rounded-[2rem] p-8 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-8 border-b pb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-[#6A9C89]/10 rounded-2xl flex items-center justify-center text-[#6A9C89]">
                                <Icon icon="solar:document-text-bold-duotone" class="w-7 h-7" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-[#2E5077]">نشاط صرف الأدوية</h2>
                                <p class="text-xs text-gray-400 italic">نظرة عامة على نشاط الصرف</p>
                            </div>
                        </div>
                        <div class="text-left">
                            <span class="text-xs bg-[#6A9C89]/10 text-[#6A9C89] px-3 py-1 rounded-full font-bold">مباشر</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="p-6 bg-gray-50 rounded-3xl text-center">
                            <p class="text-xs text-gray-400 mb-2">إجمالي الصرف</p>
                            <p class="text-3xl font-black text-[#2E5077]">{{ stats.dispensing.total }}</p>
                        </div>
                        <div class="p-6 bg-emerald-50 rounded-3xl text-center">
                            <p class="text-xs text-emerald-600 mb-2">صرف الشهر الحالي</p>
                            <p class="text-3xl font-black text-emerald-700">{{ stats.dispensing.thisMonth }}</p>
                        </div>
                        <div class="p-6 bg-red-50 rounded-3xl text-center">
                            <p class="text-xs text-red-500 mb-2">عمليات التراجع</p>
                            <p class="text-3xl font-black text-red-700">{{ stats.dispensing.reverted }}</p>
                        </div>
                    </div>


                </div>

                <!-- إحصائيات الطلبات -->
                <div class="bg-[#2E5077] rounded-[2rem] p-8 text-white shadow-xl">
                    <h2 class="text-xl font-bold mb-6 border-b border-white/10 pb-4 flex items-center gap-2">
                        <Icon icon="solar:transfer-horizontal-bold-duotone" class="w-6 h-6" />
                        طلبات التوريد
                    </h2>
                    
                    <div class="space-y-6 text-right">
                        <!-- طلبات داخلية -->
                        <div class="bg-white/5 p-5 rounded-2xl hover:bg-white/10 transition-colors cursor-default">
                            <div class="flex justify-between items-center mb-3">
                                <span class="bg-blue-400/20 text-blue-300 px-3 py-1 rounded-lg text-xs font-bold">داخلية</span>
                                <span class="text-2xl font-black">{{ stats.requests.internal.total }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-blue-200 opacity-80">
                                <span>قيد الانتظار</span>
                                <span class="font-bold underline">{{ stats.requests.internal.pending }} طلب</span>
                            </div>
                        </div>

                        <!-- طلبات خارجية -->
                        <div class="bg-white/5 p-5 rounded-2xl hover:bg-white/10 transition-colors cursor-default">
                            <div class="flex justify-between items-center mb-3">
                                <span class="bg-amber-400/20 text-amber-300 px-3 py-1 rounded-lg text-xs font-bold">خارجية</span>
                                <span class="text-2xl font-black">{{ stats.requests.external.total }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-amber-200 opacity-80">
                                <span>قيد الانتظار</span>
                                <span class="font-bold underline">{{ stats.requests.external.pending }} طلب</span>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t border-white/10 text-center">
                            <p class="text-[10px] opacity-50 uppercase tracking-widest italic font-medium">مراقبة سلسلة التوريد داخل النظام</p>
                        </div>
                    </div>
                </div>

            </div>

             <!-- الصف الثالث: توزيع الأصناف -->
             <div v-if="stats.drugs.byCategory && stats.drugs.byCategory.length > 0" class="bg-white rounded-[2rem] p-8 shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-10 h-10 bg-[#4DA1A9]/10 rounded-xl flex items-center justify-center text-[#4DA1A9]">
                        <Icon icon="solar:checklist-bold-duotone" class="w-6 h-6" />
                    </div>
                    <h2 class="text-lg font-bold text-[#2E5077]">توزيع الأدوية حسب الفئات (الأكثر توفراً)</h2>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div v-for="(cat, idx) in stats.drugs.byCategory.slice(0, 10)" :key="idx" 
                         class="p-4 bg-gray-50 rounded-2xl border border-transparent hover:border-[#4DA1A9]/30 hover:bg-white hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                        <p class="text-xs text-gray-400 mb-1 truncate">{{ cat.name }}</p>
                        <p class="text-xl font-black text-[#4DA1A9]">{{ cat.count }} <span class="text-[10px] text-gray-300 font-normal">صنف</span></p>
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
.text-4xl, .text-3xl, .text-xl {
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    letter-spacing: -0.03em;
}

.stat-card {
    background: white;
    border-radius: 2rem;
    padding: 2rem;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.stat-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
}
</style>
