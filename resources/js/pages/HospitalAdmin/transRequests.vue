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
                                    @click="sortRequests('createdAt', 'asc')"
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
                                    @click="sortRequests('createdAt', 'desc')"
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
                                    @click="sortRequests('status', 'asc')"
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

            <!-- حالة التحميل -->
           

            <!-- رسالة عدم وجود بيانات -->
            

            <!-- عرض البيانات  -->
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
                                    <th class="date-col">
                                        تاريخ الطلب
                                    </th>
                                    <th class="status-col">
                                        الحالة
                                    </th>
                                    <th class="actions-col text-center">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="text-gray-800">
                                <tr v-if="isLoading">
                                    <td colspan="7" class="p-4">
                                        <TableSkeleton :rows="5" />
                                    </td>
                                </tr>
                                <tr v-else-if="error">
                                    <td colspan="7" class="py-12">
                                        <ErrorState :message="error" :retry="fetchTransferRequests" />
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="(request, index) in filteredRequests"
                                        :key="request.id || index"
                                        class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                    >
                                        <td class="font-semibold text-gray-700">
                                            {{ request.requestNumber || `TR-${request.id}` }}
                                        </td>
                                        <td>
                                            {{ request.patient?.name || 'غير محدد' }}
                                        </td>
                                        <td>
                                            <span :class="getHospitalClass(request.fromHospital)">
                                                {{ getHospitalName(request.fromHospital) }}
                                            </span>
                                        </td>
                                        <td class="max-w-xs truncate" :title="request.reason || request.transferReason">
                                            {{ truncateContent(request.reason || request.transferReason) }}
                                        </td>
                                        <td class="date-col">
                                            {{ formatDate(request.createdAt || request.requestDate || request.created_at) }}
                                        </td>
                                        <td class="status-col">
                                            <span
                                                :class="[
                                                    'px-3 py-1 rounded-full text-xs font-bold inline-block',
                                                    getStatusClass(request.status || request.requestStatus)
                                                ]"
                                            >
                                                {{ getStatusText(request.status || request.requestStatus) }}
                                            </span>
                                        </td>
                                        <td class="actions-col">
                                            <div class="flex gap-3 justify-center">
                                                <!-- زر معاينة تفاصيل الطلب -->
                                                <button 
                                                    @click="openRequestModal(request)"
                                                    class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95" 
                                                    data-tip="معاينة تفاصيل الطلب">
                                                    <Icon
                                                        icon="famicons:open-outline"
                                                        class="w-4 h-4 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                    />
                                                </button>
                                                
                                                <!-- عرض علامة الحالة أو زر الرد -->
                                                <template v-if="!canRespondToRequest(request)">
                                                    <!-- عرض علامة صح عند القبول -->
                                                    <div
                                                        v-if="isApproved(request)"
                                                        class="tooltip p-2 rounded-lg bg-green-50 border border-green-200"
                                                        data-tip="تم قبول طلب النقل"
                                                    >
                                                        <Icon
                                                            icon="solar:check-circle-bold"
                                                            class="w-5 h-5 text-green-600"
                                                        />
                                                    </div>
                                                    <!-- عرض علامة خطأ عند الرفض -->
                                                    <div
                                                        v-else-if="isRejected(request)"
                                                        class="tooltip p-2 rounded-lg bg-red-50 border border-red-200"
                                                        data-tip="تم رفض طلب النقل"
                                                    >
                                                        <Icon
                                                            icon="solar:close-circle-bold"
                                                            class="w-5 h-5 text-red-600"
                                                        />
                                                    </div>
                                                </template>
                                                
                                                <!-- زر الرد على الطلب (فقط عند قيد المراجعة) -->
                                                <button 
                                                    v-else
                                                    @click="openResponseModal(request)"
                                                    class="tooltip p-2 rounded-lg bg-yellow-50 hover:bg-yellow-100 border border-yellow-200 transition-all duration-200 hover:scale-110 active:scale-95" 
                                                    data-tip="الرد على طلب النقل">
                                                    <Icon
                                                        icon="streamline:mail-send-reply-all-email-message-reply-all-actions-action-arrow" 
                                                        class="w-4 h-4 text-yellow-600 cursor-pointer hover:scale-110 transition-transform"
                                                    />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredRequests.length === 0">
                                        <td colspan="7" class="py-12">
                                            <EmptyState message="لا توجد طلبات نقل متاحة" />
                                        </td>
                                    </tr>
                                </template>
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
            :is-loading="isLoadingResponse"
            @close="closeResponseModal"
            @submit="handleTransferResponse"
            @reject="handleTransferResponse"
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

import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import TransferRequestDetailsModal from "@/components/forhospitaladmin/TransferRequestDetailsModal.vue";
import TransferResponseModal from "@/components/forhospitaladmin/TransferResponseModal.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. إعدادات API
// ----------------------------------------------------
const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// إضافة interceptor لإضافة الـ token تلقائياً
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

// إضافة interceptor للتعامل مع الأخطاء (نفس الطريقة المستخدمة في employeesList.vue)
api.interceptors.response.use(
  (response) => response, // إرجاع response كاملاً بدون تعديل
  (error) => {
    if (error.response?.status === 401) {
      const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
      console.error('Unauthenticated - Token exists:', !!token);
      if (token) {
        console.error('Token value (first 20 chars):', token.substring(0, 20) + '...');
      } else {
        console.error('No token found. Please login again.');
      }
    }
    return Promise.reject(error);
  }
);

// ----------------------------------------------------
// 2. حالة المكون
// ----------------------------------------------------
const transferRequests = ref([]);
const isLoading = ref(true);
const error = ref(null);

// ----------------------------------------------------
// 3. البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);
const sortKey = ref("createdAt");
const sortOrder = ref("desc");

const sortRequests = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

// دالة تحويل التاريخ من صيغة مختلفة إلى Date
const parseDate = (dateString) => {
    if (!dateString) return null;
    try {
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

const filteredRequests = computed(() => {
    let list = transferRequests.value;
    
    // 1. التصفية حسب البحث
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (request) =>
                (request.requestNumber?.toLowerCase().includes(search) || 
                 `TR-${request.id}`.toLowerCase().includes(search)) ||
                (request.patient?.name?.toLowerCase().includes(search) || 
                 request.patientName?.toLowerCase().includes(search)) ||
                (request.fromHospital?.name?.toLowerCase().includes(search) || 
                 request.fromHospital?.toLowerCase().includes(search)) ||
                (request.reason?.toLowerCase().includes(search) || 
                 request.transferReason?.toLowerCase().includes(search))
        );
    }

    // 2. فلترة حسب التاريخ
    if (dateFrom.value || dateTo.value) {
        list = list.filter((request) => {
            const requestDate = request.createdAt || request.requestDate || request.created_at;
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

    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "requestNumber") {
                const aNum = a.requestNumber || `TR-${a.id}`;
                const bNum = b.requestNumber || `TR-${b.id}`;
                comparison = aNum.localeCompare(bNum);
            } else if (sortKey.value === "createdAt") {
                const dateA = new Date(a.createdAt || a.requestDate);
                const dateB = new Date(b.createdAt || b.requestDate);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "fromHospital") {
                const aHospital = getHospitalName(a.fromHospital);
                const bHospital = getHospitalName(b.fromHospital);
                comparison = aHospital.localeCompare(bHospital, "ar");
            } else if (sortKey.value === "status") {
                comparison = getStatusText(a.status || a.requestStatus).localeCompare(
                    getStatusText(b.status || b.requestStatus), "ar"
                );
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
        return date.toLocaleDateString( {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
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

const getHospitalName = (hospitalData) => {
    if (!hospitalData) return 'غير محدد';
    return typeof hospitalData === 'object' ? hospitalData.name : hospitalData;
};

const getHospitalClass = (hospitalData) => {
    const hospitalName = getHospitalName(hospitalData);
    switch (hospitalName) {
        case 'مستشفى الخضراء':
            return 'text-blue-600 font-semibold bg-blue-50 px-2 py-1 rounded';
        case 'مستشفى طرابلس الجامعي':
            return 'text-purple-600 font-semibold bg-purple-50 px-2 py-1 rounded';
        default:
            return 'text-gray-600';
    }
};

const getStatusText = (status) => {
    if (!status) return 'غير محدد';
    
    const statusLower = String(status).toLowerCase();
    
    if (statusLower === 'approved' || statusLower.includes('قبول') || statusLower.includes('رد')) {
        return 'تم القبول';
    }
    if (statusLower === 'preapproved' || statusLower.includes('الموافقة الأولية')) {
        return 'تمت الموافقة الأولية';
    }
    if (statusLower === 'rejected' || statusLower.includes('رفض') || statusLower.includes('مرفوض')) {
        return 'مرفوض';
    }
    if (statusLower === 'pending' || statusLower.includes('مراجعة') || statusLower.includes('قيد')) {
        return 'قيد المراجعة';
    }
    
    return status || 'غير محدد';
};

const getStatusIcon = (status) => {
    if (!status) return 'solar:checklist-minimalistic-bold-duotone';
    
    const statusLower = String(status).toLowerCase();
    
    if (statusLower === 'approved' || statusLower.includes('قبول') || statusLower.includes('رد')) {
        return 'solar:check-circle-bold';
    }
    if (statusLower === 'preapproved' || statusLower.includes('الموافقة الأولية')) {
        return 'solar:check-circle-bold-duotone';
    }
    if (statusLower === 'rejected' || statusLower.includes('رفض') || statusLower.includes('مرفوض')) {
        return 'solar:close-circle-bold';
    }
    if (statusLower === 'pending' || statusLower.includes('مراجعة') || statusLower.includes('قيد')) {
        return 'solar:clock-circle-bold';
    }
    
    return 'solar:checklist-minimalistic-bold-duotone';
};

const getStatusIconClass = (status) => {
    if (!status) return 'text-[#4DA1A9]';
    
    const statusLower = String(status).toLowerCase();
    
    if (statusLower === 'approved' || statusLower.includes('قبول') || statusLower.includes('رد')) {
        return 'text-green-600';
    }
    if (statusLower === 'preapproved' || statusLower.includes('الموافقة الأولية')) {
        return 'text-blue-600';
    }
    if (statusLower === 'rejected' || statusLower.includes('رفض') || statusLower.includes('مرفوض')) {
        return 'text-red-600';
    }
    if (statusLower === 'pending' || statusLower.includes('مراجعة') || statusLower.includes('قيد')) {
        return 'text-yellow-600';
    }
    
    return 'text-[#4DA1A9]';
};

const getStatusClass = (status) => {
    if (!status) return 'text-gray-600 font-semibold';
    
    const statusText = getStatusText(status);
    
    const statusClasses = {
        'تم القبول': 'text-green-600 font-semibold',
        'تم الرد': 'text-green-600 font-semibold',
        'قيد المراجعة': 'text-yellow-600 font-semibold',
        'مرفوض': 'text-red-600 font-semibold',
    };
    
    return statusClasses[statusText] || 'text-gray-600 font-semibold';
};

const canRespondToRequest = (request) => {
    const status = request.status || request.requestStatus;
    if (!status) return true;
    
    const statusLower = String(status).toLowerCase();
    return statusLower === 'pending' || 
           statusLower === 'preapproved' || 
           statusLower.includes('مراجعة') || 
           statusLower.includes('قيد') ||
           statusLower.includes('الموافقة الأولية');
};

const isApproved = (request) => {
    const status = request.status || request.requestStatus;
    if (!status) return false;
    
    const statusLower = String(status).toLowerCase();
    return statusLower === 'approved' || statusLower.includes('قبول') || statusLower.includes('رد');
};

const isRejected = (request) => {
    const status = request.status || request.requestStatus;
    if (!status) return false;
    
    const statusLower = String(status).toLowerCase();
    return statusLower === 'rejected' || statusLower.includes('رفض') || statusLower.includes('مرفوض');
};

// ----------------------------------------------------
// 5. دوال API
// ----------------------------------------------------
const fetchTransferRequests = async () => {
    try {
        isLoading.value = true;
        error.value = null;
        
        console.log('Fetching transfer requests from:', '/admin-hospital/transfer-requests');
        const response = await api.get('/admin-hospital/transfer-requests');
        
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
        
        // التحقق من بنية الاستجابة (نفس الطريقة المستخدمة في employeesList.vue)
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
        transferRequests.value = data;
        
        if (transferRequests.value.length === 0) {
            console.log('لا توجد بيانات متاحة');
        } else {
            console.log('✅ تم جلب', transferRequests.value.length, 'طلب نقل بنجاح');
            showSuccessAlert('✅ تم تحميل ' + transferRequests.value.length + ' طلب نقل بنجاح');
        }
    } catch (err) {
        console.error('❌ Error fetching transfer requests:', err);
        console.error('Error details:', {
            message: err.message,
            response: err.response?.data,
            status: err.response?.status,
            url: err.config?.url
        });
        error.value = err.message || 'فشل في جلب البيانات من الخادم';
        transferRequests.value = [];
        const errorMessage = err.response?.data?.message || err.message || 'فشل في جلب قائمة طلبات النقل.';
        showErrorAlert('❌ ' + errorMessage);
    } finally {
        isLoading.value = false;
    }
};

const updateRequestStatus = async (requestId, statusData) => {
    try {
        console.log('Updating transfer request status:', requestId, statusData);
        const response = await api.put(`/admin-hospital/transfer-requests/${requestId}/status`, statusData);
        
        console.log('Update response:', response);
        
        // التحقق من بنية الاستجابة
        const successMessage = response.data?.message || 
                              (response.data?.success ? 'تم تحديث حالة الطلب بنجاح' : null);
        
        if (successMessage) {
            return {
                success: true,
                message: successMessage,
                status: response.data?.status || statusData.status
            };
        }
        
        return {
            success: true,
            message: 'تم تحديث حالة الطلب بنجاح',
            status: statusData.status
        };
    } catch (err) {
        console.error('❌ Error updating request status:', err);
        console.error('Error details:', {
            message: err.message,
            response: err.response?.data,
            status: err.response?.status
        });
        const errorMessage = err.response?.data?.message || err.message || 'فشل في تحديث حالة الطلب';
        showErrorAlert('❌ ' + errorMessage);
        return {
            success: false,
            message: errorMessage
        };
    }
};

// ----------------------------------------------------
// 6. المودالات
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
    if (!canRespondToRequest(request)) return;
    selectedRequest.value = request;
    isResponseModalOpen.value = true;
};

const closeResponseModal = () => {
    isResponseModalOpen.value = false;
    selectedRequest.value = null;
};

const handleTransferResponse = async (responseData) => {
    try {
        const requestId = selectedRequest.value.id;
        
        // تحضير البيانات للإرسال حسب ما يتوقعه الـ API
        const apiData = {
            status: responseData.status === 'approved' ? 'approved' : 'rejected',
            response: responseData.response || null,
            notes: responseData.notes || null,
            rejectionReason: responseData.rejectionReason || responseData.response || null,
        };
        
        console.log('Sending transfer response:', apiData);
        
        // تحديث الطلب في الـ API
        const result = await updateRequestStatus(requestId, apiData);
        
        if (result.success) {
            // إعادة جلب البيانات للتأكد من التحديث
            await fetchTransferRequests();
            
            const successMsg = result.message || 
                              `✅ تم ${apiData.status === 'rejected' ? 'رفض' : 'قبول'} طلب النقل بنجاح`;
            showSuccessAlert(successMsg);
            closeResponseModal();
        }
    } catch (err) {
        console.error('Error handling transfer response:', err);
        const errorMsg = err.response?.data?.message || err.message || '❌ فشل في حفظ الرد';
        showErrorAlert(errorMsg);
    }
};

// ----------------------------------------------------
// 7. الطباعة
// ----------------------------------------------------
const printTable = () => {
    if (filteredRequests.value.length === 0) {
        showErrorAlert("لا توجد بيانات للطباعة");
        return;
    }

    const resultsCount = filteredRequests.value.length;
    const printWindow = window.open("", "_blank", "height=600,width=800");

    if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
        showErrorAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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
    <th>تاريخ الطلب</th>
    <th>الحالة</th>
    </tr>
</thead>
<tbody>
`;

    filteredRequests.value.forEach((request) => {
        const status = getStatusText(request.status || request.requestStatus);
        const statusClass = status.includes('مرفوض') ? 'status-rejected' :
                          status.includes('قبول') || status.includes('رد') ? 'status-approved' : 
                          'status-pending';
        
        tableHtml += `
<tr>
    <td>${request.requestNumber || `TR-${request.id}`}</td>
    <td>${request.patient?.name || request.patientName || 'غير محدد'}</td>
    <td>${getHospitalName(request.fromHospital)}</td>
    <td>${request.reason || request.transferReason || 'غير محدد'}</td>
    <td>${formatDate(request.createdAt || request.requestDate || request.created_at)}</td>
    <td class="${statusClass}">${status}</td>
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
// 8. نظام التنبيهات المطور (Toast System)
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
// 9. دورة الحياة
// ----------------------------------------------------
onMounted(() => {
    fetchTransferRequests();
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
.date-col {
    width: 140px;
    min-width: 140px;
}
.status-col {
    width: 150px;
    min-width: 150px;
    text-align: center;
}
.actions-col {
    width: 150px;
    min-width: 150px;
    text-align: center;  
}
</style>