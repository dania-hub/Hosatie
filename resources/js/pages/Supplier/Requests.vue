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
                                                'قيد التجهيز' || shipment.requestStatus === 'جديد' || shipment.requestStatus === 'قيد الإستلام',
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
import RequestViewModal from "@/components/forSu/RequestViewModal.vue"; 
import ConfirmationModal from "@/components/forSu/ConfirmationModal.vue"; 

// ----------------------------------------------------
// 1. إعدادات axios ونقاط النهاية API
// ----------------------------------------------------
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:3000/api';

const api = axios.create({
    baseURL: API_BASE_URL,
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
    }
});

// تعريف نقاط النهاية API
const API_ENDPOINTS = {
    shipments: {
        getAll: () => api.get('/shipments'),
        getById: (id) => api.get(`/shipments/${id}`),
        update: (id, data) => api.put(`/shipments/${id}`, data),
        confirm: (id, data) => api.post(`/shipments/${id}/confirm`, data),
        reject: (id, data) => api.post(`/shipments/${id}/reject`, data),
        receive: (id, data) => api.post(`/shipments/${id}/receive`, data)
    },
    categories: {
        getAll: () => api.get('/categories')
    },
    drugs: {
        getAll: () => api.get('/drugs'),
        getByCategory: (categoryId) => api.get(`/drugs?categoryId=${categoryId}`)
    },
    departments: {
        getAll: () => api.get('/departments')
    }
};

// إضافة interceptor للتعامل مع الأخطاء
api.interceptors.response.use(
    (response) => response.data,
    (error) => {
        console.error('API Error:', error.response?.data || error.message);
        
        if (error.response?.status === 401) {
            // إذا كان خطأ في المصادقة
            showSuccessAlert('❌ انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.');
            // يمكن إضافة إعادة توجيه لصفحة تسجيل الدخول هنا
        } else if (error.response?.status === 403) {
            showSuccessAlert('❌ ليس لديك الصلاحية للقيام بهذا الإجراء.');
        } else if (error.response?.status === 404) {
            showSuccessAlert('❌ المورد المطلوب غير موجود.');
        } else if (error.code === 'ECONNABORTED') {
            showSuccessAlert('❌ انتهت مهلة الاتصال بالخادم.');
        } else if (!error.response) {
            showSuccessAlert('❌ فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
        }
        
        return Promise.reject(error);
    }
);

// إضافة interceptor لإضافة التوكن تلقائياً
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('token');
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
        
        shipmentsData.value = response.map(shipment => ({
            id: shipment.id,
            shipmentNumber: shipment.shipmentNumber || `S-${shipment.id}`,
            requestDate: shipment.requestDate || shipment.createdAt,
            requestStatus: shipment.status || shipment.requestStatus,
            received: shipment.received || (shipment.status === 'تم الإستلام'),
            details: {
                id: shipment.id,
                shipmentNumber: shipment.shipmentNumber,
                date: shipment.requestDate,
                status: shipment.status,
                items: shipment.items || [],
                notes: shipment.notes || '',
                createdAt: shipment.createdAt,
                updatedAt: shipment.updatedAt,
                rejectionReason: shipment.rejectionReason,
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
        // جرب جلب التفاصيل الكاملة من API
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        selectedRequestDetails.value = {
            ...response,
            items: response.items || [],
            confirmationDetails: response.confirmationDetails
        };
        isRequestViewModalOpen.value = true;
    } catch (err) {
        console.error('Error loading shipment details from API:', err);
        
        // Fallback: استخدم البيانات المحلية إذا فشل الـ API
        if (shipment.details) {
            selectedRequestDetails.value = {
                ...shipment.details,
                items: shipment.details.items || [],
                confirmationDetails: shipment.details.confirmationDetails
            };
            isRequestViewModalOpen.value = true;
            showSuccessAlert('⚠️ تم تحميل البيانات المحلية (قد تكون غير محدثة). تحقق من اتصال الإنترنت.');
        } else {
            // إذا لم تكن هناك بيانات محلية، أظهر خطأ
            showSuccessAlert('❌ فشل في تحميل تفاصيل الشحنة ولا توجد بيانات محلية.');
        }
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
        // جرب جلب التفاصيل الكاملة من API
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        selectedShipmentForConfirmation.value = {
            ...response,
            items: response.items || []
        };
        isConfirmationModalOpen.value = true;
    } catch (err) {
        console.error('Error loading shipment details from API:', err);
        
        // Fallback: استخدم البيانات المحلية إذا فشل الـ API
        if (shipment.details) {
            selectedShipmentForConfirmation.value = {
                ...shipment.details,
                items: shipment.details.items || []
            };
            isConfirmationModalOpen.value = true;
            showSuccessAlert('⚠️ تم تحميل البيانات المحلية (قد تكون غير محدثة). تحقق من اتصال الإنترنت.');
        } else {
            // إذا لم تكن هناك بيانات محلية، أظهر خطأ
            showSuccessAlert('❌ فشل في تحميل تفاصيل الشحنة ولا توجد بيانات محلية.');
        }
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
            const itemsToUpdate = confirmationData.itemsToSend.map(item => ({
                id: item.id,
                sentQuantity: item.sentQuantity,
                receivedQuantity: item.sentQuantity
            }));
            
            await API_ENDPOINTS.shipments.confirm(shipmentId, {
                items: itemsToUpdate,
                notes: confirmationData.notes || '',
                confirmedBy: 'أمين المخزن' // يجب أن يكون هذا من بيانات المستخدم
            });
            
            // تحديث البيانات محلياً
            const shipmentIndex = shipmentsData.value.findIndex(s => s.id === shipmentId);
            if (shipmentIndex !== -1) {
                shipmentsData.value[shipmentIndex].requestStatus = 'تم الإستلام';
                shipmentsData.value[shipmentIndex].details.status = 'تم الإستلام';
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
                                return { ...item, sentQuantity: sentItem.sentQuantity };
                            }
                            return item;
                        });
                }
            }
            
            const totalSent = itemsToUpdate.reduce((sum, item) => sum + (item.sentQuantity || 0), 0);
            showSuccessAlert(`✅ تم تأكيد استلام الشحنة رقم ${shipmentNumber} بنجاح! (${totalSent} وحدة)`);
        }
        
        closeConfirmationModal();
        
    } catch (err) {
        console.error('Error in handleConfirmation:', err);
        
        if (err.response?.status === 404) {
            showSuccessAlert(`❌ الشحنة غير موجودة أو تم حذفها`);
        } else if (err.response?.status === 400) {
            showSuccessAlert(`❌ بيانات غير صالحة: ${err.response.data?.message || ''}`);
        } else if (err.response?.status === 409) {
            showSuccessAlert(`❌ تعارض في البيانات: ${err.response.data?.message || ''}`);
        } else {
            showSuccessAlert(`❌ فشل في العملية: ${err.message || 'حدث خطأ غير معروف'}`);
        }
    } finally {
        isConfirming.value = false;
    }
};

const openReviewModal = async (shipment) => {
    try {
        // جلب التفاصيل الكاملة من API
        const response = await API_ENDPOINTS.shipments.getById(shipment.id);
        selectedRequestDetails.value = {
            ...response,
            items: response.items || [],
            confirmationDetails: response.confirmationDetails
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