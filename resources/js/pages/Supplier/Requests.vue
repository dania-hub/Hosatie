<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <div
                class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0"
            >
                <div class="flex items-center gap-3 w-full sm:max-w-xl">
                    <search v-model="searchTerm" />

                    <!-- زر إظهار/إخفاء فلتر التاريخ -->
                    <button
                        @click="showDateFilter = !showDateFilter"
                        class="h-11 w-19 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
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
                            class="table w-full text-right min-w-[750px] border-collapse"
                        >
                            <thead
                                class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                            >
                                <tr>
                                    
                                    <th class="shipment-number-col">
                                        رقم الشحنة
                                    </th>
                                    <th class="department-col">الجهة الطالبة</th>
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
                                    <td colspan="5" class="p-4">
                                        <TableSkeleton :rows="5" />
                                    </td>
                                </tr>
                                <tr v-else-if="error">
                                    <td colspan="5" class="py-12">
                                        <ErrorState :message="error" :retry="fetchAllData" />
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="(shipment, index) in filteredShipments"
                                        :key="shipment.id"
                                        class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                    >
                                       
                                        <td class="font-semibold text-gray-700">
                                            {{ shipment.shipmentNumber }}
                                        </td>
                                        <td class="font-medium text-[#2E5077]">
                                            {{ shipment.requestingDepartment }}
                                        </td>
                                        <td>
                                            {{ formatDate(shipment.requestDate) }}
                                        </td>
                                        <td
                                            :class="{
                                                'text-red-600 font-semibold':
                                                    shipment.requestStatus === 'مرفوض' || 
                                                    shipment.requestStatus === 'مرفوضة',
                                                'text-green-600 font-semibold':
                                                    shipment.requestStatus === 'تم الاستلام' ||
                                                    (shipment.isDelivered === true),
                                                'text-blue-600 font-semibold':
                                                    shipment.requestStatus === 'قيد الاستلام' ||
                                                    shipment.requestStatus === 'تم التنفيذ' ||
                                                    shipment.requestStatus === 'fulfilled',
                                                'text-yellow-600 font-semibold':
                                                    shipment.requestStatus === 'جديد' ||
                                                    shipment.requestStatus === 'تم الموافقة' ||
                                                    shipment.requestStatus === 'approved' ||
                                                    shipment.requestStatus === 'قيد الانتظار',
                                            }"
                                        >
                                            {{ shipment.requestStatus }}
                                        </td>
                                        <td class="actions-col">
                                            <div class="flex gap-3 justify-center">
                                                <button 
                                                    @click="openRequestViewModal(shipment)"
                                                    class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95" 
                                                    data-tip="معاينة تفاصيل الشحنة">
                                                    <Icon
                                                        icon="famicons:open-outline"
                                                        class="w-4 h-4 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                    />
                                                </button>
                                                
                                                <template v-if="shipment.requestStatus === 'مرفوض' || shipment.requestStatus === 'مرفوضة'">
                                                    <button class="tooltip p-2 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 transition-all duration-200 hover:scale-110 active:scale-95" data-tip="طلب مرفوض">
                                                        <Icon
                                                            icon="tabler:circle-x" 
                                                            class="w-4 h-4 text-red-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'تم الاستلام' || (shipment.isDelivered === true)">
                                                    <!-- تم تأكيد الاستلام من قبل مسؤول المخزن -->
                                                    <button 
                                                        @click="openReviewModal(shipment)"
                                                        class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        data-tip="تم تأكيد الاستلام">
                                                        <Icon
                                                            icon="healthicons:yes-outline"
                                                            class="w-4 h-4 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'قيد الاستلام' || shipment.requestStatus === 'تم التنفيذ' || shipment.requestStatus === 'fulfilled'">
                                                    <!-- تم الإرسال ولكن لم يتم تأكيد الاستلام بعد -->
                                                    <button class="tooltip p-2 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-all duration-200 hover:scale-110 active:scale-95" data-tip="في انتظار تأكيد الاستلام من مسؤول المخزن">
                                                        <Icon
                                                            icon="solar:clock-circle-bold"
                                                            class="w-4 h-4 text-blue-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'جديد' || shipment.requestStatus === 'تم الموافقة' || shipment.requestStatus === 'approved'">
                                                    <!-- طلب جديد معتمد من HospitalAdmin، يمكن للمورد القبول أو الرفض -->
                                                    <button
                                                        @click="openConfirmationModal(shipment)" 
                                                        class="tooltip p-2 rounded-lg bg-orange-50 hover:bg-orange-100 border border-orange-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        data-tip="قبول أو رفض الطلب">
                                                        <Icon
                                                            icon="fluent:box-28-regular"
                                                            class="w-4 h-4 text-orange-500 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else>
                                                    <button class="tooltip p-2 rounded-lg bg-yellow-50 hover:bg-yellow-100 border border-yellow-200 transition-all duration-200 hover:scale-110 active:scale-95" data-tip="في انتظار الموافقة">
                                                        <Icon
                                                            icon="solar:clock-circle-bold"
                                                            class="w-4 h-4 text-yellow-600"
                                                        />
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredShipments.length === 0">
                                        <td colspan="5" class="py-12">
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
            @send="handleConfirmation"
            @reject="handleConfirmation"
            :is-loading="isConfirming"
        />

        <Toast
            :show="isAlertVisible"
            :message="alertMessage"
            :type="alertType"
            @close="isAlertVisible = false"
        />
    </DefaultLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

// المكونات
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

import DefaultLayout from "@/components/DefaultLayout.vue"; 
import search from "@/components/search.vue"; 
import btnprint from "@/components/btnprint.vue";
import RequestViewModal from "@/components/forSu/RequestViewModal.vue"; 
import ConfirmationModal from "@/components/forSu/ConfirmationModal.vue"; 

// ----------------------------------------------------
// 1. إعدادات axios ونقاط النهاية API
// ----------------------------------------------------
const api = axios.create({
    baseURL: '/api',
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة interceptor لإضافة التوكن تلقائياً
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
    (response) => {
        // إرجاع response كاملاً بدون تعديل
        return response;
    },
    (error) => {
        console.error('API Error:', error.response?.data || error.message);
        
        if (error.response?.status === 401) {
            // إذا كان خطأ في المصادقة
            showSuccessAlert(' انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.');
            // يمكن إضافة إعادة توجيه لصفحة تسجيل الدخول هنا
        } else if (error.response?.status === 403) {
            showSuccessAlert(' ليس لديك الصلاحية للقيام بهذا الإجراء.');
        } else if (error.response?.status === 404) {
            showSuccessAlert(' المورد المطلوب غير موجود.');
        } else if (error.code === 'ECONNABORTED') {
            showSuccessAlert(' انتهت مهلة الاتصال بالخادم.');
        } else if (!error.response) {
            showSuccessAlert(' فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
        }
        
        return Promise.reject(error);
    }
);

// تعريف نقاط النهاية API
const API_ENDPOINTS = {
    shipments: {
        getAll: () => api.get('/supplier/shipments'),
        getById: (id) => api.get(`/supplier/shipments/${id}`),
        confirm: (id, data) => api.post(`/supplier/shipments/${id}/confirm`, data),
        reject: (id, data) => api.post(`/supplier/shipments/${id}/reject`, data)
    }
};

// ----------------------------------------------------
// 2. حالة المكون
// ----------------------------------------------------
const shipmentsData = ref([
]);
const categories = ref([
]);
const allDrugsData = ref([
]);
const isLoading = ref(true);
const error = ref(null);
const isSubmittingSupply = ref(false);
const isConfirming = ref(false);

// ----------------------------------------------------
// 3. جلب البيانات من API
// ----------------------------------------------------
const fetchAllData = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
        await fetchShipments();
    } catch (err) {
        error.value = 'حدث خطأ في تحميل البيانات. يرجى المحاولة مرة أخرى.';
        console.error('Error fetching data:', err);
    } finally {
        isLoading.value = false;
    }
};

const fetchShipments = async () => {
    try {
        const response = await API_ENDPOINTS.shipments.getAll();
        
        // التعامل مع response structure من BaseApiController
        // response.data = { success: true, data: [...], message: '...' }
        let data = response.data?.data || response.data || [];
        
        shipmentsData.value = data.map(shipment => ({
            id: shipment.id,
            isDelivered: shipment.isDelivered || false,
            shipmentNumber: `EXT-${shipment.id}`,
            requestDate: shipment.createdAt || shipment.requestDate,
            requestStatus: shipment.status || shipment.statusOriginal || 'قيد الانتظار',
            requestingDepartment: shipment.hospitalName || 'مستشفى غير محدد',
            received: shipment.status === 'fulfilled' || shipment.statusOriginal === 'fulfilled',
            details: {
                id: shipment.id,
                shipmentNumber: `EXT-${shipment.id}`,
                date: shipment.createdAt,
                status: shipment.status || shipment.statusOriginal,
                items: shipment.items || [],
                hospitalName: shipment.hospitalName,
                hospitalCode: shipment.hospitalCode,
                approvedBy: shipment.approvedBy
            }
        }));
    } catch (err) {
        console.error('Error fetching shipments:', err);
        throw err;
    }
};

const fetchCategories = async () => {
    try {
        const response = await API_ENDPOINTS.categories.getAll();
        categories.value = response.map(cat => ({
            id: cat.id,
            name: cat.name
        }));
    } catch (err) {
        console.error('Error fetching categories:', err);
    }
};

const fetchDrugs = async () => {
    try {
        const response = await API_ENDPOINTS.drugs.getAll();
        allDrugsData.value = response.map(drug => ({
            id: drug.id,
            name: drug.name,
            categoryId: drug.categoryId,
            dosage: drug.dosage || drug.strength,
            type: drug.type || 'Tablet',
            unit: drug.unit || 'وحدة',
            currentStock: drug.currentStock || 0,
            minStock: drug.minStock || 0
        }));
    } catch (err) {
        console.error('Error fetching drugs:', err);
    }
};

// ----------------------------------------------------
// 4. دوال مساعدة
// ----------------------------------------------------
const formatDate = (dateString) => {
    if (!dateString) return 'غير محدد';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString( {
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
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);
const sortKey = ref("requestDate");
const sortOrder = ref("desc");

const sortShipments = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredShipments = computed(() => {
    let list = shipmentsData.value;
    
    // فلترة حسب البحث النصي
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (shipment) =>
                (shipment.shipmentNumber?.toLowerCase() || '').includes(search) ||
                (shipment.requestStatus?.includes(search) || false) ||
                (shipment.requestingDepartment?.includes(search) || false)
        );
    }

    // فلترة حسب التاريخ
    if (dateFrom.value || dateTo.value) {
        list = list.filter((shipment) => {
            if (!shipment.requestDate) return false;
            
            const shipmentDate = new Date(shipment.requestDate);
            shipmentDate.setHours(0, 0, 0, 0); // إزالة الوقت للمقارنة
            
            let matchesFrom = true;
            let matchesTo = true;
            
            if (dateFrom.value) {
                const fromDate = new Date(dateFrom.value);
                fromDate.setHours(0, 0, 0, 0);
                matchesFrom = shipmentDate >= fromDate;
            }
            
            if (dateTo.value) {
                const toDate = new Date(dateTo.value);
                toDate.setHours(23, 59, 59, 999); // نهاية اليوم
                matchesTo = shipmentDate <= toDate;
            }
            
            return matchesFrom && matchesTo;
        });
    }

    // الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "shipmentNumber") {
                comparison = (a.shipmentNumber || '').localeCompare(b.shipmentNumber || '');
            } else if (sortKey.value === "requestDate") {
                const dateA = new Date(a.requestDate || 0);
                const dateB = new Date(b.requestDate || 0);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "requestStatus") {
                comparison = (a.requestStatus || '').localeCompare(b.requestStatus || '', "ar");
            }
            
            return sortOrder.value === "asc" ? comparison : -comparison;
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
// 6. حالة المكونات المنبثقة
// ----------------------------------------------------
const isRequestViewModalOpen = ref(false); 
const selectedRequestDetails = ref({ 
    id: null, 
    shipmentNumber: '', 
    department: '', 
    date: '', 
    status: '', 
    items: [],
    notes: '',
    storekeeperNotes: null,
    supplierNotes: null,
    rejectionReason: null,
    confirmation: null,
    confirmationDetails: null
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
        // التعامل مع response structure من BaseApiController
        let data = response.data?.data || response.data || response || {};
        
        selectedRequestDetails.value = {
            id: data.id,
            shipmentNumber: data.shipmentNumber || `EXT-${data.id}`,
            date: data.date || data.createdAt,
            status: data.status || data.statusOriginal || 'قيد الانتظار',
            department: data.department || data.hospital?.name || 'مستشفى غير محدد',
            items: (data.items || []).map(item => ({
                id: item.id,
                drugId: item.drugId,
                name: item.name || item.drugName,
                drugName: item.name || item.drugName,
                quantity: item.quantity || item.requestedQuantity || item.requested_qty || 0,
                requestedQuantity: item.requestedQuantity || item.requested_qty || 0,
                approvedQuantity: item.approvedQuantity || item.approved_qty || 0,
                fulfilled_qty: item.fulfilled_qty || null,
                unit: item.unit || 'وحدة',
                dosage: item.dosage || item.strength,
                strength: item.strength || item.dosage,
                batchNumber: item.batchNumber || item.batch_number || null,
                expiryDate: item.expiryDate || item.expiry_date || null,
                units_per_box: item.units_per_box || item.unitsPerBox || 1
            })),
            notes: data.notes || '',
            storekeeperNotes: data.storekeeperNotes || null,
            supplierNotes: data.supplierNotes || null,
            rejectionReason: data.rejectionReason || null,
            rejectedAt: data.rejectedAt || null,
            confirmation: data.confirmation || null,
            confirmationDetails: data.confirmationDetails || null
        };
        isRequestViewModalOpen.value = true;
    } catch (err) {
        console.error('Error loading shipment details from API:', err);
        showSuccessAlert(' فشل في تحميل تفاصيل الشحنة');
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
        items: [],
        notes: '',
        storekeeperNotes: null,
        supplierNotes: null,
        rejectionReason: null,
        confirmation: null,
        confirmationDetails: null
    };
};

const openConfirmationModal = async (shipment) => {
    try {
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        // التعامل مع response structure من BaseApiController
        let data = response.data?.data || response.data || response || {};
        
        selectedShipmentForConfirmation.value = {
            id: data.id,
            shipmentNumber: data.shipmentNumber || `EXT-${data.id}`,
            date: data.date || data.createdAt,
            status: data.status || data.statusOriginal || 'قيد الانتظار',
            department: data.department || data.hospital?.name || 'مستشفى غير محدد',
            items: (data.items || []).map(item => ({
                id: item.id,
                drugId: item.drugId,
                name: item.name || item.drugName,
                drugName: item.name || item.drugName,
                quantity: item.quantity || item.requestedQuantity || item.requested_qty || 0,
                requestedQuantity: item.requestedQuantity || item.requested_qty || 0,
                approvedQuantity: item.approvedQuantity || item.approved_qty || 0,
                fulfilled_qty: item.fulfilled_qty || null,
                unit: item.unit || 'وحدة',
                dosage: item.dosage || item.strength,
                strength: item.strength || item.dosage,
                // للـ ConfirmationModal - استخدام الكمية المتوفرة الفعلية من API (من مخزون المورد)
                originalQuantity: item.requestedQuantity || item.requested_qty || 0,
                availableQuantity: item.availableQuantity ?? item.stock ?? item.currentStock ?? 0,
                units_per_box: item.units_per_box || item.unitsPerBox || 1
            }))
        };
        isConfirmationModalOpen.value = true;
    } catch (err) {
        console.error('Error loading shipment details from API:', err);
        showSuccessAlert(' فشل في تحميل تفاصيل الشحنة');
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
    console.log('handleConfirmation called with:', confirmationData);
    isConfirming.value = true;
    const shipmentId = selectedShipmentForConfirmation.value.id;
    const shipmentNumber = selectedShipmentForConfirmation.value.shipmentNumber || `EXT-${shipmentId}`;
    
    try {
        if (confirmationData.rejectionReason) {
            // 🔴 معالجة رفض الطلب
            console.log('Rejecting shipment:', shipmentId);
            const response = await API_ENDPOINTS.shipments.reject(shipmentId, {
                reason: confirmationData.rejectionReason
            });
            console.log('Reject response:', response);
            
            await fetchShipments(); // إعادة جلب البيانات
            closeConfirmationModal();
            
            // عرض رسالة نجاح الرفض
            showSuccessAlert(`تم رفض الشحنة بنجاح - ${shipmentNumber}`);
            
        } else if (confirmationData.items || confirmationData.itemsToSend) {
            // 🟢 معالجة قبول الطلب وإرسال الشحنة
            const items = confirmationData.items || confirmationData.itemsToSend || [];
            console.log("Confirming shipment with items:", items);
            const itemsToSend = items.map((item) => ({
                id: item.id,
                fulfilled_qty:
                    item.fulfilled_qty ||
                    item.sentQuantity ||
                    item.approved_qty ||
                    item.requested_qty,
                batch_number: item.batchNumber || item.batch_number || null,
                expiry_date: item.expiryDate || item.expiry_date || null,
            }));
            console.log("Items to send:", itemsToSend);

            const response = await API_ENDPOINTS.shipments.confirm(shipmentId, {
                items: itemsToSend,
                notes: confirmationData.notes || "",
            });
            console.log('Confirm response:', response);
            
            await fetchShipments(); // إعادة جلب البيانات
            closeConfirmationModal();
            
            // عرض رسالة نجاح الإرسال
            showSuccessAlert(`تم إرسال الشحنة بنجاح - ${shipmentNumber}`);
        } else {
            console.warn('No valid confirmation data:', confirmationData);
        }
        
    } catch (err) {
        console.error('Error in handleConfirmation:', err);
        console.error('Error response:', err.response);
        console.error('Error message:', err.message);
    } finally {
        console.log('Setting isConfirming to false');
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
        showSuccessAlert(" فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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

<h1>قائمة طلبات التوريد </h1>
<p class="print-date">تاريخ الطباعة: ${new Date().toLocaleDateString('en')}</p>
<p class="results-info">عدد النتائج: ${resultsCount}</p>

<table>
<thead>
    <tr>
    <th>الجهة الطالبة</th>
    <th>رقم الشحنة</th>
    <th>تاريخ الطلب</th>
    <th>حالة الطلب</th>
  </tr>
</thead>
<tbody>
`;

    filteredShipments.value.forEach((shipment) => {
    
        tableHtml += `
<tr>
    <td>${shipment.requestingDepartment || 'غير محدد'}</td>
    <td>${shipment.shipmentNumber || 'غير محدد'}</td>
    <td>${formatDate(shipment.requestDate)}</td>
    <td>${shipment.requestStatus || 'غير محدد'}</td>

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
        showSuccessAlert(" تم تجهيز التقرير بنجاح للطباعة.");
    }; 
};

// ----------------------------------------------------
// 9. نظام التنبيهات
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

// للتوافق مع الطلبات القديمة
const showSuccessAlert = (message) => showAlert(message, "success");
const showErrorAlert = (message) => showAlert(message, "error");

// ----------------------------------------------------
// 10. دورة الحياة
// ----------------------------------------------------
onMounted(() => {
    fetchAllData();
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

/* نمط العمود: الجهة الطالبة */
.department-col {
    width: 200px; 
    min-width: 200px;
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
    .department-col {
        width: 150px;
        min-width: 150px;
    }
    .status-col {
        width: 120px;
        min-width: 120px;
    }
}
</style>