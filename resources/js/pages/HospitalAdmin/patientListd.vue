<script setup>
// عدل openViewModalوfetchPatientDetails ,openDispensationModal  ,fetchDispensationHistory  ,closeDispensationModal
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

// استيراد المكونات المنفصلة
import PatientViewModal from "@/components/forhospitaladmin/PatientViewModal.vue";
import DispensationModal from "@/components/forhospitaladmin/DispensationModal.vue";
import DefaultLayout from "@/components/DefaultLayout.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

// ----------------------------------------------------
// 0. نظام التنبيهات - يجب تعريفه قبل الاستخدام
// ----------------------------------------------------
const isSuccessAlertVisible = ref(false);
const isInfoAlertVisible = ref(false);
const successMessage = ref("");
const infoMessage = ref("");
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

const showInfoAlert = (message) => {
    if (alertTimeout) {
        clearTimeout(alertTimeout);
    }
    infoMessage.value = message;
    isInfoAlertVisible.value = true;
    alertTimeout = setTimeout(() => {
        isInfoAlertVisible.value = false;
        infoMessage.value = "";
    }, 4000);
};

// ----------------------------------------------------
// 1. تكوين Axios
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
  (response) => {
    return response.data;
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

// ----------------------------------------------------
// 2. بيانات المرضى - تأتي من API فقط
// ----------------------------------------------------
const patients = ref([]);
const isLoading = ref(true);
const error = ref(null);
const hasError = ref(false);
const errorMessage = ref("");

// ----------------------------------------------------
// 3. دوال API
// ----------------------------------------------------
// جلب جميع المرضى
const fetchPatients = async () => {
  isLoading.value = true;
  hasError.value = false;
  error.value = null;
  errorMessage.value = "";
  
  try {
    const response = await api.get('/admin-hospital/patients');
    // The interceptor returns response.data, so response is already the data object
    // Handle both wrapped (data.data) and direct array formats
    const data = response?.data || response || [];
    patients.value = (Array.isArray(data) ? data : []).map(patient => ({
      ...patient,
      lastUpdated: patient.lastUpdated || new Date().toISOString(),
      // إضافة خصائص العرض إذا لم تكن موجودة
      nameDisplay: patient.name || '',
      nationalIdDisplay: patient.nationalId || '',
      birthDisplay: patient.birth ? formatDateForDisplay(patient.birth) : ''
    }));
    showSuccessAlert(`✅ تم تحميل ${patients.value.length} مريض بنجاح`);
  } catch (err) {
    hasError.value = true;
    if (err.response) {
      switch (err.response.status) {
        case 401:
          errorMessage.value = "غير مصرح لك بالوصول. يرجى تسجيل الدخول مرة أخرى.";
          break;
        case 403:
          errorMessage.value = "ليس لديك صلاحية لعرض هذه البيانات.";
          break;
        case 404:
          errorMessage.value = "لم يتم العثور على بيانات المرضى.";
          break;
        case 500:
          errorMessage.value = "خطأ في الخادم. يرجى المحاولة لاحقًا.";
          break;
        default:
          errorMessage.value = `خطأ في جلب البيانات: ${err.message}`;
      }
    } else if (err.request) {
      errorMessage.value = "تعذر الاتصال بالخادم. يرجى التحقق من اتصالك بالإنترنت.";
      showInfoAlert('لا يمكن الاتصال بالخادم. سيتم عرض الجدول فارغًا.');
    } else {
      errorMessage.value = "حدث خطأ غير متوقع.";
    }
    
    error.value = errorMessage.value; // Sync for ErrorState component

    if (!err.response || (err.response.status !== 401 && err.response.status !== 403)) {
      showSuccessAlert(`❌ فشل في تحميل المرضى: ${errorMessage.value}`);
    }
  } finally {
    isLoading.value = false;
  }
};

// تنسيق التاريخ للعرض
const formatDateForDisplay = (dateString) => {
  if (!dateString) return '';
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    
    return date.toLocaleDateString( {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    });
  } catch {
    return dateString;
  }
};

// جلب بيانات مريض محدد
const fetchPatientDetails = async (patientId) => {
  try {
    const response = await api.get(`/admin-hospital/patients/${patientId}`);
    // The interceptor returns response.data, so response is already the data object
    const patientData = response?.data || response;
    
    // تنسيق البيانات للعرض
    return {
      ...patientData,
      nameDisplay: patientData.name || '',
      nationalIdDisplay: patientData.nationalId || '',
      birthDisplay: patientData.birth ? formatDateForDisplay(patientData.birth) : '',
      medications: patientData.medications || []
    };
  } catch (err) {
    console.error('فشل جلب تفاصيل المريض:', err);
    throw err;
  }
};

// تحديث بيانات المريض (بعد إضافة/تعديل/حذف دواء)
const updatePatientMedications = async (patientId, medications) => {
  try {
    const response = await api.put(`/admin-hospital/patients/${patientId}/medications`, {
      medications
    });
    // The interceptor returns response.data, so response is already the data object
    return response?.data || response;
  } catch (err) {
    console.error('فشل تحديث الأدوية:', err);
    throw err;
  }
};

// جلب سجل الصرف
const fetchDispensationHistory = async (patientId) => {
  try {
    const response = await api.get(`/admin-hospital/patients/${patientId}/dispensation-history`);
    // The interceptor returns response.data, so response is already the data object
    const data = response?.data || response || [];
    return Array.isArray(data) ? data : [];
  } catch (err) {
    console.error('فشل جلب سجل الصرف:', err);
    throw err;
  }
};

// ----------------------------------------------------
// 4. منطق البحث والفرز الموحد
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref('lastUpdated');
const sortOrder = ref('desc');

const calculateAge = (birthDateString) => {
    if (!birthDateString) return 0;
    try {
      const date = new Date(birthDateString);
      if (isNaN(date.getTime())) return 0;
      
      const today = new Date();
      let age = today.getFullYear() - date.getFullYear();
      const m = today.getMonth() - date.getMonth();

      if (m < 0 || (m === 0 && today.getDate() < date.getDate())) {
          age--;
      }
      return age;
    } catch {
      return 0;
    }
};

const sortPatients = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredPatients = computed(() => {
    let list = patients.value;
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(patient =>
            (patient.fileNumber && patient.fileNumber.toString().includes(search)) ||
            (patient.name && patient.name.toLowerCase().includes(search)) ||
            (patient.nationalId && patient.nationalId.includes(search)) ||
            (patient.birth && patient.birth.includes(search)) ||
            (patient.phone && patient.phone.includes(search))
        );
    }

    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === 'name') {
                comparison = (a.name || '').localeCompare(b.name || '', 'ar');
            } else if (sortKey.value === 'birth') {
                const ageA = calculateAge(a.birth);
                const ageB = calculateAge(b.birth);
                comparison = ageA - ageB;
            } else if (sortKey.value === 'lastUpdated') {
                const dateA = new Date(a.lastUpdated || 0);
                const dateB = new Date(b.lastUpdated || 0);
                comparison = dateA.getTime() - dateB.getTime();
            }

            return sortOrder.value === 'asc' ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 5. منطق الرسائل التنبيهية (تم نقله للأعلى)
// ----------------------------------------------------

// ----------------------------------------------------
// 6. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isDispensationModalOpen = ref(false);
const selectedPatient = ref({});
const dispensationHistory = ref([]);

// ----------------------------------------------------
// 7. دوال فتح وإغلاق الـ Modals
// ----------------------------------------------------
const openViewModal = async (patient) => {
  try {
    // جلب البيانات المحدثة للمريض من API
    const patientData = await fetchPatientDetails(patient.fileNumber || patient.id);
    selectedPatient.value = patientData;
    isViewModalOpen.value = true;
  } catch (err) {
    console.error('فشل فتح نموذج العرض:', err);
    // استخدام بيانات المريض من القائمة إذا فشل جلب البيانات
    selectedPatient.value = {
      ...patient,
      nameDisplay: patient.name || '',
      nationalIdDisplay: patient.nationalId || '',
      birthDisplay: patient.birth ? formatDateForDisplay(patient.birth) : '',
      medications: patient.medications || []
    };
    isViewModalOpen.value = true;
    showInfoAlert('تم فتح النموذج ببيانات محدودة. قد لا تكون جميع المعلومات متاحة.');
  }
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedPatient.value = {};
};

const closeDispensationModal = () => {
    isDispensationModalOpen.value = false;
    isViewModalOpen.value = true;
};

const openDispensationModal = async () => {
  try {
    // جلب سجل الصرف من API
    const history = await fetchDispensationHistory(selectedPatient.value.fileNumber || selectedPatient.value.id);
    dispensationHistory.value = history;
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
  } catch (err) {
    console.error('فشل فتح سجل الصرف:', err);
    showInfoAlert('فشل تحميل سجل الصرف. يرجى المحاولة مرة أخرى.');
    // فتح النافذة مع بيانات فارغة
    dispensationHistory.value = [];
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
  }
};

// ----------------------------------------------------
// 8. معالجة تعديل وحذف الأدوية
// ----------------------------------------------------
const handleEditMedication = async (medIndex, newDosage) => {
  try {
    const updatedMedications = [...selectedPatient.value.medications];
    const medicationName = updatedMedications[medIndex].drugName;
    
    // التأكد من وجود الحقول المطلوبة
    updatedMedications[medIndex] = {
      drugId: updatedMedications[medIndex].drugId || null,
      drugName: updatedMedications[medIndex].drugName || medicationName,
      dosage: newDosage.toString(),
      monthlyQuantity: `${parseInt(newDosage) * 30} حبة`,
      note: updatedMedications[medIndex].note || null
    };

    // تحديث في الـ API
    const updatedPatient = await updatePatientMedications(
      selectedPatient.value.fileNumber || selectedPatient.value.id,
      updatedMedications
    );

    // تحديث البيانات المحلية
    const patientIndex = patients.value.findIndex(p => 
      p.fileNumber === selectedPatient.value.fileNumber || p.id === selectedPatient.value.id
    );
    
    if (patientIndex !== -1) {
      patients.value[patientIndex].medications = updatedPatient.medications || updatedMedications;
    }
    
    // تحديث المريض المحدد
    selectedPatient.value = {
      ...selectedPatient.value,
      medications: updatedPatient.medications || updatedMedications
    };

    showSuccessAlert(`✅ تم تعديل جرعة ${medicationName} بنجاح`);
  } catch (err) {
    console.error('فشل تعديل الدواء:', err);
    const errorMsg = err.response?.data?.message || err.message || 'حدث خطأ غير متوقع';
    showInfoAlert(`فشل تعديل الدواء: ${errorMsg}`);
  }
};

const handleDeleteMedication = async (medIndex) => {
  try {
    const updatedMedications = [...selectedPatient.value.medications];
    const medicationName = updatedMedications[medIndex].drugName;
    updatedMedications.splice(medIndex, 1);

    // تحديث في الـ API
    const updatedPatient = await updatePatientMedications(
      selectedPatient.value.fileNumber || selectedPatient.value.id,
      updatedMedications
    );

    // تحديث البيانات المحلية
    const patientIndex = patients.value.findIndex(p => 
      p.fileNumber === selectedPatient.value.fileNumber || p.id === selectedPatient.value.id
    );
    
    if (patientIndex !== -1) {
      patients.value[patientIndex].medications = updatedPatient.medications || updatedMedications;
    }
    
    // تحديث المريض المحدد
    selectedPatient.value = {
      ...selectedPatient.value,
      medications: updatedPatient.medications || updatedMedications
    };

    showSuccessAlert(`🗑️ تم حذف الدواء ${medicationName} بنجاح`);
  } catch (err) {
    console.error('فشل حذف الدواء:', err);
    const errorMsg = err.response?.data?.message || err.message || 'حدث خطأ غير متوقع';
    showInfoAlert(`فشل حذف الدواء: ${errorMsg}`);
  }
};

// ----------------------------------------------------
// 9. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredPatients.value.length;

    const printWindow = window.open('', '_blank', 'height=600,width=800');

    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showInfoAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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
            .empty-message { text-align: center; padding: 40px; color: #666; font-size: 16px; }
        </style>

        <h1>قائمة المرضى (تقرير طباعة)</h1>
    `;

    if (resultsCount > 0) {
        tableHtml += `
            <p class="results-info">عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}</p>

            <table>
                <thead>
                    <tr>
                        <th>رقم الملف</th>
                        <th>الاسم الرباعي</th>
                        <th>الرقم الوطني</th>
                        <th>تاريخ الميلاد</th>
                        <th>رقم الهاتف</th>
                    </tr>
                </thead>
                <tbody>
        `;

        filteredPatients.value.forEach(patient => {
            tableHtml += `
                <tr>
                    <td>${patient.fileNumber || 'N/A'}</td>
                    <td>${patient.name || 'N/A'}</td>
                    <td>${patient.nationalId || 'N/A'}</td>
                    <td>${formatDateForDisplay(patient.birth) || 'N/A'}</td>
                    <td>${patient.phone || 'N/A'}</td>
                </tr>
            `;
        });

        tableHtml += `
                </tbody>
            </table>
        `;
    } else {
        tableHtml += `
            <div class="empty-message">
                <p>لا توجد بيانات للعرض</p>
            </div>
        `;
    }

    printWindow.document.write('<html><head><title>طباعة قائمة المرضى</title>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(tableHtml);
    printWindow.document.write('</body></html>');
    printWindow.document.close();

    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
        if (resultsCount > 0) {
            showSuccessAlert("✅ تم تجهيز التقرير بنجاح للطباعة.");
        } else {
            showInfoAlert("تم فتح نافذة الطباعة ولكن الجدول فارغ.");
        }
    };
};

// ----------------------------------------------------
// 10. دورة حياة المكون
// ----------------------------------------------------
onMounted(() => {
  fetchPatients();
});
</script>

<template>
  <DefaultLayout>
    <main class="flex-1 p-4 sm:p-5 pt-3">
      <!-- رسائل الخطأ -->
      
     

      <!-- المحتوى الرئيسي -->
      <div>
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
          <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0">
            <div class="flex items-center gap-3 w-full sm:max-w-xl">
              <search v-model="searchTerm" />
              
              <div class="dropdown dropdown-start">
                <div tabindex="0" role="button" class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                  <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
                  فرز
                </div>
                <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] border-[#ffffff8d] rounded-[35px] w-52 text-right">
                  <li class="menu-title text-gray-700 font-bold text-sm">حسب الاسم:</li>
                  <li>
                    <a @click="sortPatients('name', 'asc')" :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'asc'}">
                      الاسم (أ - ي)
                    </a>
                  </li>
                  <li>
                    <a @click="sortPatients('name', 'desc')" :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'desc'}">
                      الاسم (ي - أ)
                    </a>
                  </li>

                  <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب العمر:</li>
                  <li>
                    <a @click="sortPatients('birth', 'asc')" :class="{'font-bold text-[#4DA1A9]': sortKey === 'birth' && sortOrder === 'asc'}">
                      الأصغر سناً أولاً
                    </a>
                  </li>
                  <li>
                    <a @click="sortPatients('birth', 'desc')" :class="{'font-bold text-[#4DA1A9]': sortKey === 'birth' && sortOrder === 'desc'}">
                      الأكبر سناً أولاً
                    </a>
                  </li>

                  <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب آخر تحديث:</li>
                  <li>
                    <a @click="sortPatients('lastUpdated', 'desc')" :class="{'font-bold text-[#4DA1A9]': sortKey === 'lastUpdated' && sortOrder === 'desc'}">
                      الأحدث
                    </a>
                  </li>
                  <li>
                    <a @click="sortPatients('lastUpdated', 'asc')" :class="{'font-bold text-[#4DA1A9]': sortKey === 'lastUpdated' && sortOrder === 'asc'}">
                      الأقدم
                    </a>
                  </li>
                </ul>
              </div>
              
              <p class="text-sm font-semibold text-gray-600 self-end sm:self-center">
                عدد النتائج :
                <span class="text-[#4DA1A9] text-lg font-bold">{{ filteredPatients.length }}</span>
              </p>
            </div>
          </div>
          
          <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
            <btnprint @click="printTable" />
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col">
          <div class="overflow-y-auto flex-1" style="scrollbar-width: auto; scrollbar-color: grey transparent; direction: ltr;">
            <div class="overflow-x-auto h-full">
              <table dir="rtl" class="table w-full text-right min-w-[700px] border-collapse">
                <thead class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300">
                  <tr>
                    <th class="file-number-col">رقم الملف</th>
                    <th class="name-col">الإسم الرباعي</th>
                    <th class="national-id-col">الرقم الوطني</th>
                    <th class="birth-date-col">تاريخ الميلاد</th>
                    <th class="phone-col">رقم الهاتف</th>
                    <th class="actions-col">الإجراءات</th>
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
                    <tr v-for="(patient, index) in filteredPatients" :key="index" class="hover:bg-gray-100 border border-gray-300">
                        <td class="file-number-col">{{ patient.fileNumber || 'N/A' }}</td>
                        <td class="name-col">{{ patient.name || 'N/A' }}</td>
                        <td class="national-id-col">{{ patient.nationalId || 'N/A' }}</td>
                        <td class="birth-date-col">{{ formatDateForDisplay(patient.birth) || 'N/A' }}</td>
                        <td class="phone-col">{{ patient.phone || 'N/A' }}</td>

                        <td class="actions-col">
                        <div class="flex gap-3 justify-center">
                            <button @click="openViewModal(patient)">
                            <Icon icon="famicons:open-outline" class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform" />
                            </button>
                        </div>
                        </td>
                    </tr>
                    <tr v-if="filteredPatients.length === 0">
                        <td colspan="6" class="py-12">
                            <EmptyState message="لا يوجد مرضى مسجلين" />
                        </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </DefaultLayout>

  <!-- Modal Components -->
  <PatientViewModal
    :is-open="isViewModalOpen"
    :patient="selectedPatient"
    @close="closeViewModal"
    @dispensation-record="openDispensationModal"
  />

  <DispensationModal
    :is-open="isDispensationModalOpen"
    :patient="selectedPatient"
    :dispensation-history="dispensationHistory"
    @close="closeDispensationModal"
  />

  <!-- Success Alert -->
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
      class="fixed top-4 right-55 z-[1000] p-4 text-right bg-[#a2c4c6] text-white rounded-lg shadow-xl max-w-xs transition-all duration-300"
      dir="rtl"
    >
      {{ successMessage }}
    </div>
  </Transition>

  <!-- Info Alert -->
  <Transition
    enter-active-class="transition duration-300 ease-out transform"
    enter-from-class="translate-x-full opacity-0"
    enter-to-class="translate-x-0 opacity-100"
    leave-active-class="transition duration-200 ease-in transform"
    leave-from-class="translate-x-0 opacity-100"
    leave-to-class="translate-x-full opacity-0"
  >
    <div 
      v-if="isInfoAlertVisible" 
      class="fixed top-4 right-55 z-[1000] p-4 text-right bg-blue-500 text-white rounded-lg shadow-xl max-w-xs transition-all duration-300"
      dir="rtl"
    >
      {{ infoMessage }}
    </div>
  </Transition>
</template>

<style>
/* تنسيقات شريط التمرير */
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

/* تنسيقات عرض أعمدة الجدول */
.actions-col {
  width: 120px;
  min-width: 120px;
  max-width: 120px;
  text-align: center;
  padding-left: 0.5rem;
  padding-right: 0.5rem;
}
.file-number-col {
  width: 90px;
  min-width: 90px;
}
.national-id-col {
  width: 130px;
  min-width: 130px;
}
.birth-date-col {
  width: 120px;
  min-width: 120px;
}
.phone-col {
  width: 120px;
  min-width: 120px;
}
.name-col {
  width: 170px;
  min-width: 150px;
}

/* تنسيقات للجدول الفارغ */
tbody tr td[colspan] {
  height: 300px;
}
</style>