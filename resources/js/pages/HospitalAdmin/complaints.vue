<template>
  <DefaultLayout>
    <main class="flex-1 p-4 sm:p-5 pt-3">
      <!-- رسائل التنبيه -->
      
      <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
        <div class="flex items-center gap-3 w-full sm:max-w-xl">
          <search v-model="searchTerm" placeholder="ابحث باسم المريض أو رقم الملف..." />

          <!-- قائمة الفرز -->
          <div class="dropdown dropdown-start">
            <div
              tabindex="0"
              role="button"
              class="inline-flex items-center px-4 py-2 border-2 border-[#ffffff8d] h-11 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
            >
              <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
              فرز
            </div>
            <ul
              tabindex="0"
              class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] border-[#ffffff8d] rounded-[35px] w-52 text-right"
            >
              <li class="menu-title text-gray-700 font-bold text-sm">
                حسب رقم الملف:
              </li>
              <li>
                <a
                  @click="sortPatients('fileNumber', 'asc')"
                  :class="{
                    'font-bold text-[#4DA1A9]':
                      sortKey === 'fileNumber' && sortOrder === 'asc',
                  }"
                >
                  الأصغر أولاً
                </a>
              </li>
              <li>
                <a
                  @click="sortPatients('fileNumber', 'desc')"
                  :class="{
                    'font-bold text-[#4DA1A9]':
                      sortKey === 'fileNumber' && sortOrder === 'desc',
                  }"
                >
                  الأكبر أولاً
                </a>
              </li>

              <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                حسب تاريخ الإنشاء:
              </li>
              <li>
                <a
                  @click="sortPatients('createdAt', 'asc')"
                  :class="{
                    'font-bold text-[#4DA1A9]':
                      sortKey === 'createdAt' && sortOrder === 'asc',
                  }"
                >
                  الأقدم أولاً
                </a>
              </li>
              <li>
                <a
                  @click="sortPatients('createdAt', 'desc')"
                  :class="{
                    'font-bold text-[#4DA1A9]':
                      sortKey === 'createdAt' && sortOrder === 'desc',
                  }"
                >
                  الأحدث أولاً
                </a>
              </li>

              <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                حسب حالة الطلب:
              </li>
              <li>
                <a
                  @click="sortPatients('status', 'asc')"
                  :class="{
                    'font-bold text-[#4DA1A9]': sortKey === 'status',
                  }"
                >
                  حسب الأبجدية
                </a>
              </li>
            </ul>
          </div>

          <p class="text-sm font-semibold text-gray-600 self-end sm:self-center">
            عدد النتائج :
            <span class="text-[#4DA1A9] text-lg font-bold">
              {{ filteredPatients.length }}
            </span>
          </p>
        </div>

        <div class="flex items-center gap-5 w-full sm:w-auto justify-end">
          <!-- زر تحديث البيانات -->
         
          
          <btnprint @click="printTable" />
        </div>
      </div>

      <!-- جدول البيانات -->
       <div class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col">
          <div class="overflow-y-auto flex-1" style="scrollbar-width: auto; scrollbar-color: grey transparent; direction: ltr;">
            <div class="overflow-x-auto h-full">
              <table dir="rtl" class="table w-full text-right min-w-[700px] border-collapse">
                <thead class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300">
                
                <tr>
                  <th class="file-number-col">رقم الملف</th>
                  <th class="patient-name-col">اسم المريض</th>
                  <th class="request-type-col">نوع الطلب</th>
                  <th class="content-col">المحتوى</th>
                  <th class="status-col">الحالة</th>
                  <th class="actions-col text-center">الإجراءات</th>
                </tr>
              </thead>

              <tbody class="text-gray-800">
                <tr v-if="isLoading">
                    <td colspan="6" class="p-4">
                        <TableSkeleton :rows="5" />
                    </td>
                </tr>
                <tr v-else-if="error">
                    <td colspan="6" class="py-12">
                        <ErrorState :message="error" :retry="fetchPatients" />
                    </td>
                </tr>
                <template v-else>
                  <tr
                    v-for="patient in filteredPatients"
                    :key="patient.id"
                    class="hover:bg-gray-50  border-b border-gray-100 transition-colors"
                  >
                    <td class="font-semibold text-gray-700 py-4">
                      {{ patient.fileNumber || 'غير محدد' }}
                    </td>
                    <td class="py-4">
                      {{ patient.patientName || 'غير محدد' }}
                    </td>
                    <td class="py-4">
                      <span :class="getRequestTypeClass(patient.requestType)">
                        {{ patient.requestType || 'غير محدد' }}
                      </span>
                    </td>
                    <td class="py-4 max-w-xs" :title="patient.content">
                      <div class="truncate">
                        {{ truncateContent(patient.content) }}
                      </div>
                    </td>
                    <td class="py-4">
                      <span :class="getStatusClass(patient.status)" 
                            class="px-3 py-1 rounded-full text-xs font-semibold inline-block">
                        {{ patient.status || 'غير محدد' }}
                      </span>
                    </td>
                    <td class="py-4">
                      <div class="flex gap-3 justify-center">
                        <!-- زر معاينة تفاصيل الملف -->
                        <button
                          @click="openPatientModal(patient)"
                          class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                          data-tip="معاينة تفاصيل الملف"
                          :disabled="isLoadingDetails"
                        >
                          <Icon
                            icon="famicons:open-outline"
                            class="w-4 h-4 text-green-600 hover:text-green-700 transition-colors"
                            :class="{ 'opacity-50': isLoadingDetails }"
                          />
                        </button>

                        <!-- زر الرد على الطلب -->
                        <button
                          @click="openResponseModal(patient)"
                          class="tooltip p-2 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-all duration-200 hover:scale-110 active:scale-95"
                          data-tip="الرد على الطلب"
                          :disabled="patient.status === 'تمت المراجعة' || isLoadingResponse"
                        >
                          <Icon
                            icon="streamline:mail-send-reply-all-email-message-reply-all-actions-action-arrow"
                            class="w-4 h-4 text-blue-600 hover:text-blue-700 transition-colors"
                            :class="{
                              'opacity-50 cursor-not-allowed': 
                                patient.status === 'تمت المراجعة' ||
                                isLoadingResponse
                            }"
                          />
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="filteredPatients.length === 0">
                    <td colspan="6" class="py-12">
                        <EmptyState message="لا توجد شكاوى متاحة" icon="tabler:inbox-off" />
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>

    <!-- مودال معاينة الملف -->
    <PatientDetailsModal
      :is-open="isPatientModalOpen"
      :patient-data="selectedPatient"
      @close="closePatientModal"
      :is-loading="isLoadingDetails"
    />

    <!-- مودال الرد على الطلب -->
    <RequestResponseModal
      :is-open="isResponseModalOpen"
      :request-data="selectedPatient"
      @close="closeResponseModal"
      @submit="handleRequestResponse"
      @reject="handleRequestRejection"
      :is-loading="isLoadingResponse"
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
import { ref, computed, onMounted } from 'vue';
import { Icon } from '@iconify/vue';
import axios from 'axios';

import DefaultLayout from '@/components/DefaultLayout.vue';
import search from '@/components/search.vue';
import btnprint from '@/components/btnprint.vue';
import PatientDetailsModal from '@/components/forhospitaladmin/PatientDetailsModal.vue';
import RequestResponseModal from '@/components/forhospitaladmin/RequestResponseModal.vue';
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
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

const endpoints = {
  requests: {
    getAll: () => api.get('/admin-hospital/requests'),
    getById: (id) => api.get(`/admin-hospital/requests/${id}`),
    respond: (id, data) => api.post(`/admin-hospital/requests/${id}/respond`, data),
    reject: (id, data) => api.post(`/admin-hospital/requests/${id}/reject`, data)
  }
};

// ----------------------------------------------------
// 2. حالة المكون
// ----------------------------------------------------
const patientsData = ref([]);
const isLoading = ref(true);
const isLoadingDetails = ref(false);
const isLoadingResponse = ref(false);
const error = ref(null);

// ----------------------------------------------------
// 3. البحث والفرز
// ----------------------------------------------------
const searchTerm = ref('');
const sortKey = ref('createdAt');
const sortOrder = ref('desc');

const sortPatients = (key, order) => {
  sortKey.value = key;
  sortOrder.value = order;
};

const filteredPatients = computed(() => {
  if (!patientsData.value || patientsData.value.length === 0) {
    return [];
  }

  let list = [...patientsData.value];

  // تطبيق البحث
  if (searchTerm.value.trim()) {
    const search = searchTerm.value.trim().toLowerCase();
    list = list.filter((patient) => {
      return (
        (patient.fileNumber && patient.fileNumber.toLowerCase().includes(search)) ||
        (patient.patientName && patient.patientName.toLowerCase().includes(search)) ||
        (patient.requestType && patient.requestType.includes(search)) ||
        (patient.content && patient.content.toLowerCase().includes(search)) ||
        (patient.status && patient.status.toLowerCase().includes(search))
      );
    });
  }

  // تطبيق الفرز
  if (sortKey.value) {
    list.sort((a, b) => {
      let valueA = a[sortKey.value];
      let valueB = b[sortKey.value];

      // التعامل مع القيم غير المعرفة
      if (!valueA) valueA = '';
      if (!valueB) valueB = '';

      let comparison = 0;

      if (sortKey.value === 'createdAt' || sortKey.value === 'updatedAt') {
        const dateA = new Date(valueA).getTime();
        const dateB = new Date(valueB).getTime();
        comparison = dateA - dateB;
      } else {
        // مقارنة نصية
        comparison = String(valueA).localeCompare(String(valueB), 'ar');
      }

      return sortOrder.value === 'asc' ? comparison : -comparison;
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
    return date.toLocaleDateString('ar-SA', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch {
    return dateString;
  }
};

const truncateContent = (content, maxLength = 50) => {
  if (!content || typeof content !== 'string') return '';
  return content.length > maxLength 
    ? content.substring(0, maxLength) + '...' 
    : content;
};

const getRequestTypeClass = (type) => {
  if (!type) return 'text-gray-600';
  
  const typeClasses = {
    'طلب دواء': 'text-blue-600 font-semibold',
    'طلب فحوصات': 'text-purple-600 font-semibold',
    'طلب استشارة': 'text-teal-600 font-semibold',
    'طلب موعد': 'text-orange-600 font-semibold',
    'استعلام': 'text-indigo-600 font-semibold'
  };

  return typeClasses[type] || 'text-gray-600';
};

const getStatusClass = (status) => {
  if (!status) return 'bg-gray-100 text-gray-700';
  
  const statusClasses = {
    'تمت المراجعة': 'bg-green-100 text-green-700',
    'قيد المراجعة': 'bg-yellow-100 text-yellow-700',
    'مقبول': 'bg-green-100 text-green-700',
    'معلق': 'bg-yellow-100 text-yellow-700',
    'مرفوض': 'bg-red-100 text-red-700',
    'ملغى': 'bg-red-100 text-red-700',
    'مكتمل': 'bg-blue-100 text-blue-700',
    'جديد': 'bg-gray-100 text-gray-700'
  };

  return statusClasses[status] || 'bg-gray-100 text-gray-700';
};

// ----------------------------------------------------
// 5. دوال جلب البيانات من API
// ----------------------------------------------------
const fetchPatients = async () => {
  isLoading.value = true;
  error.value = null;
  
  try {
    console.log('Fetching complaints from:', '/admin-hospital/requests');
    const response = await endpoints.requests.getAll();
    
    console.log('Raw API Response:', response);
    console.log('Response structure:', {
      hasData: !!response.data,
      isArray: Array.isArray(response.data),
      hasNestedData: !!(response.data?.data),
      hasSuccess: !!(response.data?.success)
    });
    
    // التحقق من بنية الاستجابة (نفس الطريقة المستخدمة في employeesList.vue)
    let data = [];
    if (response.data) {
      if (Array.isArray(response.data)) {
        // إذا كانت البيانات مصفوفة مباشرة
        data = response.data;
        console.log('Using direct array from response.data');
      } else if (response.data.data && Array.isArray(response.data.data)) {
        // إذا كانت البيانات في response.data.data (من sendSuccess)
        data = response.data.data;
        console.log('Using nested array from response.data.data');
      } else if (response.data.success && Array.isArray(response.data.data)) {
        // إذا كانت الاستجابة من sendSuccess
        data = response.data.data;
        console.log('Using data from sendSuccess response');
      }
    }
    
    patientsData.value = data;
    
    if (patientsData.value.length === 0) {
      console.log('لا توجد بيانات متاحة');
    } else {
      console.log('✅ تم جلب', patientsData.value.length, 'شكوى بنجاح');
      showSuccessAlert('✅ تم تحميل ' + patientsData.value.length + ' شكوى بنجاح');
    }
  } catch (err) {
    error.value = err.message || 'فشل في جلب البيانات من الخادم';
    console.error('❌ Error fetching complaints:', err);
    console.error('Error details:', {
      message: err.message,
      response: err.response?.data,
      status: err.response?.status,
      url: err.config?.url
    });
    patientsData.value = [];
    const errorMessage = err.response?.data?.message || err.message || 'فشل في جلب قائمة الشكاوى.';
    showSuccessAlert('❌ ' + errorMessage, 'error');
  } finally {
    isLoading.value = false;
  }
};

const fetchPatientDetails = async (patientId) => {
  isLoadingDetails.value = true;
  
  try {
    const response = await endpoints.requests.getById(patientId);
    
    // التحقق من بنية الاستجابة (نفس الطريقة المستخدمة في employeesList.vue)
    let data = null;
    if (response.data) {
      if (response.data.data) {
        data = response.data.data;
      } else if (response.data.success && response.data.data) {
        data = response.data.data;
      } else {
        data = response.data;
      }
    }
    
    return data;
  } catch (err) {
    console.error('Error fetching complaint details:', err);
    const errorMsg = err.response?.data?.message || err.message || 'فشل في جلب تفاصيل الشكوى';
    showSuccessAlert('❌ ' + errorMsg, 'error');
    return null;
  } finally {
    isLoadingDetails.value = false;
  }
};

// ----------------------------------------------------
// 6. المودالات
// ----------------------------------------------------
const isPatientModalOpen = ref(false);
const isResponseModalOpen = ref(false);
const selectedPatient = ref(null);

const openPatientModal = async (patient) => {
  selectedPatient.value = patient;
  isPatientModalOpen.value = true;
  
  // جلب التفاصيل الكاملة إذا كانت غير موجودة
  if (!patient.fullDetails && patient.id) {
    const details = await fetchPatientDetails(patient.id);
    if (details) {
      selectedPatient.value = { ...patient, ...details };
    }
  }
};

const closePatientModal = () => {
  isPatientModalOpen.value = false;
  selectedPatient.value = null;
};

const openResponseModal = (patient) => {
  // التحقق من حالة المريض قبل فتح مودال الرد
  if (patient.status === 'تمت المراجعة') {
    showSuccessAlert('لا يمكن الرد على هذه الشكوى لأنها ' + patient.status, 'warning');
    return;
  }
  
  selectedPatient.value = patient;
  isResponseModalOpen.value = true;
};

const closeResponseModal = () => {
  isResponseModalOpen.value = false;
  selectedPatient.value = null;
};

// ----------------------------------------------------
// 7. معالجة الردود والرفض
// ----------------------------------------------------
const handleRequestResponse = async (responseData) => {
  isLoadingResponse.value = true;
  
  try {
    const complaintId = selectedPatient.value.id;
    
    // إرسال الرد إلى API
    const apiData = {
      response: responseData.response,
      notes: responseData.notes || null,
    };
    
    const result = await endpoints.requests.respond(complaintId, apiData);
    
    // تحديث البيانات المحلية
    const complaintIndex = patientsData.value.findIndex(p => p.id === complaintId);
    if (complaintIndex !== -1) {
      const responseData_result = result.data?.data || result.data || result;
      patientsData.value[complaintIndex] = {
        ...patientsData.value[complaintIndex],
        status: 'تمت المراجعة',
        reply: responseData.response,
        repliedAt: new Date().toISOString()
      };
    }
    
    // إعادة جلب البيانات للتأكد من التحديث
    await fetchPatients();
    
    const successMessage = result.data?.message || '✅ تم إرسال الرد بنجاح';
    showSuccessAlert(successMessage);
    closeResponseModal();
  } catch (err) {
    const errorMsg = err.response?.data?.message || err.message || '❌ فشل في إرسال الرد';
    showSuccessAlert('❌ ' + errorMsg, 'error');
  } finally {
    isLoadingResponse.value = false;
  }
};

const handleRequestRejection = async (responseData) => {
  isLoadingResponse.value = true;
  
  try {
    const complaintId = selectedPatient.value.id;
    
    // إرسال سبب الرفض إلى API
    const apiData = {
      rejectionReason: responseData.rejectionReason,
      notes: responseData.notes || null,
    };
    
    const result = await endpoints.requests.reject(complaintId, apiData);
    
    // تحديث البيانات المحلية
    const complaintIndex = patientsData.value.findIndex(p => p.id === complaintId);
    if (complaintIndex !== -1) {
      patientsData.value[complaintIndex] = {
        ...patientsData.value[complaintIndex],
        status: 'تمت المراجعة',
        reply: responseData.rejectionReason,
        repliedAt: new Date().toISOString()
      };
    }
    
    // إعادة جلب البيانات للتأكد من التحديث
    await fetchPatients();
    
    const successMessage = result.data?.message || '✅ تم رفض الشكوى بنجاح';
    showSuccessAlert(successMessage);
    closeResponseModal();
  } catch (err) {
    const errorMsg = err.response?.data?.message || err.message || '❌ فشل في رفض الشكوى';
    showSuccessAlert('❌ ' + errorMsg, 'error');
  } finally {
    isLoadingResponse.value = false;
  }
};

// ----------------------------------------------------
// 8. الطباعة
// ----------------------------------------------------
const printTable = () => {
  if (filteredPatients.value.length === 0) {
    showSuccessAlert('⚠️ لا توجد بيانات للطباعة', 'warning');
    return;
  }
  
  const printWindow = window.open('', '_blank', 'height=600,width=800');
  
  if (!printWindow) {
    showSuccessAlert('❌ فشل في فتح نافذة الطباعة. يرجى السماح بالنوافذ المنبثقة', 'error');
    return;
  }
  
  const printDate = new Date().toLocaleDateString('ar-SA');
  const resultsCount = filteredPatients.value.length;
  
  let tableHtml = `
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
      <meta charset="UTF-8">
      <title>تقرير ملفات المرضى</title>
      <style>
        body {
          font-family: 'Arial', sans-serif;
          direction: rtl;
          padding: 20px;
          line-height: 1.6;
        }
        .header {
          text-align: center;
          margin-bottom: 30px;
          border-bottom: 2px solid #4DA1A9;
          padding-bottom: 15px;
        }
        h1 {
          color: #2E5077;
          margin: 0;
        }
        .print-info {
          display: flex;
          justify-content: space-between;
          margin-bottom: 20px;
          font-size: 14px;
          color: #666;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
          font-size: 12px;
        }
        th {
          background-color: #9aced2;
          color: #000;
          padding: 10px;
          border: 1px solid #ccc;
          text-align: right;
          font-weight: bold;
        }
        td {
          padding: 8px;
          border: 1px solid #ccc;
          text-align: right;
        }
        tr:nth-child(even) {
          background-color: #f9f9f9;
        }
        .status-completed { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .status-rejected { color: red; font-weight: bold; }
        .footer {
          margin-top: 30px;
          padding-top: 15px;
          border-top: 1px solid #ddd;
          font-size: 12px;
          color: #666;
          text-align: left;
        }
        @media print {
          body { padding: 10px; }
          .no-print { display: none; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>تقرير ملفات المرضى</h1>
      </div>
      
      <div class="print-info">
        <div>تاريخ الطباعة: ${printDate}</div>
        <div>عدد السجلات: ${resultsCount}</div>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>رقم الملف</th>
            <th>اسم المريض</th>
            <th>نوع الطلب</th>
            <th>المحتوى</th>
            <th>الحالة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
  `;
  
  filteredPatients.value.forEach((patient) => {
    const statusClass = patient.status === 'تمت المراجعة' ? 'status-completed' : 
                       'status-pending';
    
    tableHtml += `
      <tr>
        <td>${patient.fileNumber || '-'}</td>
        <td>${patient.patientName || '-'}</td>
        <td>${patient.requestType || '-'}</td>
        <td>${patient.content ? patient.content.substring(0, 100) + (patient.content.length > 100 ? '...' : '') : '-'}</td>
        <td class="${statusClass}">${patient.status || '-'}</td>
        <td>${formatDate(patient.createdAt)}</td>
      </tr>
    `;
  });
  
  tableHtml += `
        </tbody>
      </table>
      
      <div class="footer">
        <p>تم إنشاء التقرير تلقائياً من نظام إدارة المستشفى</p>
      </div>
      
      <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #4DA1A9; color: white; border: none; border-radius: 5px; cursor: pointer;">
          طباعة التقرير
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
          إغلاق
        </button>
      </div>
    </body>
    </html>
  `;
  
  printWindow.document.open();
  printWindow.document.write(tableHtml);
  printWindow.document.close();
  
  showSuccessAlert('✅ تم تجهيز التقرير للطباعة');
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

const showSuccessAlert = (message, type = "success") => showAlert(message, type);
const showErrorAlert = (message) => showAlert(message, "error");
const showWarningAlert = (message) => showAlert(message, "warning");
const showInfoAlert = (message) => showAlert(message, "info");

// ----------------------------------------------------
// 10. دورة الحياة
// ----------------------------------------------------
onMounted(() => {
  fetchPatients();
  
  // تحديث البيانات كل 30 ثانية (اختياري)
  // setInterval(fetchPatients, 30000);
});
</script>

<style scoped>
/* تخصيص شريط التمرير */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: #4da1a9;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: #3a8c94;
}

/* أنماط الجدول */
.file-number-col {
  width: 120px;
  min-width: 120px;
}

.patient-name-col {
  width: 180px;
  min-width: 180px;
}

.request-type-col {
  width: 140px;
  min-width: 140px;
}

.content-col {
  width: 250px;
  min-width: 250px;
}

.status-col {
  width: 120px;
  min-width: 120px;
}

.actions-col {
  width: 150px;
  min-width: 150px;
  text-align: center;
}

/* حركة التحميل */
.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* تحسين مظهر الصفوف */
tr:hover {
  background-color: #f8fafc !important;
}

/* تحسين التباعد */
td {
  padding: 12px 16px !important;
}

th {
  padding: 14px 16px !important;
}
</style>