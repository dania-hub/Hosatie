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

            <!-- حالة التحميل -->
            <div v-if="isLoading" class="text-center py-10">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#4DA1A9] mx-auto"></div>
                <p class="text-gray-600 mt-4">جاري تحميل البيانات...</p>
            </div>



            <!-- جدول البيانات -->
            <div
                v-else
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
                                    <th class="department-col">
                                        الجهة الطالبة
                                    </th>
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
                                <tr
                                    v-if="filteredShipments.length === 0"
                                    class="text-center py-8"
                                >
                                    <td colspan="5" class="py-8 text-gray-500">
                                        لا توجد شحنات لعرضها
                                    </td>
                                </tr>
                                
                                <tr
                                    v-for="(shipment, index) in filteredShipments"
                                    v-else
                                    :key="shipment.id"
                                    class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                >
                                    <td class="font-semibold text-gray-700">
                                        {{ shipment.requestingDepartment }}
                                    </td>
                                    <td class="font-semibold text-gray-700">
                                        {{ shipment.shipmentNumber }}
                                    </td>
                                    <td>
                                        {{ formatDate(shipment.requestDate) }}
                                    </td>
                                    <td
                                        :class="{
                                            'text-red-600 font-semibold':
                                                shipment.requestStatus === 'مرفوضة',
                                            'text-green-600 font-semibold':
                                                shipment.requestStatus ===
                                                'تم الإستلام',
                                            'text-yellow-600 font-semibold':
                                                shipment.requestStatus ===
                                                'قيد الاستلام' || shipment.requestStatus === 'جديد' || shipment.requestStatus === 'قيد الإستلام',
                                        }"
                                    >
                                        {{ shipment.requestStatus }}
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
                                            
                                            <template v-if="shipment.requestStatus === 'مرفوضة'">
                                                <button class="tooltip" data-tip="طلب مرفوض">
                                                    <Icon
                                                        icon="tabler:circle-x" 
                                                        class="w-5 h-5 text-red-600"
                                                    />
                                                </button>
                                            </template>
                                            
                                            <template v-else-if="shipment.requestStatus === 'تم الإستلام'">
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
                                            
                                            <template v-else-if="shipment.requestStatus === 'قيد الاستلام'">
                                                <button 
                                                    class="tooltip" 
                                                    data-tip="الطلب قيد الاستلام - لا يمكن التعديل">
                                                    <Icon
                                                        icon="solar:clock-circle-bold"
                                                        class="w-5 h-5 text-yellow-600"
                                                    />
                                                </button>
                                            </template>
                                            
                                            <template v-else>
                                                <button
                                                    @click="openConfirmationModal(shipment)" 
                                                    class="tooltip"
                                                    data-tip="معاينة الطلب">
                                                    <Icon
                                                        icon="fluent:box-28-regular"
                                                        class="w-5 h-5 text-orange-500 cursor-pointer hover:scale-110 transition-transform"
                                                    />
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
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
import DefaultLayout from "@/components/DefaultLayout.vue"; 
import search from "@/components/search.vue"; 
import btnprint from "@/components/btnprint.vue";
import RequestViewModal from "@/components/forstorekeeper/RequestViewModal.vue"; 
import ConfirmationModal from "@/components/forstorekeeper/ConfirmationModal.vue"; 
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. إعدادات axios ونقاط النهاية API
// ----------------------------------------------------
const api = axios.create({
    baseURL: '/api',
    timeout: 30000, // زيادة الـ timeout إلى 30 ثانية للعمليات الطويلة
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// تعريف نقاط النهاية API
const API_ENDPOINTS = {
    shipments: {
        getAll: () => api.get('/storekeeper/shipments'),
        getById: (id) => api.get(`/storekeeper/shipments/${id}`),
        update: (id, data) => api.put(`/storekeeper/shipments/${id}`, data),
        confirm: (id, data) => api.post(`/storekeeper/shipments/${id}/confirm`, data),
        reject: (id, data) => api.post(`/storekeeper/shipments/${id}/reject`, data),
        receive: (id, data) => api.post(`/storekeeper/shipments/${id}/receive`, data)
    },
    categories: {
        getAll: () => api.get('/storekeeper/categories')
    },
    drugs: {
        getAll: () => api.get('/storekeeper/drugs'),
        getAllDrugs: () => api.get('/storekeeper/drugs/all'),
        getByCategory: (categoryId) => api.get(`/storekeeper/drugs?categoryId=${categoryId}`)
    },
    departments: {
        getAll: () => api.get('/departments')
    }
};

// إضافة interceptor للتعامل مع الأخطاء
api.interceptors.response.use(
    (response) => {
        // Laravel يعيد البيانات مباشرة في response.data
        return response.data;
    },
    (error) => {
        console.error('API Error:', error.response?.data || error.message);
        console.error('Error Status:', error.response?.status);
        console.error('Token exists:', !!localStorage.getItem('auth_token') || !!localStorage.getItem('token'));
        
        if (error.response?.status === 401) {
            // إذا كان خطأ في المصادقة
            const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
            if (!token) {
                showSuccessAlert('❌ لم يتم العثور على رمز المصادقة. يرجى تسجيل الدخول مرة أخرى.');
            } else {
                showSuccessAlert('❌ انتهت جلسة العمل أو رمز المصادقة غير صحيح. يرجى تسجيل الدخول مرة أخرى.');
            }
            // يمكن إضافة إعادة توجيه لصفحة تسجيل الدخول هنا
        } else if (error.response?.status === 403) {
            showSuccessAlert('❌ ليس لديك الصلاحية للقيام بهذا الإجراء.');
        } else if (error.response?.status === 404) {
            showSuccessAlert('❌ المورد المطلوب غير موجود.');
        } else if (error.code === 'ECONNABORTED') {
            showSuccessAlert('❌ انتهت مهلة الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
        } else if (!error.response) {
            showSuccessAlert('❌ فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
        } else {
            // معالجة أخطاء أخرى
            const errorMessage = error.response?.data?.message || error.message || 'حدث خطأ غير معروف';
            showSuccessAlert(`❌ ${errorMessage}`);
        }
        
        return Promise.reject(error);
    }
);

// إضافة interceptor لإضافة التوكن تلقائياً
api.interceptors.request.use(
    (config) => {
        // البحث عن التوكن في كلا المفاتيح (auth_token و token) للتوافق
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

// ----------------------------------------------------
// 2. حالة المكون
// ----------------------------------------------------
const shipmentsData = ref([]);
const categories = ref([]);
const allDrugsData = ref([]);
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
        // جلب البيانات بالتوازي
        await Promise.all([
            fetchShipments(),
            fetchCategories(),
            fetchDrugs()
        ]);
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
        
        // التأكد من أن response هو array
        const shipmentsArray = Array.isArray(response) ? response : [];
        
        shipmentsData.value = shipmentsArray.map(shipment => ({
            id: shipment.id,
            shipmentNumber: shipment.shipmentNumber || `INT-${shipment.id}`,
            requestDate: shipment.requestDate || shipment.createdAt,
            requestStatus: shipment.requestStatus || shipment.status || 'جديد',
            requestingDepartment: shipment.requestingDepartment || shipment.department?.name || 'قسم غير محدد',
            received: shipment.received || (shipment.requestStatus === 'تم الإستلام' || shipment.status === 'fulfilled'),
            details: {
                id: shipment.id,
                shipmentNumber: shipment.shipmentNumber,
                department: shipment.requestingDepartment || shipment.department?.name,
                date: shipment.requestDate || shipment.createdAt,
                status: shipment.requestStatus || shipment.status,
                items: shipment.items || [],
                notes: shipment.notes || '',
                createdAt: shipment.createdAt,
                updatedAt: shipment.updatedAt,
                rejectionReason: shipment.rejectionReason,
                confirmedBy: shipment.confirmedBy,
                confirmedAt: shipment.confirmedAt,
                ...(shipment.confirmationDetails && {
                    confirmationDetails: shipment.confirmationDetails
                })
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
        categories.value = [];
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
        allDrugsData.value = [];
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
                (shipment.requestStatus?.includes(search) || false) ||
                (shipment.requestingDepartment?.includes(search) || false)
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
            } else if (sortKey.value === "requestStatus") {
                comparison = (a.requestStatus || '').localeCompare(b.requestStatus || '', "ar");
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
        // جلب التفاصيل الكاملة من API
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        
        // التعامل مع response structure من BaseApiController
        // الـ interceptor يُرجع response.data، والـ API يُرجع {success: true, data: {...}}
        // إذا كان response.data موجوداً وله خاصية data، نستخدم response.data.data
        let data = response;
        if (response && response.data && typeof response.data === 'object' && 'data' in response.data) {
            data = response.data.data;
        } else if (response && response.data) {
            data = response.data;
        }
        
        console.log('📋 Response:', response);
        console.log('📋 Response data:', data);
        console.log('📋 Rejection reason:', data.rejectionReason);
        
        // تحويل البيانات لتتوافق مع ما يتوقعه مكون المعاينة
        selectedRequestDetails.value = {
            id: data.id,
            shipmentNumber: data.shipmentNumber || `INT-${data.id}`,
            department: data.department || shipment.requestingDepartment || 'قسم غير محدد',
            date: data.date || data.requestDate || data.createdAt,
            status: data.status || shipment.requestStatus,
            items: (data.items || []).map(item => ({
                id: item.id,
                drug_id: item.drug_id,
                name: item.drug_name || item.name || 'دواء غير محدد',
                quantity: item.requested_qty || item.quantity || 0,
                requested_qty: item.requested_qty || 0,
                approved_qty: item.approved_qty || 0,
                fulfilled_qty: item.fulfilled_qty || 0,
                sentQuantity: item.approved_qty || item.sentQuantity || 0,
                receivedQuantity: item.fulfilled_qty || item.receivedQuantity || 0,
                unit: item.unit || 'وحدة',
                dosage: item.dosage || item.strength || '',
                type: item.type || item.form || ''
            })),
            notes: data.notes || '',
            storekeeperNotes: data.storekeeperNotes || null,
            storekeeperNotesSource: data.storekeeperNotesSource || null,
            supplierNotes: data.supplierNotes || null,
            confirmationDetails: data.confirmationDetails || null,
            confirmation: data.confirmationDetails ? {
                confirmedBy: data.confirmationDetails.confirmedBy,
                confirmedAt: data.confirmationDetails.confirmedAt,
                notes: data.confirmationDetails.notes,
                confirmationNotes: data.confirmationNotes || null,
                items: data.items || []
            } : (data.confirmationNotes ? {
                confirmationNotes: data.confirmationNotes,
                confirmedAt: data.confirmationDetails?.confirmedAt || null
            } : null),
            confirmationNotes: data.confirmationNotes || null,
            confirmationNotesSource: data.confirmationNotesSource || null,
            rejectionReason: data.rejectionReason || shipment.rejectionReason || null,
            rejectedAt: data.rejectedAt || shipment.rejectedAt || null
        };
        
        console.log('✅ Selected request details:', selectedRequestDetails.value);
        isRequestViewModalOpen.value = true;
    } catch (err) {
        showSuccessAlert('❌ فشل في تحميل تفاصيل الشحنة');
        console.error('Error loading shipment details:', err);
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
        rejectionReason: null,
        rejectedAt: null
    };
};

const openConfirmationModal = async (shipment) => {
    // التحقق من حالة الطلب - منع التعديل إذا كان في حالة "قيد الاستلام"
    if (shipment.requestStatus === 'قيد الاستلام' || shipment.requestStatus === 'approved') {
        showSuccessAlert('⚠️ هذا الطلب قيد الاستلام ولا يمكن تعديله');
        return;
    }
    
    try {
        // جلب التفاصيل الكاملة من API
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        
        // التحقق مرة أخرى من حالة الطلب بعد جلب البيانات
        const currentStatus = response.status || shipment.requestStatus;
        if (currentStatus === 'قيد الاستلام' || currentStatus === 'approved') {
            showSuccessAlert('⚠️ هذا الطلب قيد الاستلام ولا يمكن تعديله');
            return;
        }
        
        // تحويل البيانات لتتوافق مع ما يتوقعه مكون التأكيد
        console.log('📥 Response from API:', response);
        selectedShipmentForConfirmation.value = {
            id: response.id,
            shipmentNumber: response.shipmentNumber || `INT-${response.id}`,
            department: response.department || shipment.requestingDepartment || 'قسم غير محدد',
            date: response.date || response.requestDate || response.createdAt,
            status: response.status || shipment.requestStatus,
            items: (response.items || []).map(item => {
                console.log('📦 Processing item:', item);
                return {
                    id: item.id, // هذا هو ID من internal_supply_request_item
                    drug_id: item.drug_id,
                    name: item.drug_name || item.name || 'دواء غير محدد',
                    quantity: item.requested_qty || item.quantity || 0,
                    requested_qty: item.requested_qty || 0,
                    requestedQuantity: item.requested_qty || 0,
                    originalQuantity: item.requested_qty || 0,
                    availableQuantity: item.availableQuantity !== undefined && item.availableQuantity !== null ? item.availableQuantity : (item.stock !== undefined && item.stock !== null ? item.stock : 0), // استخدام القيمة من API
                    stock: item.stock !== undefined && item.stock !== null ? item.stock : (item.availableQuantity !== undefined && item.availableQuantity !== null ? item.availableQuantity : 0),
                    suggestedQuantity: item.suggestedQuantity !== undefined && item.suggestedQuantity !== null ? item.suggestedQuantity : null, // الكمية المقترحة من API
                    sentQuantity: item.approved_qty || 0,
                    unit: item.unit || 'وحدة',
                    dosage: item.dosage || item.strength || '',
                    strength: item.strength || item.dosage || '',
                    type: item.type || item.form || '',
                    form: item.form || item.type || ''
                };
            }),
            notes: response.notes || ''
        };
        console.log('✅ Final selectedShipmentForConfirmation:', selectedShipmentForConfirmation.value);
        isConfirmationModalOpen.value = true;
    } catch (err) {
        showSuccessAlert('❌ فشل في تحميل تفاصيل الشحنة');
        console.error('Error loading shipment details:', err);
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
    const shipmentNumber = selectedShipmentForConfirmation.value.shipmentNumber;
    
    try {
        if (confirmationData.rejectionReason) {
            // 🔴 معالجة رفض الطلب
            await API_ENDPOINTS.shipments.reject(shipmentId, {
                rejectionReason: confirmationData.rejectionReason,
                rejectedBy: 'أمين المخزن' // يجب أن يكون هذا من بيانات المستخدم
            });
            
            // تحديث البيانات محلياً
            const shipmentIndex = shipmentsData.value.findIndex(s => s.id === shipmentId);
            if (shipmentIndex !== -1) {
                shipmentsData.value[shipmentIndex].requestStatus = 'مرفوضة';
                shipmentsData.value[shipmentIndex].details.status = 'مرفوضة';
                shipmentsData.value[shipmentIndex].details.rejectionReason = confirmationData.rejectionReason;
            }
            
            showSuccessAlert(`✅ تم رفض الشحنة رقم ${shipmentNumber} بنجاح`);
            
        } else if (confirmationData.itemsToSend) {
            // 🟢 معالجة إرسال الشحنة
            console.log('Sending confirmation data:', confirmationData);
            
            const itemsToUpdate = confirmationData.itemsToSend.map(item => {
                const sentQty = Number(item.sentQuantity) || 0;
                console.log(`Item ${item.id} (${item.name}): sentQuantity = ${item.sentQuantity} -> ${sentQty}`);
                return {
                    id: item.id,
                    sentQuantity: sentQty,
                    receivedQuantity: sentQty
                };
            });
            
            console.log('Items to update:', itemsToUpdate);
            console.log('Shipment ID:', shipmentId);
            
            // التحقق من أن جميع العناصر لها كمية أكبر من 0
            const hasZeroQuantities = itemsToUpdate.some(item => item.sentQuantity <= 0);
            if (hasZeroQuantities) {
                console.warn('⚠️ Warning: Some items have zero or negative quantities:', itemsToUpdate);
                const confirmSend = confirm('بعض العناصر لها كمية = 0. هل تريد المتابعة؟');
                if (!confirmSend) {
                    isConfirming.value = false;
                    return;
                }
            }
            
            const requestData = {
                items: itemsToUpdate,
                notes: confirmationData.notes || '',
                confirmedBy: 'أمين المخزن' // يجب أن يكون هذا من بيانات المستخدم
            };
            
            console.log('Request data:', requestData);
            
            await API_ENDPOINTS.shipments.confirm(shipmentId, requestData);
            
            // تحديث البيانات محلياً
            const shipmentIndex = shipmentsData.value.findIndex(s => s.id === shipmentId);
            if (shipmentIndex !== -1) {
                shipmentsData.value[shipmentIndex].requestStatus = 'قيد الاستلام';
                shipmentsData.value[shipmentIndex].details.status = 'قيد الاستلام';
                shipmentsData.value[shipmentIndex].details.confirmationDetails = {
                    confirmedAt: new Date().toISOString(),
                    confirmedBy: 'أمين المخزن',
                    notes: confirmationData.notes || ''
                };
                
                // تحديث الكميات في العناصر
                if (shipmentsData.value[shipmentIndex].details.items) {
                    shipmentsData.value[shipmentIndex].details.items = 
                        shipmentsData.value[shipmentIndex].details.items.map(item => {
                            const sentItem = confirmationData.itemsToSend.find(s => s.id === item.id);
                            if (sentItem) {
                                return { ...item, approved_qty: sentItem.sentQuantity, sentQuantity: sentItem.sentQuantity };
                            }
                            return item;
                        });
                }
            }
            
            const totalSent = itemsToUpdate.reduce((sum, item) => sum + (item.sentQuantity || 0), 0);
            showSuccessAlert(`✅ تم تأكيد تجهيز الشحنة رقم ${shipmentNumber} بنجاح! (${totalSent} وحدة)`);
        }
        
        closeConfirmationModal();
        
    } catch (err) {
        console.error('Error in handleConfirmation:', err);
        
        if (err.code === 'ECONNABORTED') {
            showSuccessAlert('❌ انتهت مهلة الاتصال. العملية تستغرق وقتاً طويلاً. يرجى التحقق من حالة الطلب.');
        } else if (err.response?.status === 404) {
            showSuccessAlert(`❌ الشحنة غير موجودة أو تم حذفها`);
        } else if (err.response?.status === 400) {
            showSuccessAlert(`❌ بيانات غير صالحة: ${err.response.data?.message || ''}`);
        } else if (err.response?.status === 409) {
            showSuccessAlert(`❌ تعارض في البيانات: ${err.response.data?.message || ''}`);
        } else if (err.response?.status === 500) {
            showSuccessAlert(`❌ خطأ في الخادم: ${err.response.data?.message || 'يرجى المحاولة مرة أخرى'}`);
        } else if (!err.response) {
            showSuccessAlert('❌ فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
        } else {
            const errorMessage = err.response?.data?.message || err.message || 'حدث خطأ غير معروف';
            showSuccessAlert(`❌ فشل في العملية: ${errorMessage}`);
        }
    } finally {
        isConfirming.value = false;
    }
};

const openReviewModal = async (shipment) => {
    try {
        // جلب التفاصيل الكاملة من API
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        
        // تحويل البيانات لتتوافق مع ما يتوقعه مكون المعاينة
        selectedRequestDetails.value = {
            id: response.id,
            shipmentNumber: response.shipmentNumber || `INT-${response.id}`,
            department: response.department || shipment.requestingDepartment || 'قسم غير محدد',
            date: response.date || response.requestDate || response.createdAt,
            status: response.status || shipment.requestStatus,
            items: (response.items || []).map(item => ({
                id: item.id,
                drug_id: item.drug_id,
                name: item.drug_name || item.name || 'دواء غير محدد',
                quantity: item.requested_qty || item.quantity || 0,
                requested_qty: item.requested_qty || 0,
                approved_qty: item.approved_qty || 0,
                fulfilled_qty: item.fulfilled_qty || 0,
                sentQuantity: item.approved_qty || item.sentQuantity || 0,
                receivedQuantity: item.fulfilled_qty || item.receivedQuantity || 0,
                unit: item.unit || 'وحدة',
                dosage: item.dosage || item.strength || '',
                type: item.type || item.form || ''
            })),
            notes: response.notes || '',
            confirmationDetails: response.confirmationDetails || null,
            confirmation: response.confirmationDetails ? {
                confirmedBy: response.confirmationDetails.confirmedBy,
                confirmedAt: response.confirmationDetails.confirmedAt,
                notes: response.confirmationDetails.notes,
                items: response.items || []
            } : null,
            rejectionReason: response.rejectionReason || null
        };
        isRequestViewModalOpen.value = true;
    } catch (err) {
        showSuccessAlert('❌ فشل في تحميل تفاصيل الشحنة');
        console.error('Error loading shipment details:', err);
    }
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
    <th>الجهة الطالبة</th>
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
    <td>${shipment.requestingDepartment || 'غير محدد'}</td>
    <td>${shipment.shipmentNumber || 'غير محدد'}</td>
    <td>${formatDate(shipment.requestDate)}</td>
    <td>${shipment.requestStatus || 'غير محدد'}</td>
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
// 9. نظام التنبيهات المطور (Toast System)
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