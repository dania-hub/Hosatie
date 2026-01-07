<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from 'axios'; 
import DefaultLayout from "@/components/DefaultLayout.vue"; 

import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. إعدادات API
// ----------------------------------------------------
const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// إضافة interceptor لإضافة الـ token تلقائياً
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
  (response) => response, // إرجاع response كاملاً بدون تعديل
  (error) => {
    if (error.response?.status === 401) {
      const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
      console.error('Unauthenticated - Token exists:', !!token);
      if (token) {
        console.error('Token value (first 20 chars):', token.substring(0, 20) + '...');
      } else {
        console.error('No token found. Please login again.');
      }
    }
    return Promise.reject(error);
  }
);

const operations = ref([]);
const isLoading = ref(true);
const error = ref(null);

// دالة جلب البيانات من نقطة النهاية (باستخدام Axios)
// دالة جلب البيانات من نقطة النهاية (باستخدام Axios)
const fetchOperations = async () => {
    isLoading.value = true;
    error.value = null;
    try {
        const response = await api.get('/admin-hospital/operations');
        
        operations.value = response.data; // 👈 تحديث البيانات المجلوبة

    } catch (err) {
        // Axios يلتقط أخطاء الاتصال والخادم
        console.error("Failed to fetch operations:", err);
        
        if (err.response?.status === 401) {
            error.value = "انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.";
        } else if (err.response?.status === 403) {
            error.value = "ليس لديك الصلاحية للوصول إلى هذه البيانات.";
        } else if (!err.response) {
            error.value = "فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.";
        } else {
            error.value = err.response?.data?.message || err.message || "فشل في تحميل البيانات.";
        }
        
        showErrorAlert(error.value);
    } finally {
        isLoading.value = false;
    }
};

// تشغيل دالة جلب البيانات عند تحميل المكون
onMounted(() => {
    fetchOperations();
});

// دالة لتحديد الفئة العامة للعملية
const getOperationCategory = (operationType) => {
    if (!operationType) return 'أخرى';
    
    const type = String(operationType).toLowerCase();
    
    if (type.includes('إضافة') || type.includes('اضافة') || type.includes('add') || type.includes('create')) {
        return 'إضافة';
    } else if (type.includes('تعديل') || type.includes('update') || type.includes('edit') || type.includes('modify')) {
        return 'تعديل';
    } else if (type.includes('حذف') || type.includes('delete') || type.includes('remove')) {
        return 'حذف';
    } else {
        return 'أخرى';
    }
};

// قائمة بالتصنيفات العامة للعمليات
const operationCategories = ['الكل', 'إضافة', 'تعديل', 'حذف', 'أخرى'];

// ----------------------------------------------------
// 2. منطق البحث والفرز والتصفية الموحد
// ----------------------------------------------------
const searchTerm = ref("");
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);
const operationTypeFilter = ref("الكل");

// حالة الفرز الحالية
const sortKey = ref('operationDate');
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

    // 1. التصفية (البحث شامل جميع حقول الجدول)
    list = list.filter(op => {
        if (!search) {
            // إذا لم يكن هناك بحث، فقط تطبيق فلتر نوع العملية
            const typeMatch = operationTypeFilter.value === 'الكل' ||
                              getOperationCategory(op.operationType) === operationTypeFilter.value;
            return typeMatch;
        }

        // البحث الشامل في جميع الحقول
        const searchLower = search.toLowerCase();
        const fileNumberStr = op.fileNumber ? op.fileNumber.toString() : '';
        const nameStr = op.name ? op.name.toLowerCase() : '';
        const patientNameStr = op.patientName ? op.patientName.toLowerCase() : '';
        const operationTypeStr = op.operationType ? op.operationType.toLowerCase() : '';
        const roleStr = op.role ? op.role.toLowerCase() : '';
        const operationDateStr = op.operationDate ? op.operationDate.toString() : '';
        
        // البحث في جميع الحقول
        const searchMatch = 
            fileNumberStr.includes(searchLower) ||
            nameStr.includes(searchLower) ||
            patientNameStr.includes(searchLower) ||
            operationTypeStr.includes(searchLower) ||
            roleStr.includes(searchLower) ||
            operationDateStr.includes(searchLower);

        // تصفية حسب الفئة العامة للعملية
        const typeMatch = operationTypeFilter.value === 'الكل' ||
                          getOperationCategory(op.operationType) === operationTypeFilter.value;

        return searchMatch && typeMatch;
    });
 // فلترة حسب التاريخ
    if (dateFrom.value || dateTo.value) {
        list = list.filter((op) => {
            if (!op.operationDate) return false;
            
            // تحويل تاريخ العملية من تنسيق yyyy/mm/dd إلى Date
            const operationDate = parseDate(op.operationDate);
            operationDate.setHours(0, 0, 0, 0); // إزالة الوقت للمقارنة
            
            let matchesFrom = true;
            let matchesTo = true;
            
            if (dateFrom.value) {
                const fromDate = new Date(dateFrom.value);
                fromDate.setHours(0, 0, 0, 0);
                matchesFrom = operationDate >= fromDate;
            }
            
            if (dateTo.value) {
                const toDate = new Date(dateTo.value);
                toDate.setHours(23, 59, 59, 999); // نهاية اليوم
                matchesTo = operationDate <= toDate;
            }
            
            return matchesFrom && matchesTo;
        });
    }

    // 2. الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === 'name') {
                comparison = a.name.localeCompare(b.name, 'ar');
            } else if (sortKey.value === 'patientName') {
                comparison = (a.patientName || '').localeCompare((b.patientName || ''), 'ar');
            } else if (sortKey.value === 'fileNumber') {
                comparison = a.fileNumber - b.fileNumber;
            } else if (sortKey.value === 'operationType') {
                comparison = a.operationType.localeCompare(b.operationType, 'ar');
            } else if (sortKey.value === 'operationDate') {
                const dateA = parseDate(a.operationDate);
                const dateB = parseDate(b.operationDate);
                comparison = dateA.getTime() - dateB.getTime();
            }

            // تطبيق الترتيب التصاعدي/التنازلي
            return sortOrder.value === 'asc' ? comparison : -comparison;
        });
    }

    return list;
});
// دالة لمسح فلتر التاريخ
const clearDateFilter = () => {
    dateFrom.value = "";
    dateTo.value = "";
}; 
// ----------------------------------------------------
// 3. نظام التنبيهات المطور (Toast System)
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


// ----------------------------------------------------
// 4. منطق الطباعة (Print Logic)
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredOperations.value.length;

    const printWindow = window.open('', '_blank', 'height=600,width=800');
    
    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showErrorAlert(" فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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
                    <th>اسم الموظف</th>
                    <th>اسم المريض</th>
                    <th>نوع العملية</th>
                    <th>تاريخ العملية</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredOperations.value.forEach(op => {
        const desc = getOperationDescription(op);
        const operationDisplay = desc.detail 
            ? `<strong>${desc.title}</strong><br><span style="font-size: 12px; color: #666;">${desc.detail}</span>`
            : op.operationType;
        
        tableHtml += `
            <tr>
                <td>${op.fileNumber}</td>
                <td>${op.name}${op.role ? ' (' + op.role + ')' : ''}</td>
                <td>${op.patientName || '-'}</td>
                <td>${operationDisplay}</td>
                <td>${op.operationDate}</td>
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
        showSuccessAlert(" تم تجهيز التقرير بنجاح للطباعة.");
    };
};


const openViewModal = (op) => console.log('عرض العملية:', op);
const openEditModal = (op) => console.log('تعديل العملية:', op);

// دالة لتنسيق تاريخ الميلاد (إزالة الوقت والأصفار)
const formatBirthDate = (dateString) => {
    if (!dateString) return '';
    
    // إزالة الوقت إذا كان موجوداً
    let dateOnly = dateString.split(' ')[0];
    
    // تحويل من YYYY-MM-DD إلى YYYY/M/D (إزالة الأصفار الزائدة)
    const parts = dateOnly.split('-');
    if (parts.length === 3) {
        const year = parts[0];
        const month = parseInt(parts[1], 10).toString();
        const day = parseInt(parts[2], 10).toString();
        return `${year}/${month}/${day}`;
    }
    
    return dateOnly;
};

// دالة للحصول على وصف العملية (مثل dataEntry/operationLog)
const getOperationDescription = (op) => {
    // إذا كانت العملية متعلقة بمريض ولديها changes، استخدم نفس التنسيق
    if (op.changes) {
        const opType = op.operationType;
        
        // التحقق من بداية operationType (قبل أي تفاصيل إضافية)
        if (opType.startsWith('إضافة مريض')) {
            return {
                title: 'إضافة',
                detail: `تم اضافة ملف مريض ${op.patientName || op.name} رقم الملف ${op.fileNumber}`
            };
        } else if (opType.startsWith('حذف مريض')) {
            return {
                title: 'حذف',
                detail: `تم حذف ملف المريض ${op.patientName || op.name} رقم ملفه ${op.fileNumber}`
            };
        } else if (opType.startsWith('تعديل مريض')) {
            // تحليل التغييرات
            let details = [];
            const oldVals = op.changes?.old || {};
            const newVals = op.changes?.new || {};

            if (newVals.phone && oldVals.phone !== newVals.phone) {
                details.push(`تعديل الرقم إلى ${newVals.phone}`);
            }
            if (newVals.full_name && oldVals.full_name !== newVals.full_name) {
                details.push(`تعديل الاسم إلى ${newVals.full_name}`);
            }
            if (newVals.national_id && oldVals.national_id !== newVals.national_id) {
                details.push(`تعديل الرقم الوطني إلى ${newVals.national_id}`);
            }
            if (newVals.email && oldVals.email !== newVals.email) {
                details.push(`تعديل البريد الإلكتروني إلى ${newVals.email}`);
            }
            if (newVals.birth_date && oldVals.birth_date !== newVals.birth_date) {
                const formattedDate = formatBirthDate(newVals.birth_date);
                details.push(`تعديل تاريخ الميلاد إلى ${formattedDate}`);
            }
            
            // إذا لم يتم اكتشاف تغيير محدد، استخدم رسالة عامة
            if (details.length === 0) {
                return { title: 'تعديل', detail: 'تم تعديل بيانات الملف' };
            }

            return {
                title: 'تعديل',
                detail: details.join('، ')
            };
        }
    }
    
    // للعمليات الأخرى، أعد النص الأصلي
    return { title: op.operationType, detail: '' };
};

</script>

<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <div  class="flex flex-col sm:flex-row justify-between items-center pt-2 pb-4 mb-2 gap-4  sm:gap-0">
                
                <div class="flex items-center gap-3 w-full sm:max-w-2xl">
                        <search v-model="searchTerm" placeholder="ابحث في جميع الحقول: رقم الملف، اسم الموظف، اسم المريض، نوع العملية، الدور، التاريخ" />
                         <!-- زر إظهار/إخفاء فلتر التاريخ -->
                    <button
                        @click="showDateFilter = !showDateFilter"
                        class="h-11 w-11 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
                        :title="showDateFilter ? 'إخفاء فلتر التاريخ' : 'إظهار فلتر التاريخ'"
                    >
                        <Icon
                            icon="solar:calendar-bold"
                            class="w-5 h-5"
                        />
                    </button>

                    <!-- فلتر التاريخ -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div v-if="showDateFilter" class="flex items-center gap-2">
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
                        <div tabindex="0" role="button" class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11
                            rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden
                            text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                            <Icon icon="lucide:filter" class="w-5 h-5 ml-2" />
                            تصفية: {{ operationTypeFilter }}
                        </div>
                        <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8]
                            rounded-[35px] w-52 text-right">
                            <li class="menu-title text-gray-700 font-bold text-sm">حسب نوع العملية:</li>
                            <li v-for="category in operationCategories" :key="category">
                                <a @click="operationTypeFilter = category"
                                    :class="{'font-bold text-[#4DA1A9]': operationTypeFilter === category}">
                                    {{ category }}
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
                                <a @click="sortOperations('operationDate', 'desc')"
                                    :class="{'font-bold text-[#4DA1A9]': sortKey === 'operationDate' && sortOrder === 'desc'}">
                                    الأحدث أولاً (تنازلي)
                                </a>
                            </li>
                            <li>
                                <a @click="sortOperations('operationDate', 'asc')"
                                    :class="{'font-bold text-[#4DA1A9]': sortKey === 'operationDate' && sortOrder === 'asc'}">
                                    الأقدم أولاً (تصاعدي)
                                </a>
                            </li>
                            
                            <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب اسم الموظف:</li>
                            <li>
                                <a @click="sortOperations('name', 'asc')"
                                    :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'asc'}">
                                    الاسم (أ - ي)
                                </a>
                            </li>
                            <li>
                                <a @click="sortOperations('name', 'desc')"
                                    :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'desc'}">
                                    الاسم (ي - أ)
                                </a>
                            </li>

                            <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب اسم المريض:</li>
                            <li>
                                <a @click="sortOperations('patientName', 'asc')"
                                    :class="{'font-bold text-[#4DA1A9]': sortKey === 'patientName' && sortOrder === 'asc'}">
                                    الاسم (أ - ي)
                                </a>
                            </li>
                            <li>
                                <a @click="sortOperations('patientName', 'desc')"
                                    :class="{'font-bold text-[#4DA1A9]': sortKey === 'patientName' && sortOrder === 'desc'}">
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
                                    <th class="name-col">اسم الموظف</th>
                                    <th class="patient-name-col">اسم المريض</th>
                                    <th class="operation-type-col">نوع العملية</th>
                                    <th class="operation-date-col">تاريخ العملية</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-if="isLoading">
                                    <td colspan="5" class="p-4">
                                        <TableSkeleton :rows="5" />
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
                                        <td class="file-number-col">{{ op.fileNumber }}</td>
                                        <td class="name-col">
                                            <div class="flex flex-col">
                                                <span>{{ op.name }}</span>
                                                <span class="text-sm text-[#4DA1A9] font-medium">{{ op.role || '-' }}</span>
                                            </div>
                                        </td>
                                        <td class="patient-name-col">{{ op.patientName || '-' }}</td>
                                        <td class="operation-type-col">
                                            <template v-if="getOperationDescription(op).detail">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-[#2E5077]">{{ getOperationDescription(op).title }}</span>
                                                    <span class="text-xs text-gray-500 font-medium">{{ getOperationDescription(op).detail }}</span>
                                                </div>
                                            </template>
                                            <template v-else>
                                                {{ op.operationType }}
                                            </template>
                                        </td>
                                        <td class="operation-date-col">{{ op.operationDate }}</td>
                                    </tr>
                                    <tr v-if="filteredOperations.length === 0">
                                        <td colspan="5" class="py-12">
                                            <EmptyState message="لا توجد عمليات مطابقة لمعايير البحث" />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div v-if="!isLoading && filteredOperations.length === 0 && searchTerm === '' && operationTypeFilter === 'الكل' && !error" class="hidden">
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

<style>
/* تنسيقات شريط التمرير */
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

/* تنسيقات عرض أعمدة الجدول */
.file-number-col {
    width: 20px;
    min-width: 20px;
}
.name-col {
    width: 100px;
    min-width: 100px;
}
.patient-name-col {
    width: 100px;
    min-width: 100px;
}
.operation-type-col {
    width: 130px;
    min-width: 130px;
}
.operation-date-col {
    width: 110px;
    min-width: 110px;
}
</style>