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
                                                    shipment.requestStatus ==='مرفوضة',
                                                'text-green-600 font-semibold':
                                                    shipment.requestStatus ===
                                                    'تم الإستلام',
                                                'text-yellow-600 font-semibold':
                                                    shipment.requestStatus ===
                                                    'قيد الاستلام',
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
                                                <template v-if="shipment.requestStatus === 'مرفوضة'">
                                                    <button class="tooltip" data-tip="طلب مرفوض">
                                                        <Icon
                                                            icon="tabler:circle-x" 
                                                            class="w-5 h-5 text-red-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'تم الإستلام'">
                                                    <!-- علامة الصح عندما تكون الحالة "تم الإستلام" -->
                                                    <button class="tooltip" data-tip="تم الإستلام">
                                                        <Icon
                                                            icon="solar:check-circle-bold"
                                                            class="w-5 h-5 text-green-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'قيد الاستلام'">
                                                    <!-- زر تأكيد الاستلام عندما تكون الحالة "قيد الاستلام" -->
                                                    <button
                                                        @click="openConfirmationModal(shipment)" 
                                                        class="tooltip"
                                                        data-tip="تأكيد الإستلام">
                                                        <Icon
                                                            icon="tabler:truck-delivery"
                                                            class="w-5 h-5 text-red-500 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else>
                                                    <!-- زر في انتظار القبول للحالات الأخرى (مثل "قيد الانتظار") -->
                                                    <button class="tooltip" data-tip="في انتظار القبول">
                                                        <Icon
                                                            icon="solar:clock-circle-bold-duotone"
                                                            class="w-5 h-5 text-gray-400"
                                                        />
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="filteredShipments.length === 0">
                                        <td colspan="4" class="py-12">
                                            <EmptyState message="لم يتم العثور على طلبات توريد" />
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
import SupplyRequestModal from "@/components/fordepartment/SupplyRequestModal.vue";
import RequestViewModal from "@/components/fordepartment/RequestViewModal.vue"; 
import ConfirmationModal from "@/components/fordepartment/ConfirmationModal.vue"; 

// ----------------------------------------------------
// 1. إعدادات axios
// ----------------------------------------------------
const API_BASE_URL = "/api/department-admin";
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// إضافة interceptor لإضافة التوكن تلقائيًا
api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('token') || localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  error => {
    return Promise.reject(error);
  }
);

// تعريف جميع الـ endpoints
const endpoints = {
  shipments: {
    getAll: () => api.get('/shipments'),
    getById: (id) => api.get(`/shipments/${id}`),
    create: (data) => api.post('/shipments', data),
    update: (id, data) => api.put(`/shipments/${id}`, data),
    confirm: (id, data) => api.post(`/shipments/${id}/confirm`, data)
  },
  categories: {
    getAll: () => api.get('/categories')
  },
  drugs: {
    getAll: () => api.get('/drugs'),
    search: (params) => api.get('/drugs/search', { params })
  },
  supplyRequests: {
    create: (data) => api.post('/supply-requests', data)
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
        const response = await endpoints.shipments.getAll();
        // BaseApiController يُرجع البيانات في response.data.data
        const shipmentsDataRaw = response.data?.data || response.data || response;
        const shipmentsList = Array.isArray(shipmentsDataRaw) ? shipmentsDataRaw : [];
        
        shipmentsData.value = shipmentsList.map(shipment => {
            // معالجة التاريخ
            let requestDate = shipment.requestDate || shipment.request_date || shipment.created_at || shipment.createdAt;
            if (requestDate && typeof requestDate === 'string') {
                // تحويل ISO string إلى تنسيق قابل للقراءة
                try {
                    const date = new Date(requestDate);
                    requestDate = date.toISOString();
                } catch (e) {
                    // إذا فشل التحويل، نستخدم القيمة كما هي
                }
            }
            
            // معالجة الحالة
            const status = shipment.status || shipment.requestStatus || shipment.request_status || 'غير محدد';
            const received = shipment.received || (status === 'تم الإستلام') || (status === 'fulfilled');
            
            return {
                id: shipment.id,
                shipmentNumber: shipment.shipmentNumber || shipment.shipment_number || `REQ-${shipment.id}`,
                requestDate: requestDate,
                requestStatus: status,
                received: received,
                details: {
                    id: shipment.id,
                    shipmentNumber: shipment.shipmentNumber || shipment.shipment_number || `REQ-${shipment.id}`,
                    date: requestDate,
                    status: status,
                    items: shipment.items || [],
                    notes: shipment.notes || '',
                    ...(shipment.confirmationDetails && {
                        confirmationDetails: shipment.confirmationDetails
                    })
                }
            };
        });
    } catch (err) {
        console.error('Error fetching shipments:', err);
        shipmentsData.value = [];
        throw err;
    }
};

const fetchCategories = async () => {
    try {
        const response = await endpoints.categories.getAll();
        // BaseApiController يُرجع البيانات في response.data.data
        const categoriesDataRaw = response.data?.data || response.data || response;
        const categoriesList = Array.isArray(categoriesDataRaw) ? categoriesDataRaw : [];
        
        categories.value = categoriesList.map(cat => ({
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
        const response = await endpoints.drugs.getAll();
        // BaseApiController يُرجع البيانات في response.data.data
        const drugsDataRaw = response.data?.data || response.data || response;
        // في حالة كون الاستجابة كائن مُرقّم (Laravel paginator)، نأخذ الحقل data
        const drugs = Array.isArray(drugsDataRaw)
            ? drugsDataRaw
            : (drugsDataRaw.data || []);

        allDrugsData.value = drugs.map(drug => ({
            id: drug.id,
            name: drug.name,
            categoryId: drug.categoryId,
            dosage: drug.dosage || drug.strength,
            type: drug.type || 'Tablet'
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
const selectedRequestDetails = ref({ id: null, date: '', status: '', items: [] }); 
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
        // التحقق من أن جميع الأدوية لديها drugId
        const itemsWithDrugId = data.items.map(item => {
            let drugId = item.drugId;
            
            // إذا لم يكن drugId موجوداً، البحث عنه في allDrugsData
            if (!drugId && item.name) {
                const drugInfo = allDrugsData.value.find(d => 
                    d.name === item.name || 
                    d.name?.toLowerCase() === item.name?.toLowerCase()
                );
                drugId = drugInfo?.id || null;
            }
            
            if (!drugId) {
                throw new Error(`لا يمكن العثور على معرف الدواء للدواء: ${item.name}`);
            }
            
            return {
                drugId: drugId,
                drugName: item.name,
                quantity: item.quantity,
                unit: item.unit,
                type: item.type
            };
        });
        
        const requestData = {
            items: itemsWithDrugId,
            notes: data.notes || '',
            departmentId: 1, // استبدل بقسم المستخدم الحالي
            priority: data.priority || 'normal'
        };
        
        const response = await endpoints.supplyRequests.create(requestData);
        // BaseApiController يُرجع البيانات في response.data.data
        const responseData = response.data?.data || response.data || response;
        
        showSuccessAlert(`✅ تم إنشاء طلب التوريد رقم ${responseData.requestNumber || responseData.id} بنجاح!`);
        closeSupplyRequestModal();
        
        await fetchShipments();
        
    } catch (err) {
        console.error('خطأ في إنشاء طلب التوريد:', err);
        const errorMessage = err.response?.data?.message || err.message || 'حدث خطأ غير معروف';
        showSuccessAlert(`❌ فشل في إنشاء طلب التوريد: ${errorMessage}`);
    } finally {
        isSubmittingSupply.value = false;
    }
};

const openRequestViewModal = async (shipment) => {
    let fetchedData = null;
    // دائماً نجلب البيانات من API لضمان الحصول على أحدث البيانات
    try {
        const response = await endpoints.shipments.getById(shipment.id);
        fetchedData = response.data?.data ?? response.data;
        
        if (fetchedData) {
            shipment.details = {
                id: fetchedData.id,
                date: fetchedData.requestDate || fetchedData.created_at,
                status: fetchedData.status,
                items: fetchedData.items || [],
                notes: fetchedData.notes || '',
                storekeeperNotes: fetchedData.storekeeperNotes || null,
                supplierNotes: fetchedData.supplierNotes || null,
                confirmationNotes: fetchedData.confirmationNotes || null,
                ...(fetchedData.confirmationDetails && {
                    confirmationDetails: fetchedData.confirmationDetails
                })
            };
        }
    } catch (err) {
        console.error('Error fetching shipment details:', err);
    }
    
    // تحديث البيانات مع التأكد من وجود الكميات المطلوبة والمرسلة والمستلمة
    selectedRequestDetails.value = {
        ...shipment.details,
        storekeeperNotes: shipment.details.storekeeperNotes || fetchedData?.storekeeperNotes || null,
        storekeeperNotesSource: shipment.details.storekeeperNotesSource || fetchedData?.storekeeperNotesSource || null,
        supplierNotes: shipment.details.supplierNotes || fetchedData?.supplierNotes || null,
        confirmationNotes: fetchedData?.confirmationNotes || shipment.details.confirmationNotes || null,
        confirmationNotesSource: shipment.details.confirmationNotesSource || fetchedData?.confirmationNotesSource || null,
        rejectionReason: fetchedData?.rejectionReason || shipment.details.rejectionReason || shipment.rejectionReason || null,
        rejectedAt: fetchedData?.rejectedAt || shipment.details.rejectedAt || shipment.rejectedAt || null,
        items: (shipment.details.items || []).map(item => ({
            ...item,
            // الكمية المطلوبة
            quantity: item.quantity ?? item.requestedQty ?? item.requested_qty ?? 0,
            requestedQty: item.requestedQty ?? item.requested_qty ?? item.quantity ?? 0,
            requested_qty: item.requested_qty ?? item.requestedQty ?? item.quantity ?? 0,
            // الكمية المرسلة (المعتمدة)
            approvedQty: item.approvedQty ?? item.approved_qty ?? null,
            approved_qty: item.approved_qty ?? item.approvedQty ?? null,
            sentQuantity: item.sentQuantity ?? item.approvedQty ?? item.approved_qty ?? null,
            // الكمية المستلمة - فقط fulfilled_qty، لا نستخدم approved_qty كبديل
            fulfilledQty: item.fulfilledQty ?? item.fulfilled_qty ?? null,
            fulfilled_qty: item.fulfilled_qty ?? item.fulfilledQty ?? null,
            // التحقق من أن receivedQuantity ليس هو نفس approved_qty
            receivedQuantity: (() => {
                const fulfilled = item.fulfilled_qty ?? item.fulfilledQty ?? null;
                if (fulfilled !== null && fulfilled !== undefined) {
                    return fulfilled;
                }
                // إذا كان receivedQuantity موجوداً وليس نفس approved_qty، نستخدمه
                const approved = item.approved_qty ?? item.approvedQty ?? null;
                const received = item.receivedQuantity;
                if (received !== null && received !== undefined && received !== approved) {
                    return received;
                }
                return null;
            })(),
            // معلومات إضافية
            unit: item.unit || 'وحدة',
            dosage: item.dosage || item.strength || '',
            type: item.type || item.form || ''
        })),
        confirmation: shipment.details.confirmationDetails || (shipment.requestStatus === 'تم الإستلام' || shipment.received || fetchedData?.status === 'تم الإستلام' ? {
            confirmedAt: shipment.details.confirmationDetails?.confirmedAt || fetchedData?.confirmationDetails?.confirmedAt || new Date().toISOString(),
            confirmationNotes: fetchedData?.confirmationNotes || shipment.details.confirmationNotes || null
        } : null),
        confirmationNotesSource: shipment.details.confirmationNotesSource || fetchedData?.confirmationNotesSource || null,
        rejectionReason: fetchedData?.rejectionReason || shipment.details.rejectionReason || shipment.rejectionReason || null,
        rejectedAt: fetchedData?.rejectedAt || shipment.details.rejectedAt || shipment.rejectedAt || null
    };
    isRequestViewModalOpen.value = true;
};

const closeRequestViewModal = () => {
    isRequestViewModalOpen.value = false;
    selectedRequestDetails.value = { id: null, date: '', status: '', items: [] }; 
};

const openConfirmationModal = async (shipment) => {
    let fetchedData = null;
    // جلب البيانات من API لضمان الحصول على أحدث البيانات بما فيها الكمية المرسلة
    try {
        const response = await endpoints.shipments.getById(shipment.id);
        fetchedData = response.data?.data ?? response.data;
        
        if (fetchedData) {
            shipment.details = {
                id: fetchedData.id,
                date: fetchedData.requestDate || fetchedData.created_at,
                status: fetchedData.status,
                storekeeperNotes: fetchedData.storekeeperNotes || null,
                supplierNotes: fetchedData.supplierNotes || null,
                items: (fetchedData.items || []).map(item => ({
                    ...item,
                    // الكمية المطلوبة
                    quantity: item.quantity ?? item.requestedQty ?? item.requested_qty ?? 0,
                    requestedQty: item.requestedQty ?? item.requested_qty ?? item.quantity ?? 0,
                    requested_qty: item.requested_qty ?? item.requestedQty ?? item.quantity ?? 0,
                    // الكمية المرسلة (المعتمدة)
                    approvedQty: item.approvedQty ?? item.approved_qty ?? null,
                    approved_qty: item.approved_qty ?? item.approvedQty ?? null,
                    sentQuantity: item.sentQuantity ?? item.approvedQty ?? item.approved_qty ?? null,
                    // الكمية المستلمة - فقط fulfilled_qty، لا نستخدم approved_qty كبديل
                    fulfilledQty: item.fulfilledQty ?? item.fulfilled_qty ?? null,
                    fulfilled_qty: item.fulfilled_qty ?? item.fulfilledQty ?? null,
                    // التحقق من أن receivedQuantity ليس هو نفس approved_qty
                    receivedQuantity: (() => {
                        const fulfilled = item.fulfilled_qty ?? item.fulfilledQty ?? null;
                        if (fulfilled !== null && fulfilled !== undefined) {
                            return fulfilled;
                        }
                        // إذا كان receivedQuantity موجوداً وليس نفس approved_qty، نستخدمه
                        const approved = item.approved_qty ?? item.approvedQty ?? null;
                        const received = item.receivedQuantity;
                        if (received !== null && received !== undefined && received !== approved) {
                            return received;
                        }
                        return null;
                    })(),
                    // معلومات إضافية
                    unit: item.unit || 'وحدة',
                    dosage: item.dosage || item.strength || '',
                    type: item.type || item.form || ''
                })),
                notes: fetchedData.notes || '',
                ...(fetchedData.confirmationDetails && {
                    confirmationDetails: fetchedData.confirmationDetails
                })
            };
        }
    } catch (err) {
        console.error('Error fetching shipment details:', err);
    }
    
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
        // إرسال بيانات التأكيد (يمكن أن تكون فارغة أو تحتوي على ملاحظات)
        const response = await endpoints.shipments.confirm(shipmentId, confirmationData || {});
        // BaseApiController يُرجع البيانات في response.data.data
        const responseData = response.data?.data ?? response.data;
        
        const shipmentIndex = shipmentsData.value.findIndex(
            s => s.id === shipmentId
        );
        
        if (shipmentIndex !== -1) {
            shipmentsData.value[shipmentIndex].requestStatus = responseData?.status || 'تم الإستلام';
            shipmentsData.value[shipmentIndex].received = true;
            
            // تحديث تفاصيل الشحنة مع البيانات المحدثة
            if (responseData?.confirmationDetails) {
                shipmentsData.value[shipmentIndex].details.confirmationDetails = responseData.confirmationDetails;
            }
            
            // تحديث عناصر الشحنة بالكميات المستلمة
            if (responseData?.items && Array.isArray(responseData.items)) {
                shipmentsData.value[shipmentIndex].details.items = responseData.items.map(item => ({
                    ...item,
                    receivedQuantity: item.receivedQuantity ?? item.fulfilledQty ?? item.fulfilled_qty ?? null,
                    quantity: item.quantity ?? item.requestedQty ?? item.requested_qty ?? 0,
                }));
            }
        }
        
        showSuccessAlert(`✅ تم تأكيد استلام الشحنة بنجاح!`);
        closeConfirmationModal();
        
        // إعادة تحميل الشحنات لتحديث البيانات
        await fetchShipments();
        
    } catch (err) {
        const errorMessage = err.response?.data?.message || err.message || 'حدث خطأ غير متوقع';
        showSuccessAlert(`❌ فشل في تأكيد الاستلام: ${errorMessage}`);
    } finally {
        isConfirming.value = false;
    }
};

const openReviewModal = (shipment) => {
    selectedRequestDetails.value = {
        ...shipment.details,
        confirmation: shipment.details.confirmationDetails
    };
    isRequestViewModalOpen.value = true;
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
