<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <div
                class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0"
            >
                <div class="flex items-center gap-3 w-full sm:max-w-xl">
                    <search v-model="searchTerm" />

                    <div class=" dropdown dropdown-start">
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
                                    @click="sortShipments('requestDate', 'asc')"
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
                                    @click="sortShipments('requestDate', 'desc')"
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
                                حسب حالة الطلب:
                            </li>
                            <li>
                                <a
                                    @click="sortShipments('requestStatus', 'asc')"
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

            <!-- جدول البيانات -->
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
                            class="table w-full text-right min-w-[550px] border-collapse"
                        >
                            <thead
                                class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                            >
                                <tr>
                                    <th class="shipment-number-col">
                                        رقم الشحنة
                                    </th>
                                    <th class="request-date-col">
                                        تاريخ الطلب
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
                                        v-for="shipment in filteredShipments"
                                        :key="shipment.id"
                                        class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                    >
                                        <td class="font-semibold text-gray-700">
                                            {{ shipment.shipmentNumber }}
                                        </td>
                                        <td>
                                            {{ formatDate(shipment.requestDate) }}
                                        </td>
                                        <td
                                            :class="{
                                                'text-red-600 font-semibold':
                                                    shipment.status === 'مرفوضة' ||
                                                    shipment.status === 'rejected',
                                                'text-green-600 font-semibold':
                                                    shipment.status === 'تم الإستلام' ||
                                                    shipment.status === 'fulfilled' ||
                                                    shipment.status === 'قيد التجهيز',
                                                'text-yellow-600 font-semibold':
                                                    shipment.status === 'جديد' ||
                                                    shipment.status === 'قيد الانتظار' ||
                                                    shipment.status === 'pending' ||
                                                    shipment.status === 'approved',
                                            }"
                                        >
                                            {{ shipment.status }}
                                        </td>
                                        <td class="actions-col">
                                            <div class="flex gap-3 justify-center">
                                                <button 
                                                    @click="openRequestViewModal(shipment)"
                                                    class="tooltip" 
                                                    data-tip="معاينة تفاصيل الشحنة">
                                                    <Icon
                                                        icon="famicons:open-outline"
                                                        class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                    />
                                                </button>
                                                
                                                <template v-if="shipment.status === 'مرفوضة' || shipment.status === 'rejected'">
                                                    <button class="tooltip" data-tip="طلب مرفوض">
                                                        <Icon
                                                            icon="tabler:circle-x" 
                                                            class="w-5 h-5 text-red-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.status === 'تم الإرسال' || shipment.status === 'fulfilled' || shipment.status === 'تم الإستلام'">
                                                    <button 
                                                        @click="openReviewModal(shipment)"
                                                        class="tooltip" 
                                                        data-tip="مراجعة تفاصيل الشحنة">
                                                        <Icon
                                                            icon="healthicons:yes-outline"
                                                            class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.status === 'جديد' || shipment.status === 'قيد الانتظار' || shipment.status === 'pending'">
                                                    <!-- طلب جديد، يمكن قبوله أو رفضه -->
                                                    <button
                                                        @click="openConfirmationModal(shipment)" 
                                                        class="tooltip"
                                                        data-tip="قبول أو رفض الطلب">
                                                        <Icon
                                                            icon="fluent:box-28-regular"
                                                            class="w-5 h-5 text-orange-500 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else>
                                                    <!-- حالات أخرى (مثل معتمدة مبدئياً) - يمكن مراجعتها فقط -->
                                                    <button
                                                        @click="openRequestViewModal(shipment)" 
                                                        class="tooltip"
                                                        data-tip="معاينة الطلب">
                                                        <Icon
                                                            icon="famicons:open-outline"
                                                            class="w-5 h-5 text-blue-500 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredShipments.length === 0">
                                        <td colspan="4" class="py-12">
                                            <EmptyState message="لا توجد شحنات لعرضها" />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <RequestViewModal
            :is-open="isRequestViewModalOpen"
            :request-data="selectedRequestDetails"
            @close="closeRequestViewModal"
        />

        <ConfirmationModal
            :is-open="isConfirmationModalOpen"
            :request-data="selectedShipmentForConfirmation"
            @close="closeConfirmationModal"
            @confirm="handleConfirmation"
            :is-loading="isConfirming"
        />

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

// المكونات
import DefaultLayout from "@/components/DefaultLayout.vue"; 
import search from "@/components/search.vue"; 
import btnprint from "@/components/btnprint.vue";
import RequestViewModal from "@/components/forhospitaladmin/RequestViewModal.vue"; 
import ConfirmationModal from "@/components/forhospitaladmin/ConfirmationModal.vue"; 
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue"; 

// ----------------------------------------------------
// 1. إعدادات axios ونقاط النهاية API
// ----------------------------------------------------
// استخدام baseURL النسبي للعمل مع Laravel
const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة interceptor لإضافة التوكن تلقائياً
api.interceptors.request.use(
    (config) => {
        // البحث عن التوكن بأي من الاسمين المحتملين
        const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        } else {
            console.warn('No token found in localStorage. Available keys:', Object.keys(localStorage));
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// إضافة interceptor للـ response للتعامل مع الأخطاء
api.interceptors.response.use(
    (response) => {
        // إرجاع response كاملاً بدون تعديل
        return response;
    },
    (error) => {
        console.error('API Error:', error.response?.data || error.message);
        if (error.response?.status === 401) {
            showSuccessAlert('❌ انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.');
        } else if (error.response?.status === 403) {
            showSuccessAlert('❌ ليس لديك الصلاحية للوصول إلى هذه البيانات.');
        } else if (!error.response) {
            showSuccessAlert('❌ فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
        }
        return Promise.reject(error);
    }
);

// تعريف نقاط النهاية API
const API_ENDPOINTS = {
    shipments: {
        getAll: () => api.get('/admin-hospital/shipments'),
        getById: (id) => api.get(`/admin-hospital/shipments/${id}`),
        confirm: (id, data) => api.put(`/admin-hospital/shipments/${id}/confirm`, data),
        reject: (id, data) => api.put(`/admin-hospital/shipments/${id}/reject`, data)
    }
};

// ----------------------------------------------------
// 2. حالة المكون
// ----------------------------------------------------
const shipmentsData = ref([]);

const error = ref(null);
const isConfirming = ref(false);

// ----------------------------------------------------
// 3. جلب البيانات من API
// ----------------------------------------------------
// ----------------------------------------------------
// 3. جلب البيانات من API
// ----------------------------------------------------
const isLoading = ref(true);

const fetchShipments = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await API_ENDPOINTS.shipments.getAll();
        
        // التعامل مع بنية الاستجابة المختلفة
        // الـ API يرجع مصفوفة مباشرة من response()->json($requests)
        let data = [];
        
        if (response && response.data) {
            // إذا كانت البيانات في response.data
            if (Array.isArray(response.data)) {
                data = response.data;
            } else if (response.data.data && Array.isArray(response.data.data)) {
                data = response.data.data;
            }
        } else if (Array.isArray(response)) {
            // إذا كانت الاستجابة نفسها مصفوفة مباشرة
            data = response;
        }
        
        if (Array.isArray(data)) {
            shipmentsData.value = data.map(shipment => ({
                id: shipment.id,
                shipmentNumber: shipment.shipmentNumber || `EXT-${shipment.id}`,
                requestDate: shipment.requestDate || shipment.createdAt,
                status: shipment.status,
                requestingDepartment: shipment.requestingDepartment || shipment.department || 'مسؤول المخزن',
                received: shipment.status === 'تم الإستلام',
                details: {
                    ...shipment,
                    items: shipment.items || []
                }
            }));
        } else {
            console.warn('No valid data array found in response:', response);
            shipmentsData.value = [];
        }
    } catch (err) {
        console.error('Error fetching shipments:', err);
        const errorMsg = err.response?.data?.message || err.response?.data?.error || err.message || 'خطأ غير معروف';
        error.value = `فشل في تحميل البيانات: ${errorMsg}`;
    } finally {
        isLoading.value = false;
    }
};

// ----------------------------------------------------
// 4. دوال مساعدة
// ----------------------------------------------------
const formatDate = (dateString) => {
    if (!dateString) return 'غير محدد';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('ar-SA', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch {
        return dateString;
    }
};

// ----------------------------------------------------
// 5. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("requestDate");
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
                (shipment.shipmentNumber?.toLowerCase() || '').includes(search) ||
                (shipment.status?.includes(search) || false)
        );
    }

    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "shipmentNumber") {
                comparison = (a.shipmentNumber || '').localeCompare(b.shipmentNumber || '');
            } else if (sortKey.value === "requestDate") {
                const dateA = new Date(a.requestDate || 0);
                const dateB = new Date(b.requestDate || 0);
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
const selectedRequestDetails = ref({ 
    id: null, 
    shipmentNumber: '', 
    department: '', 
    date: '', 
    status: '', 
    items: [] 
}); 

const isConfirmationModalOpen = ref(false);
const selectedShipmentForConfirmation = ref({ 
    id: null, 
    shipmentNumber: '', 
    department: '', 
    date: '', 
    status: '', 
    items: [] 
});

// ----------------------------------------------------
// 7. وظائف العرض والتحكم بالإجراءات
// ----------------------------------------------------
const openRequestViewModal = async (shipment) => {
    try {
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        let data = response.data || response;
        if (data.data) {
            data = data.data;
        }
        
        selectedRequestDetails.value = {
            ...data,
            items: data.items || [],
            shipmentNumber: data.shipmentNumber || `EXT-${data.id}`,
            confirmationDetails: data.confirmationDetails || null,
            storekeeperNotes: data.storekeeperNotes || null,
            supplierNotes: data.supplierNotes || null,
            notes: data.notes || '',
            rejectionReason: data.rejectionReason || null,
            rejectedAt: data.rejectedAt || null
        };
        isRequestViewModalOpen.value = true;
    } catch (err) {
        console.error('Error fetching shipment details:', err);
        showSuccessAlert('❌ فشل في تحميل تفاصيل الشحنة');
    }
};

const closeRequestViewModal = () => {
    isRequestViewModalOpen.value = false;
    selectedRequestDetails.value = { 
        id: null, 
        shipmentNumber: '', 
        department: '', 
        date: '', 
        status: '', 
        items: [] 
    };
};

const openConfirmationModal = async (shipment) => {
    try {
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        let data = response.data || response;
        if (data.data) {
            data = data.data;
        }
        
        selectedShipmentForConfirmation.value = {
            ...data,
            items: data.items || [],
            shipmentNumber: data.shipmentNumber || `EXT-${data.id}`
        };
        isConfirmationModalOpen.value = true;
    } catch (err) {
        console.error('Error fetching shipment details:', err);
        showSuccessAlert('❌ فشل في تحميل تفاصيل الشحنة');
    }
};

const closeConfirmationModal = () => {
    isConfirmationModalOpen.value = false;
    selectedShipmentForConfirmation.value = { 
        id: null, 
        shipmentNumber: '', 
        department: '', 
        date: '', 
        status: '', 
        items: [] 
    };
};

const handleConfirmation = async (confirmationData) => {
    isConfirming.value = true;
    const shipmentId = selectedShipmentForConfirmation.value.id;
    const shipmentNumber = selectedShipmentForConfirmation.value.shipmentNumber || `EXT-${shipmentId}`;
    
    try {
        if (confirmationData.rejectionReason) {
            // 🔴 معالجة رفض الطلب
            const response = await API_ENDPOINTS.shipments.reject(shipmentId, {
                rejectionReason: confirmationData.rejectionReason
            });
            
            await fetchShipments(); // إعادة جلب البيانات
            const message = response.data?.message || response.message || `✅ تم رفض الطلب بنجاح. لن يتم إرساله للمورد.`;
            showSuccessAlert(message);
            
        } else if (confirmationData.items || confirmationData.itemsToSend) {
            // 🟢 معالجة قبول الطلب (موافقة مبدئية)
            const items = confirmationData.items || confirmationData.itemsToSend || [];
            const response = await API_ENDPOINTS.shipments.confirm(shipmentId, {
                items: items.map(item => ({ id: item.id }))
            });
            
            await fetchShipments(); // إعادة جلب البيانات
            const message = response.data?.message || response.message || `✅ تم قبول الطلب بنجاح. سيتم إرساله للمورد للموافقة النهائية.`;
            showSuccessAlert(message);
        }
        
        closeConfirmationModal();
        
    } catch (err) {
        console.error('Error in handleConfirmation:', err);
        const errorMessage = err.response?.data?.message || err.response?.data?.error || err.message || 'حدث خطأ غير معروف';
        showSuccessAlert(`❌ فشل في العملية: ${errorMessage}`);
    } finally {
        isConfirming.value = false;
    }
};

const openReviewModal = async (shipment) => {
    await openRequestViewModal(shipment);
};

// ----------------------------------------------------
// 8. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredShipments.value.length;

    const printWindow = window.open("", "_blank", "height=600,width=800");

    if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
        showSuccessAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
        return;
    }

    let tableHtml = `
<style>
body { font-family: 'Arial', Tahoma, sans-serif; direction: rtl; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
th { background-color: #f2f2f2; font-weight: bold; }
h1 { text-align: center; color: #2E5077; margin-bottom: 10px; }
.results-info { text-align: right; margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #4DA1A9; }
.center-icon { text-align: center; }
.print-date { text-align: left; color: #666; font-size: 14px; margin-bottom: 10px; }
</style>

<h1>قائمة طلبات التوريد (تقرير طباعة)</h1>
<p class="print-date">تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')}</p>
<p class="results-info">عدد النتائج: ${resultsCount}</p>

<table>
<thead>
    <tr>
    <th>رقم الشحنة</th>
    <th>تاريخ الطلب</th>
    <th>حالة الطلب</th>
    <th class="center-icon">الإستلام</th> </tr>
</thead>
<tbody>
`;

    filteredShipments.value.forEach((shipment) => {
        const receivedIcon = shipment.received ? '✅' : '❌';
        tableHtml += `
<tr>
    <td>${shipment.shipmentNumber || 'غير محدد'}</td>
    <td>${formatDate(shipment.requestDate)}</td>
    <td>${shipment.status || 'غير محدد'}</td>
    <td class="center-icon">${receivedIcon}</td>
</tr>
`;
    });

    tableHtml += `
</tbody>
</table>
`;

    printWindow.document.write("<html><head><title>طباعة قائمة طلبات التوريد</title>");
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
// 9. نظام التنبيهات
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
// 10. دورة الحياة
// ----------------------------------------------------
onMounted(() => {
    fetchShipments();
});

// تحديث تلقائي كل 30 ثانية (اختياري)
// setInterval(() => {
//     if (!isLoading.value && !isConfirmationModalOpen.value && !isRequestViewModalOpen.value) {
//         fetchShipments();
//     }
// }, 30000);
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

/* تحسينات للجوال */
@media (max-width: 640px) {
    .status-col {
        width: 120px;
        min-width: 120px;
    }
}
</style>