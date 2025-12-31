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
                            class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
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
                                حسب رقم الشحنة:
                            </li>
                            <li>
                                <a
                                    @click="sortShipments('shipmentNumber', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'shipmentNumber' &&
                                            sortOrder === 'asc',
                                    }"
                                >
                                    الأصغر أولاً
                                </a>
                            </li>
                            <li>
                                <a
                                    @click="sortShipments('shipmentNumber', 'desc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'shipmentNumber' &&
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
                                    @click="sortShipments('createdAt', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'createdAt' &&
                                            sortOrder === 'asc',
                                    }"
                                >
                                    الأقدم أولاً
                                </a>
                            </li>
                            <li>
                                <a
                                    @click="sortShipments('createdAt', 'desc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'createdAt' &&
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
                                    @click="sortShipments('status', 'asc')"
                                    :class="{
                                        'font-bold text-[#4DA1A9]':
                                            sortKey === 'status',
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
                            filteredShipments.length
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
                                    <th class="shipment-number-col">
                                        رقم الشحنة
                                    </th>
                                    <th class="request-date-col">
                                        تاريخ طلب
                                    </th>
                                    <th class="status-col">حالة الطلب</th>
                                    <th class="actions-col text-center">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="text-gray-800">
                                <tr v-if="isLoading">
                                    <td colspan="4" class="p-4">
                                        <TableSkeleton :rows="5" />
                                    </td>
                                </tr>
                                <tr v-else-if="error">
                                    <td colspan="4" class="py-12">
                                        <ErrorState :message="error" :retry="fetchShipments" />
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="(shipment, index) in filteredShipments"
                                        :key="shipment.id || index"
                                        class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                    >
                                        <td class="font-semibold text-gray-700">
                                            {{ shipment.shipmentNumber || `SH-${shipment.id}` }}
                                        </td>
                                        <td>
                                            {{ formatDate(shipment.createdAt || shipment.requestDate) }}
                                        </td>
                                        <td
                                            :class="{
                                                'text-red-600 font-semibold':
                                                    shipment.status === 'مرفوضة' || shipment.status === 'rejected',
                                                'text-green-600 font-semibold':
                                                    shipment.status === 'تم الإستلام' || shipment.status === 'delivered',
                                                'text-yellow-600 font-semibold':
                                                    shipment.status === 'قيد التجهيز' || shipment.status === 'processing',
                                            }"
                                        >
                                            {{ shipment.status }}
                                        </td>
                                        <td class="actions-col">
                                            <div class="flex gap-3 justify-center">
                                                <!-- زر معاينة تفاصيل الشحنة - يظهر دائماً -->
                                                <button 
                                                    @click="openRequestViewModal(shipment)"
                                                    class="tooltip" 
                                                    data-tip="معاينة تفاصيل الشحنة">
                                                    <Icon
                                                        icon="famicons:open-outline"
                                                        class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                    />
                                                </button>
                                                
                                                <!-- زر الإجراء الثاني يختلف حسب الحالة -->
                                                <template v-if="shipment.status === 'مرفوضة' || shipment.status === 'rejected'">
                                                    <button class="tooltip" data-tip="طلب مرفوض">
                                                        <Icon
                                                            icon="tabler:circle-x" 
                                                            class="w-5 h-5 text-red-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.status === 'تم الإستلام' || shipment.status === 'delivered'">
                                                    <!-- إذا كانت تم الاستلام، تظهر زر مراجعة التفاصيل -->
                                                    <button 
                                                        @click="openReviewModal(shipment)"
                                                        class="tooltip" 
                                                        data-tip="مراجعة تفاصيل الاستلام">
                                                        <Icon
                                                            icon="healthicons:yes-outline"
                                                            class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredShipments.length === 0">
                                        <td colspan="4" class="py-12">
                                            <EmptyState message="لا توجد طلبات لعرضها" />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Modal لعرض تفاصيل الشحنة -->
        <RequestViewModal
            :is-open="isRequestViewModalOpen"
            :request-data="selectedRequestDetails"
            @close="closeRequestViewModal"
        />

        <!-- تنبيه النجاح -->
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
import RequestViewModal from "@/components/fordepartment/RequestViewModal.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

// ----------------------------------------------------
// 1. إعدادات axios
// ----------------------------------------------------
const api = axios.create({
  baseURL: 'http://localhost:3000/api', // ضع رابط الـ API الحقيقي هنا
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// إضافة interceptor للتعامل مع الأخطاء
api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    console.error('API Error:', error.response?.data || error.message);
    return Promise.reject(error);
  }
);

// تعريف جميع الـ endpoints
const endpoints = {
  shipments: {
    getAll: () => api.get('/shipments'),
    getById: (id) => api.get(`/shipments/${id}`),
    updateStatus: (id, status) => api.put(`/shipments/${id}/status`, { status }),
    confirmDelivery: (id, data) => api.post(`/shipments/${id}/confirm-delivery`, data),
    search: (params) => api.get('/shipments/search', { params })
  }
};

// ----------------------------------------------------
// 2. حالة المكون
// ----------------------------------------------------
const shipmentsData = ref([]);
const isLoading = ref(true);
const error = ref(null);

// ----------------------------------------------------
// 3. جلب البيانات من API
// ----------------------------------------------------
// ----------------------------------------------------
// 3. جلب البيانات من API
// ----------------------------------------------------
const fetchShipments = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await endpoints.shipments.getAll();
        shipmentsData.value = response.map(shipment => ({
            id: shipment.id,
            shipmentNumber: shipment.shipmentNumber || shipment.code || `SH-${shipment.id}`,
            requestDate: shipment.requestDate || shipment.createdAt,
            status: shipment.status,
            department: shipment.department,
            items: shipment.items || [],
            notes: shipment.notes,
            confirmedBy: shipment.confirmedBy,
            confirmedAt: shipment.confirmedAt,
            rejectionReason: shipment.rejectionReason,
            priority: shipment.priority,
            createdAt: shipment.createdAt,
            updatedAt: shipment.updatedAt
        }));
    } catch (err) {
        error.value = 'حدث خطأ في تحميل البيانات. يرجى المحاولة مرة أخرى.';
        console.error('Error fetching shipments:', err);
    } finally {
        isLoading.value = false;
    }
};

// دالة لتحديث حالة الشحنة
const updateShipmentStatus = async (shipmentId, newStatus) => {
    try {
        await endpoints.shipments.updateStatus(shipmentId, newStatus);
        await fetchShipments(); // إعادة تحميل البيانات
        showSuccessAlert(`✅ تم تحديث حالة الشحنة بنجاح`);
    } catch (err) {
        showSuccessAlert(`❌ فشل في تحديث الحالة: ${err.response?.data?.message || err.message}`);
    }
};

// دالة لتأكيد الاستلام
const confirmDelivery = async (shipmentId, confirmationData) => {
    try {
        await endpoints.shipments.confirmDelivery(shipmentId, confirmationData);
        await fetchShipments(); // إعادة تحميل البيانات
        showSuccessAlert(`✅ تم تأكيد استلام الشحنة بنجاح`);
    } catch (err) {
        showSuccessAlert(`❌ فشل في تأكيد الاستلام: ${err.response?.data?.message || err.message}`);
    }
};

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

// ----------------------------------------------------
// 5. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("createdAt");
const sortOrder = ref("desc");

const sortShipments = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredShipments = computed(() => {
    let list = shipmentsData.value;
    
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (shipment) =>
                (shipment.shipmentNumber || '').toLowerCase().includes(search) ||
                (shipment.status || '').toLowerCase().includes(search) ||
                (shipment.department || '').toLowerCase().includes(search)
        );
    }

    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "shipmentNumber") {
                comparison = (a.shipmentNumber || '').localeCompare(b.shipmentNumber || '');
            } else if (sortKey.value === "createdAt") {
                const dateA = new Date(a.createdAt || a.requestDate);
                const dateB = new Date(b.createdAt || b.requestDate);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "status") {
                comparison = (a.status || '').localeCompare(b.status || '', "ar");
            }

            return sortOrder.value === "asc" ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 6. حالة المكونات المنبثقة
// ----------------------------------------------------
const isRequestViewModalOpen = ref(false);
const selectedRequestDetails = ref({ id: null, date: '', status: '', items: [] });

const openRequestViewModal = async (shipment) => {
    try {
        // جلب التفاصيل الكاملة من API
        const response = await endpoints.shipments.getById(shipment.id);
        
        selectedRequestDetails.value = {
            id: response.id,
            shipmentNumber: response.shipmentNumber || response.code,
            createdAt: response.createdAt,
            status: response.status,
            items: response.items || [],
            notes: response.notes,
            storekeeperNotes: response.storekeeperNotes,
            supplierNotes: response.supplierNotes,
            rejectionReason: response.rejectionReason,
            rejectedAt: response.rejectedAt,
            confirmationDetails: response.confirmationDetails,
            confirmationNotes: response.confirmationDetails?.confirmationNotes || response.confirmationNotes
        };
        
        isRequestViewModalOpen.value = true;
    } catch (err) {
        showSuccessAlert(`❌ فشل في تحميل التفاصيل: ${err.response?.data?.message || err.message}`);
    }
};

const closeRequestViewModal = () => {
    isRequestViewModalOpen.value = false;
    selectedRequestDetails.value = { id: null, date: '', status: '', items: [] };
};

const openReviewModal = (shipment) => {
    // يمكن فتح نموذج خاص للمراجعة إذا لزم الأمر
    openRequestViewModal(shipment);
};

// ----------------------------------------------------
// 7. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    if (filteredShipments.value.length === 0) {
        showSuccessAlert("❌ لا توجد بيانات للطباعة");
        return;
    }

    const resultsCount = filteredShipments.value.length;
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
.center-icon { text-align: center; }
.status-delivered { color: green; }
.status-rejected { color: red; }
.status-processing { color: orange; }
</style>

<h1>قائمة الشحنات - تقرير طباعة</h1>
<p class="results-info">تاريخ التقرير: ${new Date().toLocaleDateString('ar-SA')}</p>
<p class="results-info">عدد النتائج: ${resultsCount}</p>

<table>
<thead>
    <tr>
    <th>رقم الشحنة</th>
    <th>تاريخ الطلب</th>
    <th>الحالة</th>
    <th>القسم</th>
    </tr>
</thead>
<tbody>
`;

    filteredShipments.value.forEach((shipment) => {
        const statusClass = shipment.status === 'تم الإستلام' ? 'status-delivered' : 
                          shipment.status === 'مرفوضة' ? 'status-rejected' : 'status-processing';
        
        tableHtml += `
<tr>
    <td>${shipment.shipmentNumber || `SH-${shipment.id}`}</td>
    <td>${formatDate(shipment.createdAt)}</td>
    <td class="${statusClass}">${shipment.status}</td>
    <td>${shipment.department || 'غير محدد'}</td>
</tr>
`;
    });

    tableHtml += `
</tbody>
</table>
`;

    printWindow.document.write("<html><head><title>طباعة قائمة الشحنات</title>");
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
// 8. نظام التنبيهات
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
// 9. دورة الحياة
// ----------------------------------------------------
onMounted(() => {
    fetchShipments();
});
</script>

<style scoped>
/* الأنماط كما هي */
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

.shipment-number-col {
    width: 120px;
    min-width: 120px;
}
.request-date-col {
    width: 140px;
    min-width: 140px;
}
.status-col {
    width: 150px;
    min-width: 150px;
}
.actions-col {
    width: 150px;
    min-width: 150px;
    text-align: center;
}
</style>