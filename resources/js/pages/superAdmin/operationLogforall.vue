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

// ----------------------------------------------------
// 1. تكوين Axios
// ----------------------------------------------------
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

api.interceptors.response.use(
    (response) => response,
    (error) => Promise.reject(error)
);

const operations = ref([]);
const isLoading = ref(false);
const error = ref(null);
const availableHospitals = ref([]); // ✅ أضف هذا لتخزين قائمة المستشفيات

// دالة جلب البيانات من نقطة النهاية (باستخدام Axios)
const fetchOperations = async () => {
    isLoading.value = true;
    error.value = null;
    try {
        const response = await api.get('/super-admin/operations');
        operations.value = response.data;
    } catch (err) {
        console.error("Failed to fetch operations:", err);
        error.value = err.response?.data?.message || "فشل في تحميل البيانات.";
    } finally {
        isLoading.value = false;
    }
};

// ✅ دالة جلب قائمة المستشفيات
const fetchHospitals = async () => {
    try {
        const response = await api.get('/super-admin/hospitals');
        // The API returns { success: true, data: [...] } or just [...]
        // HospitalSuperController::index returns sendSuccess($hospitals) which wraps in data
        const data = response.data.data || response.data;
        availableHospitals.value = Array.isArray(data) ? data : [];
    } catch (err) {
        console.warn("فشل في تحميل قائمة المستشفيات:", err.message);
        availableHospitals.value = [];
    }
};

// تشغيل دالة جلب البيانات عند تحميل المكون
onMounted(() => {
    fetchOperations();
    fetchHospitals(); // ✅ تحميل قائمة المستشفيات
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
const hospitalFilter = ref("all");
const selectedDate = ref("");

// حالة الفرز الحالية
const sortKey = ref('date');
const sortOrder = ref('desc');

// دالة تحويل التاريخ من صيغة (yyyy/mm/dd) إلى كائن Date للمقارنة
const parseDate = (dateString) => {
    if (!dateString) return new Date(0);
    const parts = dateString.split('/');
    return new Date(parts[0], parts[1] - 1, parts[2]);
};

// ----------------------------------------------------
// 3. دوال مساعدة لعرض التغييرات
// ----------------------------------------------------
const fieldTranslations = {
    'name': 'الاسم',
    'full_name': 'الاسم الكامل',
    'phone': 'رقم الهاتف',
    'email': 'البريد الإلكتروني',
    'national_id': 'رقم الهوية',
    'file_number': 'رقم الملف',
    'birth_date': 'تاريخ الميلاد',
    'gender': 'الجنس',
    'address': 'العنوان',
    'notes': 'ملاحظات',
    'status': 'الحالة',
    'type': 'النوع',
    'hospital_id': 'معرف المستشفى',
    'department_id': 'معرف القسم',
};

const translateField = (field) => {
    return fieldTranslations[field] || field;
};

const getChangedFields = (changes) => {
    if (!changes) return {};
    const oldVals = changes.old || {};
    const newVals = changes.new || {};
    const result = {};
    
    const allKeys = new Set([...Object.keys(oldVals), ...Object.keys(newVals)]);
    
    allKeys.forEach(key => {
        if (['updated_at', 'created_at', 'id', 'password', 'remember_token', 'email_verified_at'].includes(key)) return;
        
        const oldV = oldVals[key];
        const newV = newVals[key];
        
        // مقارنة القيم (تحويلها لنصوص لتجنب مشاكل الأنواع)
        if (JSON.stringify(oldV) !== JSON.stringify(newV)) {
            result[key] = { old: oldV ?? 'فارغ', new: newV ?? 'فارغ' };
        }
    });
    return result;
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

    // 1. التصفية حسب البحث
    list = list.filter(op => {
        const searchMatch = !search ||
                            op.file_number.toString().includes(search) ||
                            op.employee_name.toLowerCase().includes(search) ||
                            op.patient_name?.toLowerCase().includes(search) ||
                            op.hospital_name?.toLowerCase().includes(search) ||
                            op.operation_type.includes(search);
        
        return searchMatch;
    });

    // 2. التصفية حسب نوع العملية
    if (operationTypeFilter.value !== 'الكل') {
        list = list.filter(op => op.operation_type === operationTypeFilter.value);
    }

    // ✅ 3. التصفية حسب المستشفى
    if (hospitalFilter.value !== 'all') {
        list = list.filter(op => {
            return op.hospital_id == hospitalFilter.value || op.hospital_name === hospitalFilter.value;
        });
    }

    // ✅ التصفية حسب التاريخ
    if (selectedDate.value) {
        list = list.filter(op => {
            const [y, m, d] = selectedDate.value.split('-').map(Number);
            const inputTime = new Date(y, m - 1, d).getTime();
            const opTime = parseDate(op.date).getTime();
            return inputTime === opTime;
        });
    }

    // 4. الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === 'employee_name') {
                comparison = a.employee_name.localeCompare(b.employee_name, 'ar');
            } else if (sortKey.value === 'patient_name') {
                comparison = (a.patient_name || '').localeCompare(b.patient_name || '', 'ar');
            } else if (sortKey.value === 'hospital_name') {
                comparison = (a.hospital_name || '').localeCompare(b.hospital_name || '', 'ar');
            } else if (sortKey.value === 'file_number') {
                comparison = a.file_number - b.file_number;
            } else if (sortKey.value === 'operation_type') {
                comparison = a.operation_type.localeCompare(b.operation_type, 'ar');
            } else if (sortKey.value === 'date') {
                const dateA = parseDate(a.date);
                const dateB = parseDate(b.date);
                comparison = dateA.getTime() - dateB.getTime();
            }

            return sortOrder.value === 'asc' ? comparison : -comparison;
        });
    }

    return list;
});

// ✅ دالة للحصول على اسم المستشفى من ID
const getHospitalName = (hospitalId) => {
    if (hospitalId === 'all') return 'جميع المستشفيات';
    const hospital = availableHospitals.value.find(h => h.id == hospitalId || h.name === hospitalId);
    return hospital ? hospital.name : hospitalId;
};

// ----------------------------------------------------
// 3. منطق رسالة النجاح (Success Alert Logic)
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
            .filters-info {
                text-align: right;
                margin-bottom: 10px;
                font-size: 14px;
                color: #666;
            }
        </style>

        <h1>سجل العمليات (تقرير طباعة)</h1>
        
        <div class="filters-info">
            <strong>المستشفى:</strong> ${hospitalFilter.value === 'all' ? 'الكل' : getHospitalName(hospitalFilter.value)}<br>
            <strong>نوع العملية:</strong> ${operationTypeFilter.value}
        </div>
        
        <p class="results-info">
            عدد النتائج: ${resultsCount}
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>رقم الملف</th>
                    <th>اسم الموظف</th>
                    <th>اسم المريض</th>
                    <th>المستشفى</th>
                    <th>نوع العملية</th>
                    <th>تاريخ العملية</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredOperations.value.forEach(op => {
        tableHtml += `
            <tr>
                <td>${op.file_number}</td>
                <td>${op.employee_name}</td>
                <td>${op.patient_name || 'غير محدد'}</td>
                <td>${op.hospital_name || 'غير محدد'}</td>
                <td>${op.operation_type}</td>
                <td>${op.date}</td>
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

</script>

<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0">
                
                <div class="flex items-center gap-3 w-full sm:max-w-4xl flex-wrap">
                    <!-- شريط البحث -->
                    <div class="w-full sm:w-auto">
                        <search v-model="searchTerm" placeholder="ابحث برقم الملف الطبي، اسم الموظف، اسم المريض أو المستشفى" />
                    </div>

                     <div class="relative">
                            <input 
                                type="date" 
                                v-model="selectedDate"
                                class="h-11 px-4 rounded-[30px] border-2 border-gray-200 outline-none text-sm text-gray-600 focus:border-[#4DA1A9] transition-all bg-white"
                            />
                        </div>

                    <!-- مجموعة الفلاتر -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- ✅ فلتر المستشفى -->
                        <div class="dropdown dropdown-start">
                            <div
                                tabindex="0"
                                role="button"
                                class="inline-flex items-center justify-between h-12 px-4 py-2 border-2 border-[#ffffff8d] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] min-w-[150px]"
                            >
                                <span>
                                    {{ hospitalFilter === 'all' ? 'جميع المستشفيات' : getHospitalName(hospitalFilter) }}
                                </span>
                                <Icon icon="lucide:chevron-down" class="w-4 h-4 mr-2" />
                            </div>
                            <ul
                                tabindex="0"
                                class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-52 text-right max-h-60 overflow-y-auto"
                            >
                                <li>
                                    <a
                                        @click="hospitalFilter = 'all'"
                                        :class="{'font-bold text-[#4DA1A9]': hospitalFilter === 'all'}"
                                    >
                                        جميع المستشفيات
                                    </a>
                                </li>
                                <li v-for="hospital in availableHospitals" :key="hospital.id">
                                    <a
                                        @click="hospitalFilter = hospital.id"
                                        :class="{'font-bold text-[#4DA1A9]': hospitalFilter === hospital.id}"
                                    >
                                        {{ hospital.name }}
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- فلتر نوع العملية -->
                        <div class="dropdown dropdown-start">
                            <div tabindex="0" role="button" class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                                <Icon icon="lucide:filter" class="w-5 h-5 ml-2" />
                                {{ operationTypeFilter }}
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-52 text-right">
                                <li class="menu-title text-gray-700 font-bold text-sm">حسب نوع العملية:</li>
                                <li v-for="type in operationTypes" :key="type">
                                    <a @click="operationTypeFilter = type"
                                        :class="{'font-bold text-[#4DA1A9]': operationTypeFilter === type}">
                                        {{ type }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- فرز -->
                        <div class="dropdown dropdown-start">
                            <div tabindex="0" role="button" class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-12 w-20 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                                <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
                                فرز
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-52 text-right">
                                
                                <li class="menu-title text-gray-700 font-bold text-sm">حسب تاريخ العملية:</li>
                                <li>
                                    <a @click="sortOperations('date', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'date' && sortOrder === 'desc'}">
                                        الأحدث أولاً
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('date', 'asc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'date' && sortOrder === 'asc'}">
                                        الأقدم أولاً
                                    </a>
                                </li>
                                
                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب اسم الموظف:</li>
                                <li>
                                    <a @click="sortOperations('employee_name', 'asc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'employee_name' && sortOrder === 'asc'}">
                                        الاسم (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('employee_name', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'employee_name' && sortOrder === 'desc'}">
                                        الاسم (ي - أ)
                                    </a>
                                </li>
                                
                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب اسم المريض:</li>
                                <li>
                                    <a @click="sortOperations('patient_name', 'asc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'patient_name' && sortOrder === 'asc'}">
                                        اسم المريض (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('patient_name', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'patient_name' && sortOrder === 'desc'}">
                                        اسم المريض (ي - أ)
                                    </a>
                                </li>
                                
                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب المستشفى:</li>
                                <li>
                                    <a @click="sortOperations('hospital_name', 'asc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'hospital_name' && sortOrder === 'asc'}">
                                        المستشفى (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('hospital_name', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'hospital_name' && sortOrder === 'desc'}">
                                        المستشفى (ي - أ)
                                    </a>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- عرض عدد النتائج -->
                        <div class="flex items-center gap-1">
                            <p class="text-sm font-semibold text-gray-600">عدد النتائج:</p>
                            <span class="text-[#4DA1A9] text-lg font-bold bg-gray-100 px-3 py-1 rounded-full">
                                {{ filteredOperations.length }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full sm:w-auto">
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
                        <table dir="rtl" class="table w-full text-right min-w-[900px] border-collapse">
                            <thead class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300">
                                <tr>
                                    <th class="file-number-col">رقم الملف</th>
                                    <th class="name-col">اسم الموظف</th>
                                    <th class="patient-name-col">اسم المريض</th>
                                    <th class="hospital-col">المستشفى</th>
                                    <th class="operation-type-col">نوع العملية</th>
                                    <th class="operation-date-col">تاريخ العملية</th>
                                </tr>
                            </thead>

                                <tbody class="text-gray-800">
                                    <tr v-if="isLoading">
                                        <td colspan="6" class="p-4">
                                            <TableSkeleton :rows="10" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="error">
                                        <td colspan="6" class="py-12">
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
                                            <td class="name-col">
                                                <div class="font-medium text-gray-900">{{ op.employee_name }}</div>
                                                <div class="text-[#4DA1A9] text-sm">{{ op.employee_role }}</div>
                                            </td>
                                            <td class="patient-name-col">{{ op.patient_name || 'غير محدد' }}</td>
                                            <td class="hospital-col">{{ op.hospital_name || 'غير محدد' }}</td>
                                            <td class="operation-type-col">
                                                <div class="font-bold text-[#2E5077] mb-1 text-base">{{ op.action_label }}</div>
                                                <div class="text-sm text-gray-600">{{ op.description }}</div>
                                            </td>
                                            <td class="operation-date-col">{{ op.date }}</td>
                                        </tr>
                                        <tr v-if="filteredOperations.length === 0">
                                            <td colspan="6" class="py-12">
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
    width: 90px;
    min-width: 90px;
}
.name-col {
    width: 150px;
    min-width: 150px;
}
.patient-name-col {
    width: 170px;
    min-width: 170px;
}
.hospital-col {
    width: 160px;
    min-width: 160px;
}
.operation-type-col {
    width: 250px;
    min-width: 250px;
}
.operation-date-col {
    width: 120px;
    min-width: 120px;
}
</style>