<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from 'axios'; 
import DefaultLayout from "@/components/DefaultLayout.vue"; 
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

const operations = ref([]);
const isLoading = ref(false);
const availableHospitals = ref([]); // ✅ أضف هذا لتخزين قائمة المستشفيات

// دالة جلب البيانات من نقطة النهاية (باستخدام Axios)
const fetchOperations = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/operations');
        operations.value = response.data;
        showSuccessAlert("✅ تم تحميل سجل العمليات بنجاح.");
    } catch (error) {
        console.error("Failed to fetch operations:", error);
        showSuccessAlert("❌ فشل في تحميل البيانات.");
    } finally {
        isLoading.value = false;
    }
};

// ✅ دالة جلب قائمة المستشفيات
const fetchHospitals = async () => {
    try {
        const response = await axios.get('/api/hospitals');
        availableHospitals.value = response.data;
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
    const types = new Set(operations.value.map(op => op.operationType));
    return ['الكل', ...Array.from(types)];
});

// ----------------------------------------------------
// 2. منطق البحث والفرز والتصفية الموحد
// ----------------------------------------------------
const searchTerm = ref("");
const operationTypeFilter = ref("الكل");
const hospitalFilter = ref("all"); // ✅ فلتر المستشفى

// حالة الفرز الحالية
const sortKey = ref('operationDate');
const sortOrder = ref('desc');

// دالة تحويل التاريخ من صيغة (yyyy/mm/dd) إلى كائن Date للمقارنة
const parseDate = (dateString) => {
    if (!dateString) return new Date(0);
    const parts = dateString.split('/');
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

    // 1. التصفية حسب البحث
    list = list.filter(op => {
        const searchMatch = !search ||
                            op.fileNumber.toString().includes(search) ||
                            op.name.toLowerCase().includes(search) ||
                            op.patientName?.toLowerCase().includes(search) ||
                            op.hospital?.toLowerCase().includes(search) ||
                            op.operationType.includes(search);
        
        return searchMatch;
    });

    // 2. التصفية حسب نوع العملية
    if (operationTypeFilter.value !== 'الكل') {
        list = list.filter(op => op.operationType === operationTypeFilter.value);
    }

    // ✅ 3. التصفية حسب المستشفى
    if (hospitalFilter.value !== 'all') {
        list = list.filter(op => {
            return op.hospitalId == hospitalFilter.value || op.hospital === hospitalFilter.value;
        });
    }

    // 4. الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === 'name') {
                comparison = a.name.localeCompare(b.name, 'ar');
            } else if (sortKey.value === 'patientName') {
                comparison = (a.patientName || '').localeCompare(b.patientName || '', 'ar');
            } else if (sortKey.value === 'hospital') {
                comparison = (a.hospital || '').localeCompare(b.hospital || '', 'ar');
            } else if (sortKey.value === 'fileNumber') {
                comparison = a.fileNumber - b.fileNumber;
            } else if (sortKey.value === 'operationType') {
                comparison = a.operationType.localeCompare(b.operationType, 'ar');
            } else if (sortKey.value === 'operationDate') {
                const dateA = parseDate(a.operationDate);
                const dateB = parseDate(b.operationDate);
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
                <td>${op.fileNumber}</td>
                <td>${op.name}</td>
                <td>${op.patientName || 'غير محدد'}</td>
                <td>${op.hospital || 'غير محدد'}</td>
                <td>${op.operationType}</td>
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
                                    <a @click="sortOperations('operationDate', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'operationDate' && sortOrder === 'desc'}">
                                        الأحدث أولاً
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('operationDate', 'asc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'operationDate' && sortOrder === 'asc'}">
                                        الأقدم أولاً
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
                                        اسم المريض (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('patientName', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'patientName' && sortOrder === 'desc'}">
                                        اسم المريض (ي - أ)
                                    </a>
                                </li>
                                
                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب المستشفى:</li>
                                <li>
                                    <a @click="sortOperations('hospital', 'asc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'hospital' && sortOrder === 'asc'}">
                                        المستشفى (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a @click="sortOperations('hospital', 'desc')"
                                        :class="{'font-bold text-[#4DA1A9]': sortKey === 'hospital' && sortOrder === 'desc'}">
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

                            <tbody>
                                <tr v-if="isLoading" class="border border-gray-300">
                                    <td colspan="6" class="text-center py-10 text-[#4DA1A9] text-xl font-semibold">
                                        جاري تحميل البيانات...
                                    </td>
                                </tr>

                                <tr
                                    v-else
                                    v-for="(op, index) in filteredOperations"
                                    :key="index"
                                    class="hover:bg-gray-100 border border-gray-300"
                                >
                                    <td class="file-number-col">{{ op.fileNumber }}</td>
                                    <td class="name-col">{{ op.name }}</td>
                                    <td class="patient-name-col">{{ op.patientName || 'غير محدد' }}</td>
                                    <td class="hospital-col">{{ op.hospital || 'غير محدد' }}</td>
                                    <td class="operation-type-col">{{ op.operationType }}</td>
                                    <td class="operation-date-col">{{ op.operationDate }}</td>
                                </tr>
                                
                                <tr v-if="!isLoading && filteredOperations.length === 0">
                                    <td colspan="6" class="p-6 text-center text-gray-500 text-lg">
                                        ❌ لا توجد عمليات مطابقة لمعايير البحث أو التصفية الحالية.
                                    </td>
                                </tr>
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
    width: 120px;
    min-width: 120px;
}
.operation-date-col {
    width: 120px;
    min-width: 120px;
}
</style>