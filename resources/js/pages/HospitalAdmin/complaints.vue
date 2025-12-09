<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <div
                class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0"
            >
                <div class="flex items-center gap-3 w-full sm:max-w-xl">
                    <search v-model="searchTerm" />

                    <div class="dropdown dropdown-start">
                        <div
                            tabindex="0"
                            role="button"
                            class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                        >
                            <Icon
                                icon="lucide:arrow-down-up"
                                class="w-5 h-5 ml-2"
                            />
                            فرز
                        </div>
                        <ul
                            tabindex="0"
                            class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] border-[#ffffff8d] rounded-[35px] w-52 text-right"
                        >
                            <li
                                class="menu-title text-gray-700 font-bold text-sm"
                            >
                                حسب رقم الملف:
                            </li>
                            <li>
                                <a
                                    @click="sortPatients('fileNumber', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'fileNumber' &&
                                            sortOrder === 'asc',
                                    }"
                                >
                                    الأصغر أولاً
                                </a>
                            </li>
                            <li>
                                <a
                                    @click="sortPatients('fileNumber', 'desc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'fileNumber' &&
                                            sortOrder === 'desc',
                                    }"
                                >
                                    الأكبر أولاً
                                </a>
                            </li>

                            <li
                                class="menu-title text-gray-700 font-bold text-sm mt-2"
                            >
                                حسب تاريخ الإنشاء:
                            </li>
                            <li>
                                <a
                                    @click="sortPatients('createdDate', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'createdDate' &&
                                            sortOrder === 'asc',
                                    }"
                                >
                                    الأقدم أولاً
                                </a>
                            </li>
                            <li>
                                <a
                                    @click="sortPatients('createdDate', 'desc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'createdDate' &&
                                            sortOrder === 'desc',
                                    }"
                                >
                                    الأحدث أولاً
                                </a>
                            </li>
                            <li
                                class="menu-title text-gray-700 font-bold text-sm mt-2"
                            >
                                حسب حالة الطلب:
                            </li>
                            <li>
                                <a
                                    @click="sortPatients('requestStatus', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'requestStatus',
                                    }"
                                >
                                    حسب الأبجدية
                                </a>
                            </li>
                        </ul>
                    </div>
                    <p
                        class="text-sm font-semibold text-gray-600 self-end sm:self-center"
                    >
                        عدد النتائج :
                        <span class="text-[#4DA1A9] text-lg font-bold">{{
                            filteredPatients.length
                        }}</span>
                    </p>
                </div>

                <div
                    class="flex items-center gap-5 w-full sm:w-auto justify-end"
                >
                    <btnprint @click="printTable" />
                </div>
            </div>

            <div
                class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col"
            >
                <div
                    class="overflow-y-auto flex-1"
                    style="
                        scrollbar-width: auto;
                        scrollbar-color: grey transparent;
                        direction: ltr;
                    "
                >
                    <div class="overflow-x-auto h-full">
                        <table
                            dir="rtl"
                            class="table w-full text-right min-w-[600px] border-collapse"
                        >
                            <thead
                                class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                            >
                                <tr>
                                    <th class="file-number-col">
                                        رقم الملف
                                    </th>
                                    <th class="patient-name-col">
                                        اسم المريض
                                    </th>
                                    <th class="request-type-col">
                                        نوع الطلب
                                    </th>
                                    <th class="content-col">
                                        المحتوى
                                    </th>
                                    <th class="actions-col text-center">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="text-gray-800">
                                <tr
                                    v-for="(patient, index) in filteredPatients"
                                    :key="index"
                                    class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                >
                                    <td class="font-semibold text-gray-700">
                                        {{ patient.fileNumber }}
                                    </td>
                                    <td>
                                        {{ patient.patientName }}
                                    </td>
                                    <td>
                                        <span :class="getRequestTypeClass(patient.requestType)">
                                            {{ patient.requestType }}
                                        </span>
                                    </td>
                                    <td class="max-w-xs truncate" :title="patient.content">
                                        {{ truncateContent(patient.content) }}
                                    </td>
                                    <td class="actions-col">
                                        <div class="flex gap-3 justify-center">
                                            <!-- زر معاينة تفاصيل الملف -->
                                            <button 
                                                @click="openPatientModal(patient)"
                                                class="tooltip" 
                                                data-tip="معاينة تفاصيل الملف">
                                                <Icon
                                                    icon="famicons:open-outline"
                                                    class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                />
                                            </button>
                                            
                                            <!-- زر الرد على الطلب -->
                                            <button 
                                                @click="openResponseModal(patient)"
                                                class="tooltip" 
                                                data-tip="الرد على الطلب"
                                                :disabled="patient.requestStatus === 'تم الرد'">
                                                <Icon
                                                    icon="tabler:message-reply" 
                                                    class="w-5 h-5 text-blue-600 cursor-pointer hover:scale-110 transition-transform"
                                                    :class="{'opacity-50 cursor-not-allowed': patient.requestStatus === 'تم الرد'}"
                                                />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- مودال معاينة الملف -->
        <PatientDetailsModal
            :is-open="isPatientModalOpen"
            :patient-data="selectedPatient"
            @close="closePatientModal"
        />

        <!-- مودال الرد على الطلب -->
        <RequestResponseModal
            :is-open="isResponseModalOpen"
            :request-data="selectedPatient"
            @close="closeResponseModal"
            @submit="handleRequestResponse"
        />

        <!-- التنبيهات -->
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
                class="fixed top-4 right-55 z-[1000] p-4 text-right bg-green-500 text-white rounded-lg shadow-xl max-w-xs transition-all duration-300"
                dir="rtl"
            >
                {{ successMessage }}
            </div>
        </Transition>
    </DefaultLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import PatientDetailsModal from "@/components/forhospitaladmin/PatientDetailsModal.vue";
import RequestResponseModal from "@/components/forhospitaladmin/RequestResponseModal.vue";

// ----------------------------------------------------
// 1. إعدادات API
// ----------------------------------------------------
const api = axios.create({
  baseURL: 'http://localhost:3000/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    console.error('API Error:', error.response?.data || error.message);
    return Promise.reject(error);
  }
);

const endpoints = {
  patients: {
    getAll: () => api.get('/patients'),
    getById: (id) => api.get(`/patients/${id}`),
    updateStatus: (id, data) => api.put(`/patients/${id}/status`, data),
    respond: (id, data) => api.post(`/patients/${id}/respond`, data)
  },
  requests: {
    create: (data) => api.post('/requests', data)
  }
};

// ----------------------------------------------------
// 2. حالة المكون
// ----------------------------------------------------
const patientsData = ref([
  {
    id: 1,
    fileNumber: 'P-001',
    patientName: 'أحمد محمد',
    requestType: 'طلب دواء',
    content: 'أحتاج إلى دواء ضغط الدم مع إرفاق وصفة طبية',
    createdDate: '2023-10-01',
    requestStatus: 'قيد المراجعة',
   
  },
  {
    id: 2,
    fileNumber: 'P-002',
    patientName: 'فاطمة علي',
    requestType: 'طلب فحوصات',
    content: 'طلب إجراء فحوصات مخبرية شاملة مع تحديد موعد',
    createdDate: '2023-09-15',
    requestStatus: 'مرفوض',
 
  },
  {
    id: 3,
    fileNumber: 'P-003',
    patientName: 'خالد عبدالله',
    requestType: 'طلب استشارة',
    content: 'أحتاج إلى استشارة مع أخصائي القلب',
    createdDate: '2023-10-10',
    requestStatus: 'تم الرد',
   
  }
]);

const isLoading = ref(true);
const error = ref(null);

// ----------------------------------------------------
// 3. البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("createdDate");
const sortOrder = ref("desc");

const sortPatients = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredPatients = computed(() => {
    let list = patientsData.value;
    
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (patient) =>
                patient.fileNumber.toLowerCase().includes(search) ||
                patient.patientName.toLowerCase().includes(search) ||
                patient.requestType.includes(search) ||
                patient.content.toLowerCase().includes(search)
        );
    }

    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "fileNumber") {
                comparison = a.fileNumber.localeCompare(b.fileNumber);
            } else if (sortKey.value === "createdDate") {
                const dateA = new Date(a.createdDate);
                const dateB = new Date(b.createdDate);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "requestStatus") {
                comparison = a.requestStatus.localeCompare(b.requestStatus, "ar");
            }

            return sortOrder.value === "asc" ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 4. دوال مساعدة
// ----------------------------------------------------
const formatDate = (dateString) => {
    if (!dateString) return 'غير محدد';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en');
    } catch {
        return dateString;
    }
};

const truncateContent = (content, maxLength = 50) => {
    if (!content) return '';
    return content.length > maxLength 
        ? content.substring(0, maxLength) + '...' 
        : content;
};

const getRequestTypeClass = (type) => {
    switch (type) {
        case 'طلب دواء':
            return 'text-blue-600 font-semibold';
        case 'طلب فحوصات':
            return 'text-purple-600 font-semibold';
        case 'طلب استشارة':
            return 'text-teal-600 font-semibold';
        default:
            return 'text-gray-600';
    }
};

// ----------------------------------------------------
// 5. المودالات
// ----------------------------------------------------
const isPatientModalOpen = ref(false);
const isResponseModalOpen = ref(false);
const selectedPatient = ref(null);

const openPatientModal = (patient) => {
    selectedPatient.value = patient;
    isPatientModalOpen.value = true;
};

const closePatientModal = () => {
    isPatientModalOpen.value = false;
    selectedPatient.value = null;
};

const openResponseModal = (patient) => {
    if (patient.requestStatus === 'تم الرد') return;
    selectedPatient.value = patient;
    isResponseModalOpen.value = true;
};

const closeResponseModal = () => {
    isResponseModalOpen.value = false;
    selectedPatient.value = null;
};

const handleRequestResponse = async (responseData) => {
    try {
        // تحديث حالة المريض في البيانات المحلية
        const patientIndex = patientsData.value.findIndex(
            p => p.id === selectedPatient.value.id
        );
        
        if (patientIndex !== -1) {
            patientsData.value[patientIndex].requestStatus = responseData.status;
            if (responseData.status === 'مرفوض') {
                patientsData.value[patientIndex].details = {
                    ...patientsData.value[patientIndex].details,
                    rejectionReason: responseData.reason,
                    rejectedAt: new Date().toISOString()
                };
            } else if (responseData.status === 'تم الرد') {
                patientsData.value[patientIndex].details = {
                    ...patientsData.value[patientIndex].details,
                    response: responseData.response,
                    respondedAt: new Date().toISOString(),
                    respondedBy: 'المسؤول' // هنا يمكن استخدام اسم المستخدم الحالي
                };
            }
        }
        
        showSuccessAlert(`✅ تم ${responseData.status === 'مرفوض' ? 'رفض' : 'الرد على'} الطلب بنجاح`);
        
        // في حالة حقيقية، هنا نرسل البيانات للـ API
        // await endpoints.patients.respond(selectedPatient.value.id, responseData);
        
        closeResponseModal();
    } catch (err) {
        showSuccessAlert(`❌ فشل في حفظ الرد: ${err.message}`);
    }
};

// ----------------------------------------------------
// 6. الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredPatients.value.length;
    const printWindow = window.open("", "_blank", "height=600,width=800");

    if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
        showSuccessAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
        return;
    }

    let tableHtml = `
<style>
body { font-family: 'Arial', sans-serif; direction: rtl; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
th { background-color: #f2f2f2; font-weight: bold; }
h1 { text-align: center; color: #2E5077; margin-bottom: 10px; }
.results-info { text-align: right; margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #4DA1A9; }
.status-accepted { color: green; font-weight: bold; }
.status-pending { color: orange; font-weight: bold; }
.status-rejected { color: red; font-weight: bold; }
</style>

<h1>قائمة ملفات المرضى (تقرير طباعة)</h1>

<p class="results-info">عدد النتائج: ${resultsCount}</p>

<table>
<thead>
    <tr>
    <th>رقم الملف</th>
    <th>اسم المريض</th>
    <th>نوع الطلب</th>
    <th>المحتوى</th>
    <th>الحالة</th>
    </tr>
</thead>
<tbody>
`;

    filteredPatients.value.forEach((patient) => {
        const statusClass = patient.requestStatus === 'مرفوض' ? 'status-rejected' :
                          patient.requestStatus === 'تم الرد' ? 'status-accepted' : 
                          'status-pending';
        
        tableHtml += `
<tr>
    <td>${patient.fileNumber}</td>
    <td>${patient.patientName}</td>
    <td>${patient.requestType}</td>
    <td>${patient.content}</td>
    <td class="${statusClass}">${patient.requestStatus}</td>
</tr>
`;
    });

    tableHtml += `
</tbody>
</table>
<p style="margin-top: 20px; color: #666; font-size: 12px; text-align: left;">
تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')}
</p>
`;

    printWindow.document.write("<html><head><title>طباعة قائمة ملفات المرضى</title>");
    printWindow.document.write("</head><body>");
    printWindow.document.write(tableHtml);
    printWindow.document.write("</body></html>");
    printWindow.document.close();

    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
        showSuccessAlert("✅ تم تجهيز التقرير بنجاح للطباعة.");
    };
};

// ----------------------------------------------------
// 7. التنبيهات
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
// 8. دورة الحياة
// ----------------------------------------------------
onMounted(() => {
    isLoading.value = false;
});
</script>

<style scoped>
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

.file-number-col {
    width: 120px;
    min-width: 120px;
}
.patient-name-col {
    width: 180px;
    min-width: 180px;
}
.request-type-col {
    width: 140px;
    min-width: 140px;
}
.content-col {
    width: 250px;
    min-width: 250px;
}
.actions-col {
    width: 150px;
    min-width: 150px;
    text-align: center;  
}
</style>