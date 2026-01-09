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
const selectedDate = ref("");

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
        if (selectedDate.value) {
            const [y, m, d] = selectedDate.value.split('-').map(Number);
            // إنشاء تاريخ من المدخل (توقيت محلي 00:00:00)
            const inputTime = new Date(y, m - 1, d).getTime();
            
            // إنشاء تاريخ من البيانات (توقيت محلي 00:00:00)
            const opTime = parseDate(op.date).getTime();
            
            dateMatch = inputTime === opTime;
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
};


// ----------------------------------------------------
// 4. منطق الطباعة (Print Logic)
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredOperations.value.length;

    const printWindow = window.open('', '_blank', 'height=600,width=800');
    
    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showSuccessAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
        return;
    }

    let tableHtml = `
        <style>
            body {
                font-family: 'Arial', sans-serif;
                direction: rtl;
                padding: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            th, td {
                border: 1px solid #ccc;
                padding: 10px;
                text-align: right;
            }
            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            h1 {
                text-align: center;
                color: #2E5077;
                margin-bottom: 10px;
            }
            .results-info {
                text-align: right;
                margin-bottom: 15px;
                font-size: 16px;
                font-weight: bold;
                color: #4DA1A9;
            }
        </style>

        <h1>سجل العمليات (تقرير طباعة)</h1>
        
        <p class="results-info">
            عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>رقم الملف</th>
                    <th>الإسم الرباعي</th>
                    <th>نوع العملية</th>
                    <th>تاريخ العملية</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredOperations.value.forEach(op => {
        tableHtml += `
            <tr>
                <td>${op.file_number || '-'}</td>
                <td>${op.full_name || '-'}</td>
                <td>
                    <strong>${op.operation_label || '-'}</strong><br>
                    <small>${op.operation_body || ''}</small>
                </td>
                <td>${op.date || '-'}</td>
            </tr>
        `;
    });

    tableHtml += `
            </tbody>
        </table>
    `;

    printWindow.document.write('<html><head><title>طباعة سجل العمليات</title>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(tableHtml);
    printWindow.document.write('</body></html>');
    printWindow.document.close();

    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
        showSuccessAlert("✅ تم تجهيز التقرير بنجاح للطباعة.");
    };
};


const openViewModal = (op) => console.log('عرض العملية:', op);
const openEditModal = (op) => console.log('تعديل العملية:', op);

</script>
<template>
 <DefaultLayout>
            <main class="flex-1 p-4 sm:p-5 pt-3">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0">
                    
                    <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <div class="relative w-full sm:max-w-xs">
                            <search v-model="searchTerm" placeholder="ابحث برقم الملف الطبي" />
                        </div>

                         <div class="relative">
                            <input 
                                type="date" 
                                v-model="selectedDate"
                                class="h-11 px-4 rounded-[30px] border-2 border-gray-200 outline-none text-sm text-gray-600 focus:border-[#4DA1A9] transition-all bg-white"
                            />
                        </div>
                        
                        <div class="dropdown dropdown-start">
                            <div tabindex="0" role="button" class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11
                                rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden
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
                                        <th class="name-col">المفعول به</th>
                                        <th class="operation-type-col">التفاصيل</th>
                                        <th class="operation-date-col">تاريخ العملية</th>
                                        </tr>
                                </thead>

                                <tbody class="text-gray-800">
                                    <tr v-if="isLoading">
                                        <td colspan="4" class="p-4">
                                            <TableSkeleton :rows="10" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="error">
                                        <td colspan="4" class="py-12">
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
                                                    <span class="text-[#4DA1A9] font-bold text-base mb-1">{{ op.operation_label }}</span>
                                                    <span class="text-gray-600 text-sm">{{ op.operation_body }}</span>
                                                </div>
                                            </td>
                                            <td class="operation-date-col">{{ op.date }}</td>
                                        </tr>
                                        <tr v-if="filteredOperations.length === 0">
                                            <td colspan="4" class="py-12">
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
</style>