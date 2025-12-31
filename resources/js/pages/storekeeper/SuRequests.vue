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
                    <button
                        class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-29 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                        @click="openSupplyRequestModal"
                    >
                        طلب التوريد
                    </button>

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
                                        <ErrorState :message="error" :retry="fetchAllData" />
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="(shipment, index) in filteredShipments"
                                        :key="index"
                                        class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                    >
                                        <td class="font-semibold text-gray-700">
                                            {{ shipment.shipmentNumber }}
                                        </td>
                                        <td>
                                            {{ shipment.requestDate }}
                                        </td>
                                        <td
                                            :class="{
                                                'text-red-600 font-semibold':
                                                    shipment.requestStatus === 'مرفوضة' || 
                                                    shipment.requestStatus === 'مرفوض',
                                                'text-green-600 font-semibold':
                                                    shipment.requestStatus === 'تم الإستلام' ||
                                                    shipment.requestStatus === 'تم الاستلام',
                                                'text-yellow-600 font-semibold':
                                                    shipment.requestStatus === 'قيد الاستلام' ||
                                                    shipment.requestStatus === 'تمت الموافقة عليه جزئياً' ||
                                                    shipment.requestStatus === 'قيد الانتظار',
                                            }"
                                        >
                                            {{ shipment.requestStatus }}
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
                                                <template v-if="shipment.requestStatus === 'مرفوضة' || shipment.requestStatus === 'مرفوض'">
                                                    <button class="tooltip" data-tip="طلب مرفوض">
                                                        <Icon
                                                            icon="tabler:circle-x" 
                                                            class="w-5 h-5 text-red-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'قيد الاستلام'">
                                                    <!-- إذا كانت قيد الاستلام (أرسلها Supplier)، تظهر زر تأكيد الاستلام -->
                                                    <button
                                                        @click="openConfirmationModal(shipment)" 
                                                        class="tooltip"
                                                        data-tip="تأكيد استلام الشحنة">
                                                        <Icon
                                                            icon="tabler:truck-delivery"
                                                            class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'تم الإستلام' || shipment.requestStatus === 'تم الاستلام'">
                                                    <!-- إذا كانت تم الاستلام، تظهر علامة الصح -->
                                                    <button 
                                                        @click="openReviewModal(shipment)"
                                                        class="tooltip" 
                                                        data-tip="تم الاستلام">
                                                        <Icon
                                                            icon="healthicons:yes-outline"
                                                            class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else>
                                                    <!-- في انتظار الموافقة من HospitalAdmin أو Supplier -->
                                                    <button class="tooltip" data-tip="في انتظار الموافقة">
                                                        <Icon
                                                            icon="solar:clock-circle-bold"
                                                            class="w-5 h-5 text-yellow-600"
                                                        />
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredShipments.length === 0">
                                        <td colspan="4" class="py-12">
                                            <EmptyState message="لا توجد طلبات توريد لعرضها" />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    
        </main>
        
        <SupplyRequestModal
            :is-open="isSupplyRequestModalOpen"
            :categories="categories"
            :all-drugs-data="allDrugsData"
            @close="closeSupplyRequestModal"
            @confirm="handleSupplyConfirm"
            @show-alert="showSuccessAlert"
            :is-loading="isSubmittingSupply"
        />

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
import axios from "axios"; // استيراد axios

import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import SupplyRequestModal from "@/components/forpharmacist/SupplyRequestModal.vue";
import RequestViewModal from "@/components/forstorekeeper/RequestViewModal.vue"; 
import ConfirmationModal from "@/components/forstorekeeper/ConfirmationModal.vue"; 
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue"; 

// ----------------------------------------------------
// 0. نظام التنبيهات - يجب تعريفه قبل الاستخدام
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
// 1. إعدادات axios
// ----------------------------------------------------
const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
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
    // إرجاع response كاملاً بدون تعديل (نفس الطريقة المستخدمة في transRequests.vue)
    return response;
  },
  (error) => {
    console.error('API Error:', error.response?.data || error.message);
    if (error.response?.status === 401) {
      const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
      console.error('Unauthenticated - Token exists:', !!token);
      if (token) {
        console.error('Token value (first 20 chars):', token.substring(0, 20) + '...');
      } else {
        console.error('No token found. Please login again.');
      }
      showSuccessAlert('❌ انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.');
    } else if (error.response?.status === 403) {
      showSuccessAlert('❌ ليس لديك الصلاحية للوصول إلى هذه البيانات.');
    } else if (!error.response) {
      showSuccessAlert('❌ فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
    }
    return Promise.reject(error);
  }
);

// تعريف جميع الـ endpoints
const endpoints = {
  shipments: {
    getAll: () => api.get('/storekeeper/shipments'),
    getById: (id) => api.get(`/storekeeper/shipments/${id}`),
    create: (data) => api.post('/storekeeper/shipments', data),
    update: (id, data) => api.put(`/storekeeper/shipments/${id}`, data),
    confirm: (id, data) => api.post(`/storekeeper/shipments/${id}/confirm`, data)
  },
  categories: {
    getAll: () => api.get('/storekeeper/categories')
  },
  drugs: {
    getAll: () => api.get('/storekeeper/drugs/all'),
    search: (params) => api.get('/storekeeper/drugs/search', { params })
  },
  supplyRequests: {
    getAll: () => api.get('/storekeeper/supply-requests'),
    create: (data) => api.post('/storekeeper/supply-requests', data),
    confirmDelivery: (id, data) => api.post(`/storekeeper/supply-requests/${id}/confirm-delivery`, data)
  }
};

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
        console.log('Fetching supply requests from:', '/storekeeper/supply-requests');
        const response = await endpoints.supplyRequests.getAll();
        
        console.log('Raw API Response:', response);
        console.log('Response.data:', response.data);
        console.log('Response structure:', {
            hasData: !!response.data,
            isArray: Array.isArray(response.data),
            hasNestedData: !!(response.data?.data),
            hasSuccess: !!(response.data?.success),
            dataType: typeof response.data,
            dataKeys: response.data ? Object.keys(response.data) : []
        });
        
        // التحقق من بنية الاستجابة (نفس الطريقة المستخدمة في transRequests.vue)
        let data = [];
        if (response.data) {
            // sendSuccess يرجع: { success: true, message: "...", data: [...] }
            if (response.data.success && response.data.data && Array.isArray(response.data.data)) {
                // إذا كانت الاستجابة من sendSuccess
                data = response.data.data;
                console.log('✅ Using data from sendSuccess response, count:', data.length);
            } else if (response.data.data && Array.isArray(response.data.data)) {
                // إذا كانت البيانات في response.data.data
                data = response.data.data;
                console.log('✅ Using nested array from response.data.data, count:', data.length);
            } else if (Array.isArray(response.data)) {
                // إذا كانت البيانات مصفوفة مباشرة
                data = response.data;
                console.log('✅ Using direct array from response.data, count:', data.length);
            } else {
                console.warn('⚠️ Unknown response structure:', response.data);
                console.warn('⚠️ Response keys:', Object.keys(response.data));
                // محاولة استخراج البيانات بأي طريقة ممكنة
                if (response.data.data) {
                    data = Array.isArray(response.data.data) ? response.data.data : [];
                    console.log('⚠️ Extracted data (may be empty):', data.length);
                }
            }
        }
        
        console.log('Final data array:', data);
        console.log('Final data count:', data.length);
        console.log('First item (if exists):', data[0]);
        
        // طباعة بيانات الطلبات المرفوضة للتأكد من وجود rejectionReason
        const rejectedShipments = data.filter(s => s.status === 'rejected' || s.requestStatus === 'مرفوضة' || s.requestStatus === 'مرفوض');
        if (rejectedShipments.length > 0) {
            console.log('🔴 Rejected shipments:', rejectedShipments.map(s => ({
                id: s.id,
                status: s.status,
                requestStatus: s.requestStatus,
                rejectionReason: s.rejectionReason,
                rejectedAt: s.rejectedAt,
                fullData: s // إضافة البيانات الكاملة للتحقق
            })));
        }
        
        shipmentsData.value = data.map(shipment => {
            // طباعة معلومات rejectionReason للطلبات المرفوضة
            if (shipment.status === 'rejected' || shipment.requestStatus === 'مرفوضة' || shipment.requestStatus === 'مرفوض') {
                console.log('🔴 Mapping rejected shipment:', {
                    id: shipment.id,
                    status: shipment.status,
                    requestStatus: shipment.requestStatus,
                    rejectionReason: shipment.rejectionReason,
                    rejectedAt: shipment.rejectedAt,
                    hasRejectionReason: !!shipment.rejectionReason,
                    rejectionReasonType: typeof shipment.rejectionReason,
                    fullShipment: shipment
                });
            }
            
            return {
                id: shipment.id,
                shipmentNumber: shipment.shipmentNumber || `EXT-${shipment.id}`,
                requestDate: shipment.requestDate || shipment.requestDateFull || shipment.createdAt,
                requestStatus: shipment.requestStatus || shipment.status,
                received: shipment.requestStatus === 'تم الإستلام' || shipment.status === 'fulfilled',
                // إضافة rejectionReason على مستوى shipment أيضاً (بالإضافة إلى details)
                rejectionReason: shipment.rejectionReason || null,
                rejectedAt: shipment.rejectedAt || null,
                details: {
                    id: shipment.id,
                    date: shipment.requestDate || shipment.requestDateFull || shipment.createdAt,
                    status: shipment.requestStatus || shipment.status,
                    items: (shipment.items || []).map(item => ({
                        ...item,
                        // التأكد من وجود جميع الحقول المطلوبة
                        requested_qty: item.requested_qty || item.requested || item.quantity || 0,
                        requestedQty: item.requestedQty || item.requested || item.quantity || 0,
                        quantity: item.quantity || item.requested || item.requested_qty || 0,
                        unit: item.unit || 'وحدة'
                    })),
                    notes: shipment.notes || '',
                    storekeeperNotes: shipment.storekeeperNotes || null,
                    supplierNotes: shipment.supplierNotes || null,
                    rejectionReason: shipment.rejectionReason || null,
                    rejectedAt: shipment.rejectedAt || null,
                    department: shipment.requestingDepartment || shipment.department?.name || shipment.department,
                    ...(shipment.confirmationDetails && {
                        confirmationDetails: {
                            ...shipment.confirmationDetails,
                            confirmationNotes: shipment.confirmationDetails.confirmationNotes || null
                        }
                    })
                }
            };
        });
        
        if (shipmentsData.value.length === 0) {
            console.log('لا توجد بيانات متاحة');
        } else {
            console.log('✅ تم جلب', shipmentsData.value.length, 'طلب توريد بنجاح');
        }
    } catch (err) {
        console.error('❌ Error fetching supply requests:', err);
        console.error('Error details:', {
            message: err.message,
            response: err.response?.data,
            status: err.response?.status,
            url: err.config?.url
        });
        throw err;
    }
};

const fetchCategories = async () => {
    try {
        const response = await endpoints.categories.getAll();
        
        // التحقق من بنية الاستجابة
        let data = [];
        if (response.data) {
            if (response.data.success && response.data.data && Array.isArray(response.data.data)) {
                data = response.data.data;
            } else if (response.data.data && Array.isArray(response.data.data)) {
                data = response.data.data;
            } else if (Array.isArray(response.data)) {
                data = response.data;
            }
        }
        
        categories.value = data.map(cat => ({
            id: cat.id || cat.name,
            name: cat.name || cat.id
        }));
        console.log(`✅ تم تحميل ${categories.value.length} تصنيف بنجاح`);
    } catch (err) {
        console.error('Error fetching categories:', err);
        showSuccessAlert('❌ فشل في تحميل التصنيفات.');
        categories.value = [];
    }
};

const fetchDrugs = async () => {
    try {
        const response = await endpoints.drugs.getAll();
        
        // التحقق من بنية الاستجابة
        let data = [];
        if (response.data) {
            if (response.data.success && response.data.data && Array.isArray(response.data.data)) {
                data = response.data.data;
            } else if (response.data.data && Array.isArray(response.data.data)) {
                data = response.data.data;
            } else if (Array.isArray(response.data)) {
                data = response.data;
            }
        }
        
        allDrugsData.value = data.map(drug => {
            const drugName = drug.drugName || drug.name || '';
            return {
                id: drug.id,
                drugId: drug.id,
                name: drugName,
                drugName: drugName,
                genericName: drug.genericName || drug.generic_name || '',
                categoryId: drug.category || '',
                category: drug.category || '',
                dosage: drug.strength || '',
                strength: drug.strength || '',
                type: drug.form || drug.type || 'Tablet',
                form: drug.form || '',
                unit: drug.unit || '',
                drugCode: drug.drugCode || drug.id
            };
        });
        
        console.log(`✅ تم تحميل ${allDrugsData.value.length} دواء بنجاح`);
    } catch (err) {
        console.error('Error fetching drugs:', err);
        showSuccessAlert('❌ فشل في تحميل الأدوية.');
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
        return date.toLocaleDateString('ar-SA');
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
                shipment.shipmentNumber.toLowerCase().includes(search) ||
                shipment.requestStatus.includes(search)
        );
    }

    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "shipmentNumber") {
                comparison = a.shipmentNumber.localeCompare(b.shipmentNumber);
            } else if (sortKey.value === "requestDate") {
                const dateA = new Date(a.requestDate);
                const dateB = new Date(b.requestDate);
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
// 6. حالة المكونات المنبثقة
// ----------------------------------------------------
const isSupplyRequestModalOpen = ref(false);
const isRequestViewModalOpen = ref(false); 
const selectedRequestDetails = ref({ 
    id: null, 
    date: '', 
    status: '', 
    items: [], 
    notes: '', 
    storekeeperNotes: null, 
    supplierNotes: null, 
    confirmation: null 
}); 
const isConfirmationModalOpen = ref(false);
const selectedShipmentForConfirmation = ref({ id: null, date: '', status: '', items: [] });

// ----------------------------------------------------
// 7. وظائف العرض والتحكم بالإجراءات
// ----------------------------------------------------
const openSupplyRequestModal = () => {
    isSupplyRequestModalOpen.value = true;
};

const closeSupplyRequestModal = () => {
    isSupplyRequestModalOpen.value = false;
};

const handleSupplyConfirm = async (data) => {
    isSubmittingSupply.value = true;
    try {
        // التحقق من أن جميع الأدوية لديها drugId صحيح
        const itemsWithDrugId = data.items.map(item => {
            let drugId = item.drugId || item.id;
            
            // إذا لم يكن drugId موجوداً، البحث عنه في allDrugsData
            if (!drugId && item.name) {
                const drugInfo = allDrugsData.value.find(d => 
                    d.id === item.id ||
                    d.name === item.name || 
                    d.drugName === item.name ||
                    (d.name && item.name && d.name.toLowerCase() === item.name.toLowerCase()) ||
                    (d.drugName && item.name && d.drugName.toLowerCase() === item.name.toLowerCase())
                );
                drugId = drugInfo?.id || drugInfo?.drugId || null;
            }
            
            if (!drugId) {
                throw new Error(`لا يمكن العثور على معرف الدواء للدواء: ${item.name || 'غير معروف'}`);
            }
            
            return {
                drug_id: drugId,
                requested_qty: item.quantity || item.requested_qty || 1,
            };
        });
        
        const requestData = {
            items: itemsWithDrugId,
            supplier_id: data.supplierId || null,
            notes: data.notes || null,
        };
        
        const response = await endpoints.supplyRequests.create(requestData);
        
        const requestNumber = response.data?.requestNumber || response.requestNumber || `EXT-${response.data?.id || response.id}`;
        showSuccessAlert(`✅ تم إنشاء طلب التوريد رقم ${requestNumber} بنجاح!`);
        closeSupplyRequestModal();
        
        await fetchShipments();
        
    } catch (err) {
        const errorMessage = err.response?.data?.message || err.response?.data?.error || err.message || 'حدث خطأ غير متوقع';
        showSuccessAlert(`❌ فشل في إنشاء طلب التوريد: ${errorMessage}`);
    } finally {
        isSubmittingSupply.value = false;
    }
};

const openRequestViewModal = (shipment) => {
    console.log('📋 Opening modal for shipment:', {
        shipmentId: shipment.id,
        shipmentStatus: shipment.requestStatus,
        shipmentRejectionReason: shipment.rejectionReason,
        detailsRejectionReason: shipment.details?.rejectionReason,
        fullShipment: shipment
    });
    
    // إعداد البيانات للعرض في الـ modal
    // محاولة جلب rejectionReason من عدة مصادر
    const rejectionReason = shipment.details?.rejectionReason || 
                           shipment.rejectionReason || 
                           null;
    
    const rejectedAt = shipment.details?.rejectedAt || 
                      shipment.rejectedAt || 
                      null;
    
    selectedRequestDetails.value = {
        ...shipment.details,
        rejectionReason: rejectionReason,
        rejectedAt: rejectedAt,
        notes: shipment.details?.notes || '',
        storekeeperNotes: shipment.details?.storekeeperNotes || shipment.storekeeperNotes || null,
        supplierNotes: shipment.details?.supplierNotes || shipment.supplierNotes || null,
        confirmation: shipment.details?.confirmationDetails || shipment.confirmationDetails || null
    };
    
    // التأكد من أن confirmation يحتوي على confirmationNotes
    if (selectedRequestDetails.value.confirmation && !selectedRequestDetails.value.confirmation.confirmationNotes) {
        // محاولة جلب confirmationNotes من shipment مباشرة
        if (shipment.confirmationDetails?.confirmationNotes) {
            selectedRequestDetails.value.confirmation.confirmationNotes = shipment.confirmationDetails.confirmationNotes;
        }
    }
    
    // إضافة receivedQuantity إلى items إذا كان موجوداً في confirmation
    if (selectedRequestDetails.value.confirmation?.receivedItems) {
        selectedRequestDetails.value.items = selectedRequestDetails.value.items.map(item => {
            const receivedItem = selectedRequestDetails.value.confirmation.receivedItems.find(
                ri => ri.id === item.id || ri.name === item.name
            );
            if (receivedItem) {
                return {
                    ...item,
                    receivedQuantity: receivedItem.receivedQuantity || item.receivedQuantity || null
                };
            }
            return item;
        });
    }
    
    console.log('📋 Storekeeper - Opening RequestViewModal with data:', {
        storekeeperNotes: selectedRequestDetails.value.storekeeperNotes,
        supplierNotes: selectedRequestDetails.value.supplierNotes,
        confirmationNotes: selectedRequestDetails.value.confirmation?.confirmationNotes,
        confirmation: selectedRequestDetails.value.confirmation,
        rejectionReason: selectedRequestDetails.value.rejectionReason,
        rejectedAt: selectedRequestDetails.value.rejectedAt,
        hasRejectionReason: !!selectedRequestDetails.value.rejectionReason,
        rejectionReasonType: typeof selectedRequestDetails.value.rejectionReason,
        rejectionReasonLength: selectedRequestDetails.value.rejectionReason ? selectedRequestDetails.value.rejectionReason.length : 0,
        fullSelectedRequestDetails: selectedRequestDetails.value
    });
    
    isRequestViewModalOpen.value = true;
};

const closeRequestViewModal = () => {
    isRequestViewModalOpen.value = false;
    selectedRequestDetails.value = { 
        id: null, 
        date: '', 
        status: '', 
        items: [], 
        notes: '', 
        storekeeperNotes: null, 
        supplierNotes: null, 
        confirmation: null 
    }; 
};

const openConfirmationModal = (shipment) => {
    selectedShipmentForConfirmation.value = shipment.details; 
    isConfirmationModalOpen.value = true;
};

const closeConfirmationModal = () => {
    isConfirmationModalOpen.value = false;
    selectedShipmentForConfirmation.value = { id: null, date: '', status: '', items: [] }; 
};

const handleConfirmation = async (confirmationData) => {
    isConfirming.value = true;
    const shipmentId = selectedShipmentForConfirmation.value.id;
    
    try {
        // تحويل receivedItems إلى items بالشكل المتوقع من API
        const items = (confirmationData.receivedItems || []).map(item => ({
            id: item.id,
            receivedQuantity: item.receivedQuantity || item.received_qty || 0
        }));
        
        const requestData = {
            items: items,
            notes: confirmationData.notes || ''
        };
        
        console.log('Confirming delivery with data:', requestData);
        
        const response = await endpoints.supplyRequests.confirmDelivery(shipmentId, requestData);
        
        console.log('Confirm delivery response:', response);
        
        // إعادة جلب البيانات
        await fetchShipments();
        
        const message = response.data?.message || response.message || '✅ تم تأكيد استلام الشحنة بنجاح!';
        showSuccessAlert(message);
        closeConfirmationModal();
        
    } catch (err) {
        console.error('Error confirming delivery:', err);
        console.error('Error response:', err.response);
        const errorMessage = err.response?.data?.message || err.response?.data?.error || err.message || 'حدث خطأ غير معروف';
        showSuccessAlert(`❌ فشل في تأكيد الاستلام: ${errorMessage}`);
    } finally {
        isConfirming.value = false;
    }
};

const openReviewModal = async (shipment) => {
    try {
        // جلب البيانات المحدثة من API
        const response = await endpoints.supplyRequests.getAll();
        
        // التحقق من بنية الاستجابة
        let data = [];
        if (response.data) {
            if (response.data.success && response.data.data && Array.isArray(response.data.data)) {
                data = response.data.data;
            } else if (response.data.data && Array.isArray(response.data.data)) {
                data = response.data.data;
            } else if (Array.isArray(response.data)) {
                data = response.data;
            }
        }
        
        const updatedShipment = data.find(s => s.id === shipment.id) || shipment;
        
        selectedRequestDetails.value = {
            id: updatedShipment.id || shipment.id,
            date: updatedShipment.requestDateFull || updatedShipment.requestDate || shipment.details.date,
            status: updatedShipment.requestStatus || shipment.requestStatus || shipment.details.status,
            items: updatedShipment.items || shipment.details.items || [],
            notes: updatedShipment.notes || shipment.details.notes || '',
            storekeeperNotes: updatedShipment.storekeeperNotes || shipment.details.storekeeperNotes || shipment.storekeeperNotes || null,
            supplierNotes: updatedShipment.supplierNotes || shipment.details.supplierNotes || shipment.supplierNotes || null,
            rejectionReason: updatedShipment.rejectionReason || shipment.details.rejectionReason || null,
            rejectedAt: updatedShipment.rejectedAt || shipment.details.rejectedAt || null,
            confirmation: updatedShipment.confirmationDetails || shipment.details.confirmationDetails || shipment.confirmationDetails || null
        };
        
        // التأكد من أن confirmation يحتوي على confirmationNotes
        if (selectedRequestDetails.value.confirmation && !selectedRequestDetails.value.confirmation.confirmationNotes) {
            if (updatedShipment.confirmationDetails?.confirmationNotes) {
                selectedRequestDetails.value.confirmation.confirmationNotes = updatedShipment.confirmationDetails.confirmationNotes;
            } else if (shipment.confirmationDetails?.confirmationNotes) {
                selectedRequestDetails.value.confirmation.confirmationNotes = shipment.confirmationDetails.confirmationNotes;
            }
        }
        
        // إضافة receivedQuantity إلى items إذا كان موجوداً في confirmation
        if (selectedRequestDetails.value.confirmation?.receivedItems) {
            selectedRequestDetails.value.items = selectedRequestDetails.value.items.map(item => {
                const receivedItem = selectedRequestDetails.value.confirmation.receivedItems.find(
                    ri => ri.id === item.id || ri.name === item.name
                );
                if (receivedItem) {
                    return {
                        ...item,
                        receivedQuantity: receivedItem.receivedQuantity || item.receivedQuantity || null
                    };
                }
                return item;
            });
        }
        
        isRequestViewModalOpen.value = true;
    } catch (err) {
        console.error('Error loading shipment details:', err);
        // في حالة الخطأ، نستخدم البيانات المحلية
        selectedRequestDetails.value = {
            ...shipment.details,
            rejectionReason: shipment.details.rejectionReason || null,
            rejectedAt: shipment.details.rejectedAt || null,
            notes: shipment.details.notes || '',
            storekeeperNotes: shipment.details.storekeeperNotes || shipment.storekeeperNotes || null,
            supplierNotes: shipment.details.supplierNotes || shipment.supplierNotes || null,
            confirmation: shipment.details.confirmationDetails || shipment.confirmationDetails || null
        };
        
        // التأكد من أن confirmation يحتوي على confirmationNotes
        if (selectedRequestDetails.value.confirmation && !selectedRequestDetails.value.confirmation.confirmationNotes) {
            if (shipment.confirmationDetails?.confirmationNotes) {
                selectedRequestDetails.value.confirmation.confirmationNotes = shipment.confirmationDetails.confirmationNotes;
            }
        }
        
        // إضافة receivedQuantity إلى items إذا كان موجوداً في confirmation
        if (selectedRequestDetails.value.confirmation?.receivedItems) {
            selectedRequestDetails.value.items = selectedRequestDetails.value.items.map(item => {
                const receivedItem = selectedRequestDetails.value.confirmation.receivedItems.find(
                    ri => ri.id === item.id || ri.name === item.name
                );
                if (receivedItem) {
                    return {
                        ...item,
                        receivedQuantity: receivedItem.receivedQuantity || item.receivedQuantity || null
                    };
                }
                return item;
            });
        }
        
        isRequestViewModalOpen.value = true;
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
body { font-family: 'Arial', sans-serif; direction: rtl; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
th { background-color: #f2f2f2; font-weight: bold; }
h1 { text-align: center; color: #2E5077; margin-bottom: 10px; }
.results-info { text-align: right; margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #4DA1A9; }
.center-icon { text-align: center; }
</style>

<h1>قائمة طلبات التوريد (تقرير طباعة)</h1>

<p class="results-info">عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}</p>

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
    <td>${shipment.shipmentNumber}</td>
    <td>${formatDate(shipment.requestDate)}</td>
    <td>${shipment.requestStatus}</td>
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
// 9. دورة الحياة
// ----------------------------------------------------
onMounted(() => {
    fetchAllData();
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