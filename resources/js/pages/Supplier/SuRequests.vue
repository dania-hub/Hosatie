<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
        <div
                class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0"
            >
                <div class="flex items-center gap-3 w-full sm:max-w-xl">
                    <search v-model="searchTerm" placeholder="ابحث في جميع الحقول (رقم الشحنة، حالة الطلب...)" />
                    
                    <!-- زر إظهار/إخفاء فلتر التاريخ -->
                    <button
                        @click="showDateFilter = !showDateFilter"
                        class="h-11 w-12 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
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
                                                    shipment.requestStatus === 'مرفوض' || shipment.requestStatus === 'مرفوضة',
                                                'text-blue-600 font-semibold':
                                                    shipment.requestStatus === 'تم الاستلام' || shipment.requestStatus === 'تم الإستلام',
                                                'text-amber-500 font-semibold':
                                                    shipment.requestStatus === 'قيد الاستلام',
                                                'text-green-600 font-semibold':
                                                    shipment.requestStatus === 'جديد' || shipment.requestStatus === 'approved',
                                                'text-gray-500 font-semibold':
                                                    shipment.requestStatus === 'قيد الانتظار' || shipment.requestStatus === 'pending',
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
                                                <template v-if="shipment.requestStatus === 'مرفوض' || shipment.requestStatus === 'مرفوضة'">
                                                    <button class="tooltip p-2 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 transition-all duration-200 hover:scale-110 active:scale-95" data-tip="طلب مرفوض">
                                                        <Icon
                                                            icon="tabler:circle-x" 
                                                            class="w-4 h-4 text-red-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'تم الاستلام' || shipment.requestStatus === 'تم الإستلام'">
                                                    <!-- علامة الصح عندما تكون الحالة "تم الإستلام" -->
                                                    <button class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95" data-tip="تم الإستلام">
                                                        <Icon
                                                            icon="solar:check-circle-bold"
                                                            class="w-4 h-4 text-green-600"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else-if="shipment.requestStatus === 'جديد' || shipment.requestStatus === 'approved'">
                                                    <!-- زر تأكيد الإرسال عندما تكون الحالة "جديد" (approved) -->
                                                    <button
                                                        @click="openConfirmationModal(shipment)" 
                                                        class="tooltip p-2 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        data-tip="تأكيد الإرسال">
                                                        <Icon
                                                            icon="tabler:truck-delivery"
                                                            class="w-4 h-4 text-blue-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>

                                                <template v-else-if="shipment.requestStatus === 'قيد الاستلام' || shipment.requestStatus === 'fulfilled'">
                                                    <!-- زر تأكيد الاستلام عندما تكون الحالة "قيد الاستلام" -->
                                                    <button 
                                                        @click="openConfirmationModal(shipment)"
                                                        class="tooltip p-2 rounded-lg bg-amber-50 hover:bg-amber-100 border border-amber-200 transition-all duration-200 hover:scale-110 active:scale-95" 
                                                        data-tip="تأكيد الاستلام">
                                                        <Icon
                                                            icon="tabler:truck-delivery"
                                                            class="w-4 h-4 text-amber-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </template>
                                                
                                                <template v-else>
                                                    <!-- زر في انتظار القبول للحالات الأخرى (مثل "قيد الانتظار") -->
                                                    <button class="tooltip p-2 rounded-lg bg-gray-50 hover:bg-gray-100 border border-gray-200 transition-all duration-200 hover:scale-110 active:scale-95" data-tip="في انتظار القبول">
                                                        <Icon
                                                            icon="solar:clock-circle-bold-duotone"
                                                            class="w-4 h-4 text-gray-400"
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
            :drugs-data="drugsData"
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

import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import SupplyRequestModal from "@/components/forpharmacist/SupplyRequestModal.vue";
import RequestViewModal from "@/components/forSu/RequestViewModal.vue"; 
import ConfirmationModal from "@/components/forSu/ConfirmationsModal.vue"; 
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. إعدادات axios
// ----------------------------------------------------
const api = axios.create({
  baseURL: '/api/supplier',
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
  (error) => Promise.reject(error)
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
  shipments: {
    getAll: () => api.get('/supply-requests'),
    getById: (id) => api.get(`/supply-requests/${id}`),
    confirm: (id, data) => api.post(`/shipments/${id}/confirm`, data)
  },
  categories: {
    getAll: () => api.get('/categories')
  },
  drugs: {
    getAll: () => api.get('/drugs'),
    getAllForSupply: () => api.get('/drugs/all'),
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
const drugsData = ref([]);
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
            fetchDrugs(),
            fetchDrugsInventory()
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
        // Laravel Resources wrap collections in a 'data' property
        const data = response.data?.data ?? response.data;
        const shipments = Array.isArray(data) ? data : [];
        
        shipmentsData.value = shipments.map(shipment => ({
            id: shipment.id,
            shipmentNumber: shipment.shipmentNumber || `SHP-${shipment.id}`,
            requestDate: shipment.requestDate || shipment.created_at,
            requestStatus: shipment.status || 'قيد الانتظار',
            received: shipment.received || (shipment.status === 'تم الإستلام' || shipment.status === 'fulfilled'),
            details: {
                id: shipment.id,
                date: shipment.requestDate || shipment.created_at,
                status: shipment.status,
                items: shipment.items || [],
                notes: shipment.notes || '',
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
        const response = await endpoints.categories.getAll();
        // Laravel Resources wrap collections in a 'data' property
        const data = response.data?.data ?? response.data;
        const cats = Array.isArray(data) ? data : [];
        
        categories.value = cats.map(cat => ({
            id: cat.id || cat,
            name: typeof cat === 'string' ? cat : (cat.name || cat)
        }));
    } catch (err) {
        console.error('Error fetching categories:', err);
        categories.value = [];
    }
};

const fetchDrugs = async () => {
    try {
        // استخدام /drugs/all للحصول على جميع الأدوية المتاحة لإضافتها للطلب
        const response = await endpoints.drugs.getAllForSupply();
        // Laravel Resources wrap collections in a 'data' property
        const data = response.data?.data ?? response.data;
        const drugs = Array.isArray(data) ? data : [];
        
        allDrugsData.value = drugs.map(drug => ({
            id: drug.id,
            drugId: drug.id,
            name: drug.drugName || drug.name,
            genericName: drug.genericName || drug.generic_name,
            strength: drug.strength,
            form: drug.form,
            category: drug.category,
            categoryId: drug.category,
            unit: drug.unit,
            maxMonthlyDose: drug.maxMonthlyDose || drug.max_monthly_dose,
            status: drug.status,
            manufacturer: drug.manufacturer,
            country: drug.country,
            utilizationType: drug.utilizationType || drug.utilization_type,
            dosage: drug.strength,
            type: drug.form || 'Tablet',
            units_per_box: drug.units_per_box || drug.unitsPerBox || 1
        }));
    } catch (err) {
        console.error('Error fetching drugs:', err);
        allDrugsData.value = [];
    }
};

const fetchDrugsInventory = async () => {
    try {
        const response = await api.get('/drugs');
        const data = response.data?.data ?? response.data;
        drugsData.value = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('Error fetching drugs inventory:', err);
        drugsData.value = [];
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

// دالة تحويل التاريخ من صيغة (yyyy/mm/dd) إلى كائن Date للمقارنة
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
    
    // 1. البحث
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (shipment) =>
                shipment.shipmentNumber.toLowerCase().includes(search) ||
                shipment.requestStatus.includes(search)
        );
    }

    // 2. فلترة حسب التاريخ (تاريخ الطلب)
    if (dateFrom.value || dateTo.value) {
        list = list.filter((shipment) => {
            const requestDate = shipment.requestDate;
            if (!requestDate) return false;

            let requestDateObj = parseDate(requestDate);
            if (!requestDateObj) {
                // محاولة تحويل التاريخ من صيغة ISO
                try {
                    const date = new Date(requestDate);
                    if (isNaN(date.getTime())) return false;
                    requestDateObj = date;
                } catch {
                    return false;
                }
            }

            requestDateObj.setHours(0, 0, 0, 0);

            let matchesFrom = true;
            let matchesTo = true;

            if (dateFrom.value) {
                const fromDate = new Date(dateFrom.value);
                fromDate.setHours(0, 0, 0, 0);
                matchesFrom = requestDateObj >= fromDate;
            }

            if (dateTo.value) {
                const toDate = new Date(dateTo.value);
                toDate.setHours(23, 59, 59, 999);
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
        const hospitalsResponse = await api.get("/hospitals");
        const hospitalsData = hospitalsResponse.data?.data ?? hospitalsResponse.data ?? [];
        
        // استخدام أول مستشفى كافتراضي
        const hospitalId = hospitalsData.length > 0 ? hospitalsData[0].id : null;
        
        if (!hospitalId) {
            showErrorAlert(" لا توجد مستشفيات متاحة لإرسال الطلب إليها.");
            isSubmittingSupply.value = false;
            return;
        }

        const requestData = {
            hospital_id: hospitalId,
            items: data.items.map(item => ({
                drug_id: item.drugId || item.id,
                quantity: item.quantity
            })),
            notes: data.notes || ''
        };
        
        const response = await endpoints.supplyRequests.create(requestData);
        // Laravel Resources wrap collections in a 'data' property
        const responseData = response.data?.data ?? response.data;
        
        const requestNumber = responseData?.requestNumber || responseData?.id || 'N/A';
        showSuccessAlert(` تم إنشاء طلب التوريد رقم ${requestNumber} بنجاح!`);
        closeSupplyRequestModal();
        
        // إعادة تحميل الشحنات بعد إنشاء الطلب
        await fetchShipments();
        
    } catch (err) {
        const errorMessage = err.response?.data?.message || err.message || 'حدث خطأ غير متوقع';
        showSuccessAlert(` فشل في إنشاء طلب التوريد: ${errorMessage}`);
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
                date: fetchedData.createdAt || fetchedData.requestDate || fetchedData.created_at,
                status: fetchedData.status,
                items: fetchedData.items || [],
                notes: fetchedData.notes || '',
                storekeeperNotes: fetchedData.storekeeperNotes || null,
                supplierNotes: fetchedData.supplierNotes || null,
                confirmationNotes: fetchedData.confirmationNotes || null,
                rejectionReason: fetchedData.rejectionReason || null,
                rejectedAt: fetchedData.rejectedAt || null,
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
        rejectionReason: fetchedData?.rejectionReason || shipment.details.rejectionReason || null,
        rejectedAt: fetchedData?.rejectedAt || shipment.details.rejectedAt || null,
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
            // أولوية: fulfilled_qty > fulfilledQty > receivedQuantity (فقط إذا لم يكن approved_qty)
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
        confirmationNotesSource: shipment.details.confirmationNotesSource || fetchedData?.confirmationNotesSource || null
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
                date: fetchedData.createdAt || fetchedData.requestDate || fetchedData.created_at || shipment.requestDate || shipment.created_at,
                created_at: fetchedData.created_at || shipment.created_at || shipment.requestDate,
                createdAt: fetchedData.createdAt || fetchedData.created_at || shipment.created_at || shipment.requestDate,
                requestDate: fetchedData.requestDate || shipment.requestDate,
                status: fetchedData.status,
                items: fetchedData.items || [],
                notes: fetchedData.notes || '',
                storekeeperNotes: fetchedData.storekeeperNotes || null,
                supplierNotes: fetchedData.supplierNotes || null,
                ...(fetchedData.confirmationDetails && {
                    confirmationDetails: fetchedData.confirmationDetails
                })
            };
        }
    } catch (err) {
        console.error('Error fetching shipment details:', err);
    }
    
    selectedShipmentForConfirmation.value = {
        ...shipment.details,
        items: (shipment.details.items || []).map(item => ({
            ...item,
            // تعيين الكميات للحقل المناسب في Modal
            originalQuantity: item.quantity || item.requestedQty || item.requested_qty || 0,
            availableQuantity: item.availableQuantity ?? item.stock ?? item.currentStock ?? 0,
            // تمرير البيانات الموجودة مسبقاً
            sentQuantity: item.fulfilled_qty || item.fulfilledQty || item.sentQuantity || item.quantity,
            batchNumber: item.batch_number || item.batchNumber || null,
            expiryDate: item.expiry_date || item.expiryDate || null
        }))
    }; 
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
        // إعداد بيانات الطلب للتوافق مع الباك إند
        const payload = {
            items: confirmationData.receivedItems || [],
            notes: confirmationData.notes
        };
        
        // استدعاء API لتأكيد الاستلام وإضافة الكميات لمخزون المورد
        const response = await api.post(`/supply-requests/${shipmentId}/confirm-receipt`, payload);
        const responseData = response.data?.data ?? response.data;
        
        const shipmentIndex = shipmentsData.value.findIndex(
            s => s.id === shipmentId
        );
        
        if (shipmentIndex !== -1) {
            // تحديث الحالة إلى approved بدلاً من تم الإستلام
            shipmentsData.value[shipmentIndex].requestStatus = 'approved';
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
        
        showSuccessAlert(` تم تأكيد الاستلام وإضافة الكميات لمخزون المورد بنجاح!`);
        closeConfirmationModal();
        
        // إعادة تحميل الشحنات لتحديث البيانات
        await fetchShipments();
        
    } catch (err) {
        const errorMessage = err.response?.data?.message || err.message || 'حدث خطأ غير متوقع';
        showSuccessAlert(` فشل في تأكيد الاستلام: ${errorMessage}`);
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

<p class="results-info">عدد النتائج: ${resultsCount}</p>

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
