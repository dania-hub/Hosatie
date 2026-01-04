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
                        class="h-11 w-11 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
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
                                                    shipment.requestStatus === 'مرفوض',
                                                'text-green-600 font-semibold':
                                                    shipment.requestStatus === 'تم التنفيذ',
                                                'text-yellow-600 font-semibold':
                                                    shipment.requestStatus === 'قيد الانتظار',
                                                'text-blue-600 font-semibold':
                                                    shipment.requestStatus === 'جديد',
                                            }"
                                        >
                                            {{ shipment.requestStatus }}
                                        </td>
                                        <td class="actions-col">
                                            <div class="flex gap-3 justify-center">
                                                <!-- زر معاينة تفاصيل الشحنة - يظهر دائماً -->
                                                <button 
                                                    @click="openRequestViewModal(shipment)"
                                                    class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95" 
                                                    data-tip="معاينة تفاصيل الشحنة">
                                                    <Icon
                                                        icon="famicons:open-outline"
                                                        class="w-4 h-4 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                    />
                                                </button>
                                                
                                                <!-- زر الإجراء الثاني يختلف حسب الحالة -->
                                                <template v-if="shipment.requestStatus === 'مرفوض'">
                                                    <button class="tooltip p-2 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 transition-all duration-200 hover:scale-110 active:scale-95" data-tip="طلب مرفوض">
                                                        <Icon
                                                            icon="tabler:circle-x" 
                                                            class="w-4 h-4 text-red-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'تم التنفيذ'">
                                                    <!-- إذا كانت تم التنفيذ، تظهر زر مراجعة التفاصيل -->
                                                    <button 
                                                        @click="openReviewModal(shipment)"
                                                        class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95" 
                                                        data-tip="مراجعة تفاصيل التنفيذ">
                                                        <Icon
                                                            icon="healthicons:yes-outline"
                                                            class="w-4 h-4 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'جديد' || shipment.statusOriginal === 'approved'">
                                                    <!-- يظهر زر تأكيد الاستلام فقط للطلبات المعتمدة من super-admin -->
                                                    <button
                                                        @click="openConfirmationModal(shipment)" 
                                                        class="tooltip p-2 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        data-tip="تأكيد الإستلام">
                                                        <Icon
                                                            icon="tabler:truck-delivery"
                                                            class="w-4 h-4 text-blue-500 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'قيد الانتظار'">
                                                    <!-- إذا كانت قيد الانتظار، تظهر رسالة انتظار -->
                                                    <button class="tooltip p-2 rounded-lg bg-orange-50 hover:bg-orange-100 border border-orange-200 transition-all duration-200 hover:scale-110 active:scale-95" data-tip="في انتظار اعتماد الطلب من المدير العام">
                                                        <Icon
                                                            icon="solar:clock-circle-bold"
                                                            class="w-4 h-4 text-orange-400"
                                                        />
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredShipments.length === 0">
                                        <td colspan="4" class="py-12">
                                            <EmptyState message="لا توجد طلبات توريد" />
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
import axios from "axios"; // استيراد axios

import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import SupplyRequestModal from "@/components/forpharmacist/SupplyRequestModal.vue";
import RequestViewModal from "@/components/fordepartment/RequestViewModal.vue"; 
import ConfirmationModal from "@/components/fordepartment/ConfirmationModal.vue"; 

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

// إضافة interceptor لإضافة التوكن تلقائيًا
api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  error => {
    return Promise.reject(error);
  }
);

// إضافة interceptor للتعامل مع الأخطاء
api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('API Error:', error.response?.data || error.message);
    return Promise.reject(error);
  }
);

// تعريف جميع الـ endpoints
const endpoints = {
  supplyRequests: {
    getAll: () => api.get('/supplier/supply-requests'),
    getById: (id) => api.get(`/supplier/supply-requests/${id}`),
    create: (data) => api.post('/supplier/supply-requests', data)
  },
  categories: {
    getAll: () => api.get('/supplier/categories')
  },
  drugs: {
    getAll: () => api.get('/supplier/drugs/all'),
    search: (params) => api.get('/supplier/drugs/search', { params })
  },
  hospitals: {
    getAll: () => api.get('/supplier/hospitals')
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
        const response = await endpoints.supplyRequests.getAll();
        // BaseApiController يُرجع البيانات بداخل data
        const responseData = response.data?.data ?? response.data ?? [];
        
        shipmentsData.value = responseData.map(request => ({
            id: request.id,
            shipmentNumber: `SR-${request.id}`,
            requestDate: request.createdAt || '',
            requestStatus: request.status || 'جديد',
            statusOriginal: request.statusOriginal || request.status,
            received: request.statusOriginal === 'fulfilled',
            hospitalName: request.hospitalName || 'غير محدد',
            itemsCount: request.itemsCount || 0,
            approvedBy: request.approvedBy || '',
            details: {
                id: request.id,
                date: request.createdAt,
                status: request.status,
                statusOriginal: request.statusOriginal || request.status,
                hospitalName: request.hospitalName,
                itemsCount: request.itemsCount
            }
        }));
    } catch (err) {
        console.error('Error fetching supply requests:', err);
        throw err;
    }
};

const fetchCategories = async () => {
    try {
        const response = await endpoints.categories.getAll();
        // BaseApiController يُرجع البيانات بداخل data
        const responseData = response.data?.data ?? response.data ?? [];
        categories.value = responseData.map(cat => ({
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
        // BaseApiController يُرجع البيانات بداخل data
        const responseData = response.data?.data ?? response.data ?? [];
        allDrugsData.value = responseData.map(drug => ({
            id: drug.id,
            drugId: drug.id,
            name: drug.name,
            drugName: drug.name, // للتوافق
            genericName: drug.genericName || drug.generic_name,
            strength: drug.strength,
            dosage: drug.strength, // للتوافق
            form: drug.form,
            type: drug.type || drug.form || 'Tablet',
            category: drug.category || '',
            categoryId: drug.categoryId || drug.category || null,
            unit: drug.unit || 'قرص',
            status: drug.status || 'متوفر'
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
        return date.toLocaleDateString('en');
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

// دالة تحويل التاريخ من صيغة مختلفة إلى Date
const parseDate = (dateString) => {
    if (!dateString) return null;
    try {
        // محاولة تحويل الصيغة Y/m/d إلى Date
        if (dateString.includes('/')) {
            const parts = dateString.split('/');
            if (parts.length === 3) {
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }
        }
        const date = new Date(dateString);
        return isNaN(date.getTime()) ? null : date;
    } catch {
        return null;
    }
};

// دالة لمسح فلتر التاريخ
const clearDateFilter = () => {
    dateFrom.value = "";
    dateTo.value = "";
};

const filteredShipments = computed(() => {
    let list = shipmentsData.value;
    
    // 1. التصفية حسب البحث
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (shipment) =>
                shipment.shipmentNumber.toLowerCase().includes(search) ||
                shipment.requestStatus.includes(search)
        );
    }

    // 2. فلترة حسب التاريخ
    if (dateFrom.value || dateTo.value) {
        list = list.filter((shipment) => {
            const requestDate = shipment.requestDate;
            if (!requestDate) return false;

            const requestDateObj = parseDate(requestDate);
            if (!requestDateObj) return false;

            requestDateObj.setHours(0, 0, 0, 0); // إزالة الوقت للمقارنة

            let matchesFrom = true;
            let matchesTo = true;

            if (dateFrom.value) {
                const fromDate = new Date(dateFrom.value);
                fromDate.setHours(0, 0, 0, 0);
                matchesFrom = requestDateObj >= fromDate;
            }

            if (dateTo.value) {
                const toDate = new Date(dateTo.value);
                toDate.setHours(23, 59, 59, 999); // نهاية اليوم
                matchesTo = requestDateObj <= toDate;
            }

            return matchesFrom && matchesTo;
        });
    }

    // 3. الفرز
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
        // جلب hospitals أولاً للحصول على hospital_id
        const hospitalsResponse = await endpoints.hospitals.getAll();
        const hospitalsData = hospitalsResponse.data?.data ?? hospitalsResponse.data ?? [];
        
        // استخدام أول مستشفى كافتراضي (يمكن تحسين هذا لاحقاً)
        const hospitalId = hospitalsData.length > 0 ? hospitalsData[0].id : null;
        
        if (!hospitalId) {
            throw new Error('لا توجد مستشفيات متاحة');
        }
        
        const requestData = {
            hospital_id: hospitalId,
            items: data.items.map(item => ({
                drug_id: item.drugId || null,
                quantity: item.quantity
            })),
            notes: data.notes || '',
            priority: data.priority || 'normal'
        };
        
        const response = await endpoints.supplyRequests.create(requestData);
        
        // BaseApiController يُرجع البيانات بداخل data
        const responseData = response.data?.data ?? response.data;
        
        showSuccessAlert(` تم إنشاء طلب التوريد بنجاح!`);
        closeSupplyRequestModal();
        
        await fetchShipments();
        
    } catch (err) {
        const errorMessage = err.response?.data?.message || err.message || 'فشل في إنشاء طلب التوريد';
        showSuccessAlert(` ${errorMessage}`);
        console.error('Error creating supply request:', err);
    } finally {
        isSubmittingSupply.value = false;
    }
};

const openRequestViewModal = async (shipment) => {
    try {
        // جلب تفاصيل الطلب من API
        const response = await endpoints.supplyRequests.getById(shipment.id);
        const responseData = response.data?.data ?? response.data;
        
        selectedRequestDetails.value = {
            id: responseData.id,
            date: responseData.createdAt,
            status: responseData.status,
            statusOriginal: responseData.statusOriginal,
            department: responseData.department || responseData.hospital?.name || 'غير محدد',
            hospital: responseData.hospital,
            requestedBy: responseData.requestedBy,
            items: (responseData.items || []).map(item => {
                // استخراج الكمية المطلوبة - التحقق من جميع التنسيقات الممكنة
                const requestedQty = item.requested_qty ?? item.requestedQty ?? item.requestedQuantity ?? item.quantity ?? 0;
                
                return {
                    id: item.id,
                    drugId: item.drugId,
                    name: item.name || item.drugName,
                    drugName: item.name || item.drugName,
                    category: item.category,
                    requested_qty: Number(requestedQty) || 0,
                    requestedQty: Number(requestedQty) || 0,
                    requestedQuantity: Number(requestedQty) || 0,
                    approved_qty: Number(item.approved_qty ?? item.approvedQty ?? item.approvedQuantity ?? 0) || 0,
                    approvedQty: Number(item.approvedQty ?? item.approvedQuantity ?? item.approved_qty ?? 0) || 0,
                    approvedQuantity: Number(item.approvedQuantity ?? item.approved_qty ?? item.approvedQty ?? 0) || 0,
                    quantity: Number(requestedQty) || 0,
                    strength: item.strength || null,
                    dosage: item.dosage || item.strength || null,
                    form: item.form || null,
                    type: item.type || item.form || 'Tablet',
                    unit: item.unit || 'وحدة'
                };
            }),
            notes: responseData.notes || '',
            storekeeperNotes: responseData.storekeeperNotes || null,
            supplierNotes: responseData.supplierNotes || null,
            confirmation: responseData.confirmation || null,
            rejectionReason: responseData.rejectionReason || null,
            rejectedAt: responseData.rejectedAt || null
        };
        isRequestViewModalOpen.value = true;
    } catch (err) {
        console.error('Error fetching request details:', err);
        // في حالة الخطأ، استخدام البيانات المتاحة
        selectedRequestDetails.value = shipment.details || {
            id: shipment.id,
            date: shipment.requestDate,
            status: shipment.requestStatus,
            items: []
        };
        isRequestViewModalOpen.value = true;
    }
};

const closeRequestViewModal = () => {
    isRequestViewModalOpen.value = false;
    selectedRequestDetails.value = { id: null, date: '', status: '', items: [] }; 
};

const openConfirmationModal = async (shipment) => {
    try {
        // جلب تفاصيل الطلب من API
        const response = await endpoints.supplyRequests.getById(shipment.id);
        const responseData = response.data?.data ?? response.data;
        
        selectedShipmentForConfirmation.value = {
            id: responseData.id,
            date: responseData.createdAt,
            status: responseData.status,
            statusOriginal: responseData.statusOriginal,
            items: (responseData.items || []).map(item => {
                // استخراج الكمية المطلوبة - التحقق من جميع التنسيقات الممكنة
                const requestedQty = item.requested_qty ?? item.requestedQty ?? item.requestedQuantity ?? item.quantity ?? 0;
                const approvedQty = item.approved_qty ?? item.approvedQty ?? item.approvedQuantity ?? null;
                
                return {
                    id: item.id,
                    drugId: item.drugId,
                    name: item.name || item.drugName,
                    drugName: item.name || item.drugName,
                    category: item.category,
                    requested_qty: Number(requestedQty) || 0,
                    requestedQty: Number(requestedQty) || 0,
                    requestedQuantity: Number(requestedQty) || 0,
                    approved_qty: approvedQty !== null ? Number(approvedQty) : null,
                    approvedQty: approvedQty !== null ? Number(approvedQty) : null,
                    approvedQuantity: approvedQty !== null ? Number(approvedQty) : null,
                    quantity: Number(requestedQty) || 0,
                    originalQuantity: Number(requestedQty) || 0,
                    sentQuantity: approvedQty !== null ? Number(approvedQty) : Number(requestedQty) || 0,
                    strength: item.strength || null,
                    dosage: item.dosage || item.strength || null,
                    form: item.form || null,
                    type: item.type || item.form || 'Tablet',
                    unit: item.unit || 'وحدة'
                };
            })
        };
        isConfirmationModalOpen.value = true;
    } catch (err) {
        console.error('Error fetching request details for confirmation:', err);
        // في حالة الخطأ، استخدام البيانات المتاحة
        selectedShipmentForConfirmation.value = shipment.details || {
            id: shipment.id,
            date: shipment.requestDate,
            status: shipment.requestStatus,
            items: []
        };
        isConfirmationModalOpen.value = true;
    }
};

const closeConfirmationModal = () => {
    isConfirmationModalOpen.value = false;
    selectedShipmentForConfirmation.value = { id: null, date: '', status: '', items: [] }; 
};

const handleConfirmation = async (confirmationData) => {
    isConfirming.value = true;
    const shipmentId = selectedShipmentForConfirmation.value.id;
    
    try {
        // التحقق من أن الطلب معتمد قبل تأكيد الاستلام
        const shipment = shipmentsData.value.find(s => s.id === shipmentId);
        if (shipment && shipment.requestStatus !== 'جديد' && shipment.statusOriginal !== 'approved') {
            showSuccessAlert(` لا يمكن تأكيد الاستلام. يجب أن يكون الطلب معتمداً من المدير العام أولاً.`);
            closeConfirmationModal();
            return;
        }
        
        // استخدام shipments endpoint لتأكيد الاستلام
        const response = await api.post(`/supplier/shipments/${shipmentId}/confirm`, confirmationData);
        
        const shipmentIndex = shipmentsData.value.findIndex(
            s => s.id === shipmentId
        );
        
        if (shipmentIndex !== -1) {
            shipmentsData.value[shipmentIndex].requestStatus = 'تم التنفيذ';
            shipmentsData.value[shipmentIndex].received = true;
        }
        
        showSuccessAlert(` تم تأكيد استلام الشحنة بنجاح!`);
        closeConfirmationModal();
        await fetchShipments();
        
    } catch (err) {
        const errorMessage = err.response?.data?.message || err.message || 'فشل في تأكيد الاستلام';
        showSuccessAlert(` ${errorMessage}`);
        console.error('Error confirming shipment:', err);
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
        showSuccessAlert(" فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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

<h1>قائمة طلبات التوريد </h1>

<p class="results-info">عدد النتائج : ${resultsCount}</p>

<table>
<thead>
    <tr>
    <th>رقم الشحنة</th>
    <th>تاريخ الطلب</th>
    <th>حالة الطلب</th>
   </tr>
</thead>
<tbody>
`;

    filteredShipments.value.forEach((shipment) => {
        const receivedIcon = shipment.received ? '' : '';
        tableHtml += `
<tr>
    <td>${shipment.shipmentNumber}</td>
    <td>${formatDate(shipment.requestDate)}</td>
    <td>${shipment.requestStatus}</td>
 
</tr>
`;
    });

    tableHtml += `
</tbody>
</table>
`;

    printWindow.document.write("<html><head><title> قائمة طلبات التوريد</title>");
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