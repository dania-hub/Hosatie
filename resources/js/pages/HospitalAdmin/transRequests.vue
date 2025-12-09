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
                                حسب رقم الطلب:
                            </li>
                            <li>
                                <a
                                    @click="sortRequests('requestNumber', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'requestNumber' &&
                                            sortOrder === 'asc',
                                    }"
                                >
                                    الأصغر أولاً
                                </a>
                            </li>
                            <li>
                                <a
                                    @click="sortRequests('requestNumber', 'desc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'requestNumber' &&
                                            sortOrder === 'desc',
                                    }"
                                >
                                    الأكبر أولاً
                                </a>
                            </li>

                            <li
                                class="menu-title text-gray-700 font-bold text-sm mt-2"
                            >
                                حسب تاريخ الطلب:
                            </li>
                            <li>
                                <a
                                    @click="sortRequests('requestDate', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'requestDate' &&
                                            sortOrder === 'asc',
                                    }"
                                >
                                    الأقدم أولاً
                                </a>
                            </li>
                            <li>
                                <a
                                    @click="sortRequests('requestDate', 'desc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'requestDate' &&
                                            sortOrder === 'desc',
                                    }"
                                >
                                    الأحدث أولاً
                                </a>
                            </li>

                            <li
                                class="menu-title text-gray-700 font-bold text-sm mt-2"
                            >
                                حسب المستشفى المرسل:
                            </li>
                            <li>
                                <a
                                    @click="sortRequests('fromHospital', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'fromHospital',
                                    }"
                                >
                                    حسب الأبجدية
                                </a>
                            </li>

                            <li
                                class="menu-title text-gray-700 font-bold text-sm mt-2"
                            >
                                حسب حالة الطلب:
                            </li>
                            <li>
                                <a
                                    @click="sortRequests('requestStatus', 'asc')"
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
                            filteredRequests.length
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
                                    <th class="request-number-col">
                                        رقم الطلب
                                    </th>
                                    <th class="patient-name-col">
                                        اسم المريض
                                    </th>
                                    <th class="hospital-col">
                                        المستشفى المرسل
                                    </th>
                                    <th class="content-col">
                                        سبب النقل
                                    </th>
                                    <th class="actions-col text-center">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="text-gray-800">
                                <tr
                                    v-for="(request, index) in filteredRequests"
                                    :key="index"
                                    class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                >
                                    <td class="font-semibold text-gray-700">
                                        {{ request.requestNumber }}
                                    </td>
                                    <td>
                                        {{ request.patientName }}
                                    </td>
                                    <td>
                                        <span :class="getHospitalClass(request.fromHospital)">
                                            {{ request.fromHospital }}
                                        </span>
                                    </td>
                                    <td class="max-w-xs truncate" :title="request.transferReason">
                                        {{ truncateContent(request.transferReason) }}
                                    </td>
                                    <td class="actions-col">
                                        <div class="flex gap-3 justify-center">
                                            <!-- زر معاينة تفاصيل الطلب -->
                                            <button 
                                                @click="openRequestModal(request)"
                                                class="tooltip" 
                                                data-tip="معاينة تفاصيل الطلب">
                                                <Icon
                                                    icon="famicons:open-outline"
                                                    class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                />
                                            </button>
                                            
                                            <!-- زر الرد على الطلب -->
                                            <button 
                                                @click="openResponseModal(request)"
                                                class="tooltip" 
                                                data-tip="الرد على طلب النقل"
                                                :disabled="request.requestStatus === 'تم الرد' || request.requestStatus === 'مرفوض'">
                                                <Icon
                                                    icon="tabler:message-reply" 
                                                    class="w-5 h-5 text-blue-600 cursor-pointer hover:scale-110 transition-transform"
                                                    :class="{'opacity-50 cursor-not-allowed': request.requestStatus === 'تم الرد' || request.requestStatus === 'مرفوض'}"
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
        
        <!-- مودال معاينة الطلب -->
        <TransferRequestDetailsModal
            :is-open="isRequestModalOpen"
            :request-data="selectedRequest"
            @close="closeRequestModal"
        />

        <!-- مودال الرد على الطلب -->
        <TransferResponseModal
            :is-open="isResponseModalOpen"
            :request-data="selectedRequest"
            @close="closeResponseModal"
            @submit="handleTransferResponse"
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
import TransferRequestDetailsModal from "@/components/forhospitaladmin/TransferRequestDetailsModal.vue";
import TransferResponseModal from "@/components/forhospitaladmin/TransferResponseModal.vue";

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
  transferRequests: {
    getAll: () => api.get('/transfer-requests'),
    getById: (id) => api.get(`/transfer-requests/${id}`),
    updateStatus: (id, data) => api.put(`/transfer-requests/${id}/status`, data),
    respond: (id, data) => api.post(`/transfer-requests/${id}/respond`, data)
  }
};

// ----------------------------------------------------
// 2. حالة المكون
// ----------------------------------------------------
const transferRequests = ref([
  {
    id: 1,
    requestNumber: 'TR-2023-001',
    patientName: 'أحمد محمد',
 
    patientNationalId: '1234567890',
    fromHospital: 'مستشفى الخضراء',
    toHospital: 'مستشفى طرابلس الجامعي',
    transferReason: 'حاجة المريض إلى أجهزة متخصصة في أمراض القلب غير متوفرة في المستشفى الحالي',
    requestDate: '2023-10-01',
    requestStatus: 'قيد المراجعة',
  },
 
]);

const isLoading = ref(true);
const error = ref(null);

// ----------------------------------------------------
// 3. البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("requestDate");
const sortOrder = ref("desc");

const sortRequests = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredRequests = computed(() => {
    let list = transferRequests.value;
    
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (request) =>
                request.requestNumber.toLowerCase().includes(search) ||
                request.patientName.toLowerCase().includes(search) ||
                request.fromHospital.toLowerCase().includes(search) ||
                request.transferReason.toLowerCase().includes(search) ||
                request.medicalCondition?.toLowerCase().includes(search)
        );
    }

    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "requestNumber") {
                comparison = a.requestNumber.localeCompare(b.requestNumber);
            } else if (sortKey.value === "requestDate") {
                const dateA = new Date(a.requestDate);
                const dateB = new Date(b.requestDate);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "fromHospital") {
                comparison = a.fromHospital.localeCompare(b.fromHospital, "ar");
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
        return date.toLocaleDateString('ar-SA');
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

const getHospitalClass = (hospital) => {
    switch (hospital) {
        case 'مستشفى الخضراء':
            return 'text-blue-600 font-semibold bg-blue-50 px-2 py-1 rounded';
        case 'مستشفى طرابلس الجامعي':
            return 'text-purple-600 font-semibold bg-purple-50 px-2 py-1 rounded';
        default:
            return 'text-gray-600';
    }
};

const getUrgencyClass = (urgency) => {
    switch (urgency) {
        case 'عاجل جداً':
            return 'bg-red-100 text-red-800 font-bold px-2 py-1 rounded';
        case 'عاجل':
            return 'bg-orange-100 text-orange-800 font-bold px-2 py-1 rounded';
        case 'متوسط':
            return 'bg-yellow-100 text-yellow-800 font-bold px-2 py-1 rounded';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// ----------------------------------------------------
// 5. المودالات
// ----------------------------------------------------
const isRequestModalOpen = ref(false);
const isResponseModalOpen = ref(false);
const selectedRequest = ref(null);

const openRequestModal = (request) => {
    selectedRequest.value = request;
    isRequestModalOpen.value = true;
};

const closeRequestModal = () => {
    isRequestModalOpen.value = false;
    selectedRequest.value = null;
};

const openResponseModal = (request) => {
    if (request.requestStatus === 'تم الرد' || request.requestStatus === 'مرفوض') return;
    selectedRequest.value = request;
    isResponseModalOpen.value = true;
};

const closeResponseModal = () => {
    isResponseModalOpen.value = false;
    selectedRequest.value = null;
};

const handleTransferResponse = async (responseData) => {
    try {
        // تحديث حالة طلب النقل في البيانات المحلية
        const requestIndex = transferRequests.value.findIndex(
            r => r.id === selectedRequest.value.id
        );
        
        if (requestIndex !== -1) {
            transferRequests.value[requestIndex].requestStatus = responseData.status;
            if (responseData.status === 'مرفوض') {
                transferRequests.value[requestIndex].rejectionReason = responseData.rejectionReason;
                transferRequests.value[requestIndex].rejectedAt = new Date().toISOString();
            } else if (responseData.status === 'تم الرد') {
                transferRequests.value[requestIndex].response = responseData.response;
                transferRequests.value[requestIndex].respondedAt = new Date().toISOString();
                transferRequests.value[requestIndex].respondedBy = 'مسؤول المستشفى';
                transferRequests.value[requestIndex].notes = responseData.notes;
            }
        }
        
        showSuccessAlert(`✅ تم ${responseData.status === 'مرفوض' ? 'رفض' : 'الرد على'} طلب النقل بنجاح`);
        
        // في حالة حقيقية، هنا نرسل البيانات للـ API
        // await endpoints.transferRequests.respond(selectedRequest.value.id, responseData);
        
        closeResponseModal();
    } catch (err) {
        showSuccessAlert(`❌ فشل في حفظ الرد: ${err.message}`);
    }
};

// ----------------------------------------------------
// 6. الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredRequests.value.length;
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
.status-approved { color: green; font-weight: bold; }
.status-pending { color: orange; font-weight: bold; }
.status-rejected { color: red; font-weight: bold; }
.hospital-green { color: #1e40af; font-weight: bold; }
.hospital-tripoli { color: #7c3aed; font-weight: bold; }
</style>

<h1>طلبات نقل المرضى - تقرير طباعة</h1>
<p class="results-info">عدد النتائج: ${resultsCount}</p>
<table>
<thead>
    <tr>
    <th>رقم الطلب</th>
    <th>اسم المريض</th>
    <th>المستشفى المرسل</th>
    <th>سبب النقل</th>
    <th>الحالة</th>
    </tr>
</thead>
<tbody>
`;

    filteredRequests.value.forEach((request) => {
        const statusClass = request.requestStatus === 'مرفوض' ? 'status-rejected' :
                          request.requestStatus === 'تم الرد' ? 'status-approved' : 
                          'status-pending';
        
        const hospitalClass = request.fromHospital === 'مستشفى الخضراء' ? 'hospital-green' : 
                            request.fromHospital === 'مستشفى طرابلس الجامعي' ? 'hospital-tripoli' : '';
        
        tableHtml += `
<tr>
    <td>${request.requestNumber}</td>
    <td>${request.patientName}</td>
    <td class="${hospitalClass}">${request.fromHospital}</td>
    <td>${request.transferReason}</td>
    <td class="${statusClass}">${request.requestStatus}</td>
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

    printWindow.document.write("<html><head><title>طباعة طلبات نقل المرضى</title>");
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

.request-number-col {
    width: 130px;
    min-width: 130px;
}
.patient-name-col {
    width: 180px;
    min-width: 180px;
}
.hospital-col {
    width: 180px;
    min-width: 180px;
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