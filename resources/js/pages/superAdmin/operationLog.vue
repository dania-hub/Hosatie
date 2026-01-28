<script setup>
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from 'axios'; 
import DefaultLayout from "@/components/DefaultLayout.vue"; 

import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import Toast from "@/components/Shared/Toast.vue";


// إعداد axios مع interceptor لإضافة التوكن
const api = axios.create({
    baseURL: '/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

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

const operations = ref([]);
const isLoading = ref(false);
const error = ref(null);

// دالة جلب البيانات من نقطة النهاية (باستخدام Axios)
const fetchOperations = async () => {
    isLoading.value = true;
    error.value = null;
    try {
        const response = await api.get('/super-admin/patient-operations');
        operations.value = response.data;
    } catch (err) {
        console.error("Failed to fetch operations:", err);
        error.value = err.response?.data?.message || err.message || "فشل في تحميل البيانات.";
    } finally {
        isLoading.value = false;
    }
};

// تشغيل دالة جلب البيانات عند تحميل المكون
onMounted(() => {
    fetchOperations();
});

const toArabicNumerals = (str) => {
    if (str === null || str === undefined) return '';
    const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    return String(str).replace(/[0-9]/g, (w) => arabicNumbers[parseInt(w)]);
};
// تفاصيل العملية: إبقاء أرقام الطلبات (INT-xxx / EXT-xxx) بالأرقام الإنجليزية
const formatOperationBody = (str) => {
    if (str == null || str === '') return '';
    if (/(INT|EXT)-\d+/.test(str)) return str;
    return toArabicNumerals(str);
};

// قائمة بأنواع العمليات المتاحة للتصفية
const operationTypes = computed(() => {
    const types = new Set(operations.value.map(op => op.operation_type));
    return ['الكل', ...Array.from(types)];
});

// ----------------------------------------------------
// 2. منطق البحث والفرز والتصفية الموحد
// ----------------------------------------------------
const searchTerm = ref("");
const operationTypeFilter = ref("الكل");
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);

// حالة الفرز الحالية
const sortKey = ref('date');
const sortOrder = ref('desc');

// دالة تحويل التاريخ من صيغة (yyyy/mm/dd) إلى كائن Date للمقارنة
const parseDate = (dateString) => {
    if (!dateString) return new Date(0);
    const parts = dateString.split('/');
    // يتم إنشاء التاريخ بتنسيق (Year, MonthIndex, Day)
    return new Date(parts[0], parts[1] - 1, parts[2]);
};

// دالة لضبط معيار الفرز (الحقل والترتيب معًا)
const sortOperations = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const clearDateFilter = () => {
    dateFrom.value = "";
    dateTo.value = "";
};

// دالة محسوبة لتطبيق البحث والتصفية والفرز
const filteredOperations = computed(() => {
    let list = operations.value;
    const search = searchTerm.value ? searchTerm.value.toLowerCase() : '';

    // 1. التصفية (البحث ونص نوع العملية ورقم الملف)
    list = list.filter(op => {
        // تصفية حسب نص البحث
        const searchMatch = !search ||
                            op.file_number.toString().includes(search) ||
                            (op.full_name && op.full_name.toLowerCase().includes(search)) ||
                            (op.operation_label && op.operation_label.toLowerCase().includes(search)) ||
                            (op.operation_body && op.operation_body.toLowerCase().includes(search));

        // تصفية حسب نوع العملية
        const typeMatch = operationTypeFilter.value === 'الكل' ||
                          op.operation_type === operationTypeFilter.value;

        // تصفية حسب التاريخ
        let dateMatch = true;
        if (dateFrom.value || dateTo.value) {
            const opDate = parseDate(op.date);
            if (isNaN(opDate.getTime())) {
                dateMatch = false;
            } else {
                opDate.setHours(0, 0, 0, 0);

                let matchesFrom = true;
                let matchesTo = true;

                if (dateFrom.value) {
                    const fromDate = new Date(dateFrom.value);
                    fromDate.setHours(0, 0, 0, 0);
                    matchesFrom = opDate >= fromDate;
                }

                if (dateTo.value) {
                    const toDate = new Date(dateTo.value);
                    toDate.setHours(23, 59, 59, 999);
                    matchesTo = opDate <= toDate;
                }

                dateMatch = matchesFrom && matchesTo;
            }
        }

        return searchMatch && typeMatch && dateMatch;
    });

    // 2. الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === 'full_name') {
                comparison = (a.full_name || '').localeCompare(b.full_name || '', 'ar');
            } else if (sortKey.value === 'file_number') {
                comparison = a.file_number - b.file_number;
            } else if (sortKey.value === 'operation_type') {
                comparison = (a.operation_type || '').localeCompare(b.operation_type || '', 'ar');
            } else if (sortKey.value === 'date') {
                const dateA = parseDate(a.date);
                const dateB = parseDate(b.date);
                comparison = dateA.getTime() - dateB.getTime();
            }

            // تطبيق الترتيب التصاعدي/التنازلي
            return sortOrder.value === 'asc' ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 3. منطق رسالة النجاح (Success Alert Logic)
// ----------------------------------------------------
const toast = ref({
    show: false,
    type: 'success',
    title: '',
    message: ''
});

const showSuccessAlert = (message) => {
    const isError = message.startsWith('❌') || message.includes('فشل');
    toast.value = {
        show: true,
        type: isError ? 'error' : 'success',
        title: isError ? 'خطأ' : 'نجاح',
        message: message.replace(/^❌ |^✅ /, '')
    };
    // إخفاء التنبيه تلقائياً بعد 3 ثواني
    setTimeout(() => {
        toast.value.show = false;
    }, 3000);
};


// ----------------------------------------------------
// 4. منطق الطباعة (Print Logic)
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredOperations.value.length;
    const currentDate = new Date().toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'numeric', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });

    const printWindow = window.open('', '_blank', 'height=800,width=1000');
    
    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showSuccessAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
        return;
    }

    let tableHtml = `
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>طباعة سجل العمليات</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        @media print {
            @page { margin: 15mm; size: A4; }
            .no-print { display: none; }
        }
        
        * { box-sizing: border-box; font-family: 'Cairo', sans-serif; }
        body { padding: 0; margin: 0; color: #1e293b; background: white; line-height: 1.5; }
        
        .print-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            padding-bottom: 20px; 
            border-bottom: 2px solid #2E5077;
        }
        
        .gov-title { text-align: right; }
        .gov-title h2 { margin: 0; font-size: 20px; font-weight: 800; color: #2E5077; }
        .gov-title p { margin: 5px 0 0; font-size: 14px; color: #64748b; }
        
        .report-title { text-align: center; margin: 20px 0; }
        .report-title h1 { 
            margin: 0; 
            font-size: 24px; 
            color: #1e293b; 
            background: #f1f5f9;
            display: inline-block;
            padding: 10px 40px;
            border-radius: 50px;
        }
        
        .summary-box {
            display: grid;
            grid-template-cols: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .stat-item { display: flex; flex-direction: column; }
        .stat-label { font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 4px; }
        .stat-value { font-size: 14px; color: #2E5077; font-weight: 700; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        th { 
            background-color: #2E5077; 
            color: white; 
            font-weight: 700; 
            padding: 12px 10px; 
            text-align: right; 
            font-size: 12px;
        }
        td { 
            padding: 10px; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 11px; 
            color: #334155;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) { background-color: #f8fafc; }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #64748b;
        }
        
        .signature-box { text-align: right; }
        .signature-line { margin-top: 30px; width: 150px; border-top: 1px solid #cbd5e1; }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="page-header">
            <div class="gov-title">
                <h2>وزارة الصحة</h2>
                <p>سجل العمليات العام</p>
            </div>
            <div style="text-align: left">
                <p style="margin: 0; font-size: 11px; color: #64748b;">تاريخ التقرير</p>
                <p style="margin: 3px 0 0; font-weight: 700; color: #1e293b;">${currentDate}</p>
            </div>
        </div>

        <div class="report-title">
            <h1>تقرير سجل العمليات</h1>
        </div>

        <div class="summary-box">
            <div class="stat-item">
                <span class="stat-label">نوع العملية</span>
                <span class="stat-value">${operationTypeFilter.value}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">الفترة</span>
                <span class="stat-value">${dateFrom.value || 'من البداية'} - ${dateTo.value || 'اليوم'}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">إجمالي العمليات</span>
                <span class="stat-value">${resultsCount} عملية</span>
            </div>
             <div class="stat-item">
                <span class="stat-label">حالة البحث</span>
                <span class="stat-value">${searchTerm.value || 'عرض الكل'}</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th>رقم الملف</th>
                    <th>الاسم الرباعي</th>
                    <th>تفاصيل العملية</th>
                    <th>تاريخ العملية</th>
                </tr>
            </thead>
            <tbody>
                ${filteredOperations.value.map((op, index) => `
                <tr>
                    <td style="text-align: center; font-weight: 700; color: #64748b;">${index + 1}</td>
                    <td style="font-weight: 700; color: #2E5077;">${op.file_number || '-'}</td>
                    <td style="font-weight: 600;">${op.full_name || '-'}</td>
                    <td>
                        <div style="font-weight: 700; color: #4DA1A9;">${toArabicNumerals(op.operation_label || '-')}</div>
                        <div style="font-size: 10px; color: #64748b; margin-top: 2px;">${formatOperationBody(op.operation_body || '')}</div>
                    </td>
                    <td style="white-space: nowrap;">${op.date || '-'}</td>
                </tr>
                `).join('')}
            </tbody>
        </table>

        <div class="footer">
            <div class="signature-box">
                <p>اعتماد مدير الإدارة</p>
                <div class="signature-line"></div>
            </div>
            <div style="text-align: left;">
                <p>نظام حُصتي لإدارة المرافق الصحية</p>
                <p style="font-size: 9px; margin-top: 5px;">تم استخراج هذا التقرير آلياً</p>
            </div>
        </div>
    </div>
</body>
</html>
`;

    printWindow.document.write(tableHtml);
    printWindow.document.close();

    printWindow.onload = () => {
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            showSuccessAlert("✅ تم تجهيز التقرير بنجاح للطباعة.");
        }, 250);
    };
};


const openViewModal = (op) => console.log('عرض العملية:', op);
const openEditModal = (op) => console.log('تعديل العملية:', op);

</script>
<template>
 <DefaultLayout>
            <main class="flex-1 p-4 sm:p-5 pt-3">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0">
                    
                    <div class="flex items-center gap-3 w-full sm:max-w-3xl">
                        <div class="relative w-full sm:max-w-lg">
                            <search v-model="searchTerm" placeholder="ابحث برقم الملف  أو معرف الدواء أو معرف المؤسسة" />
                        </div>

                        <button
                            @click="showDateFilter = !showDateFilter"
                            class="h-11 w-23 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
                            :title="showDateFilter ? 'إخفاء فلتر التاريخ' : 'إظهار فلتر التاريخ'"
                        >
                            <Icon icon="solar:calendar-bold" class="w-5 h-5" />
                        </button>

                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-150 ease-in"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <div v-if="showDateFilter" class="flex items-center gap-2">
                                <span class="text-xs font-bold text-[#2E5077] whitespace-nowrap bg-white px-3 py-1 rounded-full border border-[#4DA1A9]/20 shadow-sm">
                                    فلترة بأيام محددة:
                                </span>
                                <div class="relative">
                                    <input
                                        type="date"
                                        v-model="dateFrom"
                                        class="h-11 px-3 pr-10 border-2 border-[#ffffff8d] rounded-[30px] bg-white text-gray-700 focus:outline-none focus:border-[#4DA1A9] text-sm cursor-pointer"
                                        placeholder="من تاريخ"
                                    />
                                    <Icon
                                        icon="solar:calendar-linear"
                                        class="w-5 h-5 text-[#4DA1A9] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                                    />
                                </div>
                                <span class="text-gray-600 font-medium">إلى</span>
                                <div class="relative">
                                    <input
                                        type="date"
                                        v-model="dateTo"
                                        class="h-11 px-3 pr-10 border-2 border-[#ffffff8d] rounded-[30px] bg-white text-gray-700 focus:outline-none focus:border-[#4DA1A9] text-sm cursor-pointer"
                                        placeholder="إلى تاريخ"
                                    />
                                    <Icon
                                        icon="solar:calendar-linear"
                                        class="w-5 h-5 text-[#4DA1A9] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                                    />
                                </div>
                                <button
                                    v-if="dateFrom || dateTo"
                                    @click="clearDateFilter"
                                    class="h-11 px-3 border-2 border-red-300 rounded-[30px] bg-red-50 text-red-600 hover:bg-red-100 transition-colors flex items-center gap-1"
                                    title="مسح فلتر التاريخ"
                                >
                                    <Icon icon="solar:close-circle-bold" class="w-4 h-4" />
                                    مسح
                                </button>
                            </div>
                        </Transition>
                        
                        <div class="dropdown dropdown-start">
                            <div tabindex="0" role="button" class=" inline-flex items-center px-6 py-[9px] border-2 border-[#ffffff8d] h-11
                                rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden whitespace-nowrap
                                text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                                <Icon icon="lucide:filter" class="w-5 h-5 ml-2" />
                                تصفية: {{ operationTypeFilter }}
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8]
                                rounded-[35px] w-52 text-right">
                                <li class="menu-title text-gray-700 font-bold text-sm">حسب نوع العملية:</li>
                                <li v-for="type in operationTypes" :key="type">
                                    <a @click="operationTypeFilter = type"
                                        :class="{'font-bold text-[#4DA1A9]': operationTypeFilter === type}">
                                        {{ type }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="dropdown dropdown-start">
                            <div tabindex="0" role="button" class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23
                                rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden
                                text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                                <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
                                فرز
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] border-[#ffffff8d]
                                rounded-[35px] w-52 text-right">
                                
                                <li class="menu-title text-gray-700 font-bold text-sm">حسب تاريخ العملية:</li>
                                <li>
                                    <a @click="sortOperations('date', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'date' && sortOrder === 'desc'}">
                                        الأحدث أولاً (تنازلي)
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('date', 'asc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'date' && sortOrder === 'asc'}">
                                        الأقدم أولاً (تصاعدي)
                                    </a>
                                </li>
                                
                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب الاسم:</li>
                                <li>
                                    <a @click="sortOperations('full_name', 'asc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'full_name' && sortOrder === 'asc'}">
                                        الاسم (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('full_name', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'full_name' && sortOrder === 'desc'}">
                                        الاسم (ي - أ)
                                    </a>
                                </li>
                            </ul>
                            
                        </div>
                        
                        <p class="text-sm font-semibold text-gray-600 self-end sm:self-center ">
                            عدد النتائج :
                            <span class="text-[#4DA1A9] text-lg font-bold">{{ filteredOperations.length }}</span>
                        </p>
                    </div>


                    <div class="flex items-center gap-5 w-full sm:w-auto justify-end">
                    
                        
                        <btnprint @click="printTable" />
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col">
                    <div
                        class="overflow-y-auto flex-1"
                        style="
                            scrollbar-width: auto;
                            scrollbar-color: grey transparent;
                            direction: ltr;
                        "
                    >
                        <div class="overflow-x-auto h-full">
                            <table dir="rtl" class="table w-full text-right min-w-[700px] border-collapse">
                                <thead class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300">
                                    <tr>
                                        <th class="file-number-col">رقم الملف</th>
                                        <th class="name-col"> الاسم</th>
                                        <th class="operation-type-col">نوع العملية</th>
                                        <th class="operation-date-col">تاريخ العملية</th>
                                        </tr>
                                </thead>

                                <tbody class="text-gray-800">
                                    <tr v-if="isLoading">
                                        <td colspan="5" class="p-4">
                                            <TableSkeleton :rows="10" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="error">
                                        <td colspan="5" class="py-12">
                                            <ErrorState :message="error" :retry="fetchOperations" />
                                        </td>
                                    </tr>
                                    <template v-else>
                                        <tr
                                            v-for="(op, index) in filteredOperations"
                                            :key="index"
                                            class="hover:bg-gray-100 border border-gray-300"
                                        >
                                            <td class="file-number-col">{{ op.file_number }}</td>
                                            
                                            <td class="name-col">{{ op.full_name }}</td>
                                            <td class="operation-type-col">
                                                <div class="flex flex-col">
                                                    <span class="text-[#4DA1A9] font-bold text-base mb-1">{{ toArabicNumerals(op.operation_label) }}</span>
                                                    <span class="text-gray-600 text-sm">{{ formatOperationBody(op.operation_body) }}</span>
                                                </div>
                                            </td>
                                            <td class="operation-date-col">{{ op.date }}</td>
                                        </tr>
                                        <tr v-if="filteredOperations.length === 0">
                                            <td colspan="5" class="py-12">
                                                <EmptyState message="لا توجد عمليات حالياً" />
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </DefaultLayout>

    <Toast 
        :show="toast.show" 
        :type="toast.type" 
        :title="toast.title" 
        :message="toast.message" 
        @close="toast.show = false" 
    />

</template>

<style>
/* 14. تنسيقات شريط التمرير */
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background-color: #4da1a9;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background-color: #3a8c94;
}

/* 15. تنسيقات عرض أعمدة الجدول الجديدة والمعدلة */
/* تم حذف تنسيق .actions-col */
.file-number-col {
    width: 90px;
    min-width: 90px;
}
.operation-type-col {
    width: 120px;
    min-width: 120px;
}
.operation-date-col {
    width: 120px;
    min-width: 120px;
}
.name-col {
    width: 170px;
    min-width: 150px;
}
.hospital-col {
    width: 150px;
    min-width: 150px;
}
</style>
