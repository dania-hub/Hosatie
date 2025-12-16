<script setup>
// عدل openViewModal و fetchPatientDetails , openDispensationModal , fetchDispensationHistory , closeDispensationModal
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

// استيراد المكونات المنفصلة
import PatientViewModal from "@/components/forsuperadmin/PatientViewModal.vue";
import DispensationModal from "@/components/forsuperadmin/DispensationModal.vue";
import DefaultLayout from "@/components/DefaultLayout.vue";

// ----------------------------------------------------
// 1. تكوين Axios
// ----------------------------------------------------
const API_BASE_URL = "https://api.your-domain.com"; // استبدل بالرابط الفعلي لـ API
const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${localStorage.getItem('token')}`
  }
});

// إضافة interceptor لإضافة التوكن تلقائيًا
api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  error => {
    return Promise.reject(error);
  }
);

// ----------------------------------------------------
// 2. بيانات المرضى والمستشفيات
// ----------------------------------------------------
const patients = ref([]);
const hospitals = ref([]);

const isLoading = ref(false);
const isLoadingHospitals = ref(false);
const hasError = ref(false);
const errorMessage = ref("");

// ----------------------------------------------------
// 3. دوال API
// ----------------------------------------------------
// التحقق من اتصال API
const checkAPI = async () => {
  try {
    const response = await api.get('/api/health');
    console.log('API متصل:', response.data);
    return true;
  } catch (err) {
    console.error('API غير متصل:', err);
    showInfoAlert('الخادم غير متصل. سيتم استخدام بيانات تجريبية.');
    return false;
  }
};

// جلب جميع المستشفيات
const fetchHospitals = async () => {
  isLoadingHospitals.value = true;
  
  try {
    const response = await api.get('/api/hospitals');
    hospitals.value = response.data.map(hospital => ({
      id: hospital.id,
      name: hospital.name,
      code: hospital.code
    }));
    
    console.log('تم جلب المستشفيات:', hospitals.value.length);
    return true;
  } catch (err) {
    console.error('فشل جلب المستشفيات:', err);
    
    // استخدام بيانات تجريبية للمستشفيات
    hospitals.value = getSampleHospitals();
    return false;
  } finally {
    isLoadingHospitals.value = false;
  }
};

// بيانات تجريبية للمستشفيات
// const getSampleHospitals = () => {
//   return [ ];
// };

// جلب جميع المرضى
const fetchPatients = async () => {
  isLoading.value = true;
  hasError.value = false;
  errorMessage.value = "";
  
  try {
    const response = await api.get('/api/patients');
    
    // إذا كانت هناك بيانات مستشفيات، ربط اسم المستشفى
    patients.value = response.data.map(patient => {
      let hospitalDisplay = patient.hospital || patient.hospitalName || 'غير محدد';
      
      // إذا كان لدينا معرف المستشفى، نبحث عن الاسم الكامل
      if (patient.hospitalId && hospitals.value.length > 0) {
        const foundHospital = hospitals.value.find(h => h.id === patient.hospitalId);
        if (foundHospital) {
          hospitalDisplay = foundHospital.name;
        }
      }
      
      return {
        ...patient,
        lastUpdated: new Date(patient.lastUpdated).toISOString(),
        nameDisplay: patient.name || '',
        nationalIdDisplay: patient.nationalId || '',
        birthDisplay: patient.birth ? formatDateForDisplay(patient.birth) : '',
        hospitalDisplay: hospitalDisplay,
        hospitalId: patient.hospitalId || null
      };
    });
    
    console.log('تم جلب المرضى:', patients.value.length);
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
    
    // استخدام بيانات تجريبية في حالة فشل الاتصال
    console.log('استخدام بيانات تجريبية بسبب خطأ الاتصال');
    patients.value = getSamplePatients();
  } finally {
    isLoading.value = false;
  }
};

// بيانات تجريبية للاختبار مع إضافة المستشفى
// const getSamplePatients = () => {};

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
    console.log('جلب تفاصيل المريض مع ID:', patientId);
    
    const response = await api.get(`/api/patients/${patientId}`);
    const patientData = response.data;
    
    // البحث عن اسم المستشفى الكامل
    let hospitalDisplay = patientData.hospital || patientData.hospitalName || 'غير محدد';
    if (patientData.hospitalId && hospitals.value.length > 0) {
      const foundHospital = hospitals.value.find(h => h.id === patientData.hospitalId);
      if (foundHospital) {
        hospitalDisplay = foundHospital.name;
      }
    }
    
    // تنسيق البيانات للعرض
    const formattedPatient = {
      ...patientData,
      nameDisplay: patientData.name || '',
      nationalIdDisplay: patientData.nationalId || '',
      birthDisplay: patientData.birth ? formatDateForDisplay(patientData.birth) : '',
      hospitalDisplay: hospitalDisplay,
      medications: patientData.medications || [],
      fileNumber: patientData.fileNumber || patientData.id || ''
    };
    
    console.log('بيانات المريض المستلمة:', formattedPatient);
    return formattedPatient;
  } catch (err) {
    console.error('فشل جلب تفاصيل المريض:', err);
    
    if (err.response) {
      console.error('تفاصيل الخطأ:', {
        status: err.response.status,
        data: err.response.data
      });
    }
    
    throw err;
  }
};

// جلب سجل الصرف
const fetchDispensationHistory = async (patientId) => {
  try {
    console.log('جلب سجل الصرف للمريض:', patientId);
    
    const response = await api.get(`/api/patients/${patientId}/dispensation-history`);
    
    // التأكد من أن البيانات هي مصفوفة
    const history = Array.isArray(response.data) ? response.data : [];
    
    console.log('سجل الصرف المستلم:', history.length, 'سجلات');
    return history;
  } catch (err) {
    console.error('فشل جلب سجل الصرف:', err);
    
    // في حالة الخطأ 404 (غير موجود)، إرجاع مصفوفة فارغة
    if (err.response && err.response.status === 404) {
      console.log('لم يتم العثور على سجل صرف للمريض - إرجاع مصفوفة فارغة');
      return [];
    }
    
    throw err;
  }
};

// ----------------------------------------------------
// 4. منطق البحث والفرز الموحد
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref('lastUpdated');
const sortOrder = ref('desc');
const selectedHospital = ref('all'); // 'all' لعرض جميع المستشفيات

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

const filterByHospital = (hospitalId) => {
    selectedHospital.value = hospitalId;
};

const filteredPatients = computed(() => {
    let list = patients.value;
    
    // تصفية حسب البحث
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(patient =>
            (patient.fileNumber && patient.fileNumber.toString().toLowerCase().includes(search)) ||
            (patient.name && patient.name.toLowerCase().includes(search)) ||
            (patient.nationalId && patient.nationalId.toLowerCase().includes(search)) ||
            (patient.birth && patient.birth.toLowerCase().includes(search)) ||
            (patient.phone && patient.phone.toLowerCase().includes(search)) ||
            (patient.hospitalDisplay && patient.hospitalDisplay.toLowerCase().includes(search))
        );
    }
    
    // تصفية حسب المستشفى
    if (selectedHospital.value !== 'all') {
        list = list.filter(patient => 
            patient.hospitalId && patient.hospitalId.toString() === selectedHospital.value
        );
    }

    // الفرز
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
            } else if (sortKey.value === 'hospital') {
                comparison = (a.hospitalDisplay || '').localeCompare(b.hospitalDisplay || '', 'ar');
            }

            return sortOrder.value === 'asc' ? comparison : -comparison;
        });
    }

    return list;
});

// حساب إحصائيات التصفية
const filterStats = computed(() => {
    const stats = {
        total: patients.value.length,
        filtered: filteredPatients.value.length,
        byHospital: {}
    };
    
    // إحصاءات حسب المستشفى
    hospitals.value.forEach(hospital => {
        const count = patients.value.filter(p => p.hospitalId === hospital.id).length;
        stats.byHospital[hospital.id] = count;
    });
    
    return stats;
});

// ----------------------------------------------------
// 5. منطق الرسائل التنبيهية
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
    console.log('فتح نموذج المريض:', patient);
    
    // للتصحيح المؤقت: إذا كان API غير متصل، استخدم البيانات مباشرة
    const isAPIConnected = await checkAPI();
    
    if (!isAPIConnected) {
      console.log('استخدام بيانات المريض المحلية (API غير متصل)');
      selectedPatient.value = {
        ...patient,
        nameDisplay: patient.name || '',
        nationalIdDisplay: patient.nationalId || '',
        birthDisplay: formatDateForDisplay(patient.birth) || '',
        hospitalDisplay: patient.hospital || patient.hospitalDisplay || 'غير محدد',
        medications: patient.medications || [],
        fileNumber: patient.fileNumber || patient.id || ''
      };
      isViewModalOpen.value = true;
      return;
    }
    
    // استخدام المعرف الصحيح - حاول استخدام id أولاً
    const patientId = patient.id || patient.fileNumber;
    
    if (!patientId) {
      console.error('لا يوجد معرف للمريض:', patient);
      showInfoAlert('بيانات المريض غير مكتملة. يرجى تحديث الصفحة والمحاولة مرة أخرى.');
      return;
    }
    
    // جلب البيانات المحدثة للمريض من API
    const patientData = await fetchPatientDetails(patientId);
    
    // التأكد من وجود بيانات المريض الأساسية
    if (!patientData || Object.keys(patientData).length === 0) {
      showInfoAlert('لم يتم العثور على بيانات المريض. قد يكون الحساب غير موجود.');
      return;
    }
    
    selectedPatient.value = patientData;
    isViewModalOpen.value = true;
    
    console.log('نموذج المريض مفتوح بنجاح:', patientData);
  } catch (err) {
    console.error('فشل فتح نموذج العرض:', err);
    
    // عرض رسالة خطأ محددة
    if (err.response) {
      switch (err.response.status) {
        case 404:
          showInfoAlert('لم يتم العثور على بيانات المريض. قد يكون الحساب غير موجود.');
          break;
        case 403:
          showInfoAlert('ليس لديك صلاحية لعرض تفاصيل هذا المريض.');
          break;
        case 401:
          showInfoAlert('جلسة العمل منتهية. يرجى تسجيل الدخول مرة أخرى.');
          break;
        default:
          showInfoAlert('فشل تحميل تفاصيل المريض. يرجى المحاولة مرة أخرى.');
      }
    } else if (err.request) {
      showInfoAlert('تعذر الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.');
    } else {
      showInfoAlert('حدث خطأ غير متوقع أثناء تحميل البيانات.');
    }
    
    // استخدام البيانات المحلية كحل بديل
    console.log('استخدام البيانات المحلية كحل بديل');
    selectedPatient.value = {
      ...patient,
      nameDisplay: patient.name || '',
      nationalIdDisplay: patient.nationalId || '',
      birthDisplay: formatDateForDisplay(patient.birth) || '',
      hospitalDisplay: patient.hospital || patient.hospitalDisplay || 'غير محدد',
      medications: patient.medications || [],
      fileNumber: patient.fileNumber || patient.id || ''
    };
    isViewModalOpen.value = true;
  }
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedPatient.value = {};
};

const closeDispensationModal = () => {
    isDispensationModalOpen.value = false;
    
    // إعادة فتح نافذة عرض المريض بعد إغلاق سجل الصرف
    setTimeout(() => {
      isViewModalOpen.value = true;
    }, 300);
};

const openDispensationModal = async () => {
  try {
    console.log('فتح سجل الصرف للمريض:', selectedPatient.value);
    
    if (!selectedPatient.value || !(selectedPatient.value.id || selectedPatient.value.fileNumber)) {
      console.error('لا يوجد مريض محدد');
      showInfoAlert('يرجى تحديد مريض أولاً');
      return;
    }
    
    // للتصحيح المؤقت: إذا كان API غير متصل، استخدم البيانات المحلية
    const isAPIConnected = await checkAPI();
    
    if (!isAPIConnected) {
      console.log('استخدام بيانات سجل الصرف المحلية');
      dispensationHistory.value = selectedPatient.value.dispensationHistory || [];
      isDispensationModalOpen.value = true;
      isViewModalOpen.value = false;
      return;
    }
    
    // جلب سجل الصرف من API
    const patientId = selectedPatient.value.id || selectedPatient.value.fileNumber;
    const history = await fetchDispensationHistory(patientId);
    
    dispensationHistory.value = history;
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
    
    console.log('سجل الصرف مفتوح بنجاح:', history.length, 'سجلات');
  } catch (err) {
    console.error('فشل فتح سجل الصرف:', err);
    
    // استمرار فتح النافذة حتى مع وجود خطأ
    dispensationHistory.value = [];
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
    
    // استخدام البيانات المحلية كحل بديل
    if (selectedPatient.value.dispensationHistory && selectedPatient.value.dispensationHistory.length > 0) {
      console.log('استخدام بيانات سجل الصرف المحلية');
      dispensationHistory.value = selectedPatient.value.dispensationHistory;
    }
    
    // عرض رسالة معلومات فقط إذا لم يكن الخطأ 404 (غير موجود)
    if (!err.response || err.response.status !== 404) {
      showInfoAlert('فشل تحميل سجل الصرف. سيتم عرض القائمة فارغة.');
    } else {
      showInfoAlert('لا يوجد سجل صرف لهذا المريض.');
    }
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
                        <th>المستشفى</th>
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
                    <td>${patient.hospitalDisplay || 'غير محدد'}</td>
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
onMounted(async () => {
  console.log('جاري تحميل المكون...');
  const isAPIConnected = await checkAPI();
  
  if (isAPIConnected) {
    // جلب المستشفيات أولاً
    await fetchHospitals();
    // ثم جلب المرضى
    await fetchPatients();
  } else {
    // استخدام البيانات التجريبية
    hospitals.value = getSampleHospitals();
    patients.value = getSamplePatients();
  }
  
  console.log('تم تحميل البيانات:', {
    patients: patients.value.length,
    hospitals: hospitals.value.length
  });
});
</script>

<template>
  <DefaultLayout>
    <main class="flex-1 p-4 sm:p-5 pt-3">
      <!-- رسائل الخطأ -->
      <div v-if="hasError" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-center">
          <Icon icon="mdi:alert-circle-outline" class="w-5 h-5 text-red-500 ml-2" />
          <p class="text-red-700 font-medium">{{ errorMessage }}</p>
        </div>
      </div>
      
      <!-- حالة التحميل -->
      <div v-if="isLoading" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-center justify-center">
          <Icon icon="mdi:loading" class="w-5 h-5 text-blue-500 animate-spin ml-2" />
          <p class="text-blue-700 font-medium">جاري تحميل بيانات المرضى...</p>
        </div>
      </div>

      <!-- المحتوى الرئيسي -->
      <div>
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3">

  <!-- الجهة اليسرى: البحث + الفلترة + الفرز -->
  <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">

    <!-- البحث -->
    <search v-model="searchTerm" class="flex-1 min-w-[150px] sm:min-w-[200px]" />

    <!-- تصفية حسب المستشفى -->
    <div class="dropdown dropdown-start">
      <div tabindex="0" role="button"
           class="inline-flex items-center px-4 py-3 border-2 border-[#ffffff8d] rounded-full bg-[#4DA1A9] text-white text-sm font-medium cursor-pointer hover:bg-[#5e8c90f9] transition-all duration-200">
        تصفية بالمستشفى
      </div>
      <ul tabindex="0"
          class="dropdown-content z-50 menu p-2 shadow-lg bg-white border rounded-2xl w-64 text-right max-h-80 overflow-y-auto">
        <!-- تحميل المستشفيات -->
        <li v-if="isLoadingHospitals" class="flex items-center justify-center p-2">
          <Icon icon="mdi:loading" class="w-4 h-4 animate-spin ml-2" />
          <span class="text-gray-500">جاري تحميل المستشفيات...</span>
        </li>
        <!-- خيار "جميع المستشفيات" -->
        <li v-else>
          <a @click="filterByHospital('all')" :class="{'font-bold text-[#4DA1A9]': selectedHospital === 'all'}">
            <div class="flex justify-between items-center">
              <span>جميع المستشفيات</span>
              <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ filterStats.total }}</span>
            </div>
          </a>
        </li>
        <!-- المستشفيات -->
        <li v-for="hospital in hospitals" :key="hospital.id">
          <a @click="filterByHospital(hospital.id.toString())" 
             :class="{'font-bold text-[#4DA1A9]': selectedHospital === hospital.id.toString()}">
            <div class="flex justify-between items-center">
              <span>{{ hospital.name }}</span>
              <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ filterStats.byHospital[hospital.id] || 0 }}</span>
            </div>
          </a>
        </li>
      </ul>
    </div>

    <!-- فرز -->
    <div class="dropdown dropdown-start">
      <div tabindex="0" role="button"
           class="inline-flex items-center px-4 py-3 border-2 border-[#ffffff8d] rounded-full bg-[#4DA1A9] text-white text-sm font-medium cursor-pointer hover:bg-[#5e8c90f9] transition-all duration-200">
        <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
        فرز
      </div>
      <ul tabindex="0"
          class="dropdown-content z-50 menu p-2 shadow-lg bg-white border rounded-2xl w-52 text-right">
        <!-- حسب الاسم -->
        <li class="menu-title text-gray-700 font-bold text-sm">حسب الاسم:</li>
        <li>
          <a @click="sortPatients('name', 'asc')" 
             :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'asc'}">
            الاسم (أ - ي)
          </a>
        </li>
        <li>
          <a @click="sortPatients('name', 'desc')" 
             :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'desc'}">
            الاسم (ي - أ)
          </a>
        </li>

        <!-- حسب العمر -->
        <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب العمر:</li>
        <li>
          <a @click="sortPatients('birth', 'asc')" 
             :class="{'font-bold text-[#4DA1A9]': sortKey === 'birth' && sortOrder === 'asc'}">
            الأصغر سناً أولاً
          </a>
        </li>
        <li>
          <a @click="sortPatients('birth', 'desc')" 
             :class="{'font-bold text-[#4DA1A9]': sortKey === 'birth' && sortOrder === 'desc'}">
            الأكبر سناً أولاً
          </a>
        </li>

        <!-- حسب المستشفى -->
        <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب المستشفى:</li>
        <li>
          <a @click="sortPatients('hospital', 'asc')" 
             :class="{'font-bold text-[#4DA1A9]': sortKey === 'hospital' && sortOrder === 'asc'}">
            المستشفى (أ - ي)
          </a>
        </li>
        <li>
          <a @click="sortPatients('hospital', 'desc')" 
             :class="{'font-bold text-[#4DA1A9]': sortKey === 'hospital' && sortOrder === 'desc'}">
            المستشفى (ي - أ)
          </a>
        </li>

        <!-- حسب آخر تحديث -->
        <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب آخر تحديث:</li>
        <li>
          <a @click="sortPatients('lastUpdated', 'desc')" 
             :class="{'font-bold text-[#4DA1A9]': sortKey === 'lastUpdated' && sortOrder === 'desc'}">
            الأحدث
          </a>
        </li>
        <li>
          <a @click="sortPatients('lastUpdated', 'asc')" 
             :class="{'font-bold text-[#4DA1A9]': sortKey === 'lastUpdated' && sortOrder === 'asc'}">
            الأقدم
          </a>
        </li>
      </ul>
    </div>

    <!-- عداد النتائج -->
    <p class="text-sm font-semibold text-gray-600 ml-2">
      عدد النتائج :
      <span class="text-[#4DA1A9] text-lg font-bold">{{ filteredPatients.length }}</span>
      <span v-if="selectedHospital !== 'all'" class="text-xs text-gray-500 mr-2">(مستشفى محدد)</span>
    </p>
  </div>

  <!-- الجهة اليمنى: زر الطباعة -->
  <div class="flex items-center justify-end w-full sm:w-auto">
    <btnprint @click="printTable" />
  </div>

</div>


        <!-- معلومات التصفية -->
        <div v-if="selectedHospital !== 'all'" class="mb-4 p-3 bg-[#9aced2]/20 rounded-lg border border-[#9aced2]/30">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Icon icon="mdi:filter" class="w-4 h-4 text-[#4DA1A9]" />
              <span class="text-sm text-[#2E5077] font-medium">
                مصفى حسب: 
                <span class="font-bold">
                  {{ hospitals.find(h => h.id.toString() === selectedHospital)?.name || selectedHospital }}
                </span>
              </span>
            </div>
            <button 
              @click="filterByHospital('all')"
              class="text-xs text-gray-500 hover:text-[#4DA1A9] flex items-center gap-1"
            >
              <Icon icon="mdi:close" class="w-3 h-3" />
              إلغاء التصفية
            </button>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col">
          <div class="overflow-y-auto flex-1" style="scrollbar-width: auto; scrollbar-color: grey transparent; direction: ltr;">
            <div class="overflow-x-auto h-full">
              <table dir="rtl" class="table w-full text-right min-w-[800px] border-collapse">
                <thead class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300">
                  <tr>
                    <th class="file-number-col">رقم الملف</th>
                    <th class="name-col">الإسم الرباعي</th>
                    <th class="national-id-col">الرقم الوطني</th>
                    <th class="birth-date-col">تاريخ الميلاد</th>
                    <th class="phone-col">رقم الهاتف</th>
                    <th class="hospital-col">المستشفى</th>
                    <th class="actions-col">الإجراءات</th>
                  </tr>
                </thead>

                <tbody v-if="filteredPatients.length > 0">
                  <tr v-for="(patient, index) in filteredPatients" :key="index" class="hover:bg-gray-100 border border-gray-300">
                    <td class="file-number-col">{{ patient.fileNumber || 'N/A' }}</td>
                    <td class="name-col">{{ patient.name || 'N/A' }}</td>
                    <td class="national-id-col">{{ patient.nationalId || 'N/A' }}</td>
                    <td class="birth-date-col">{{ formatDateForDisplay(patient.birth) || 'N/A' }}</td>
                    <td class="phone-col">{{ patient.phone || 'N/A' }}</td>
                    <td class="hospital-col">
                      <div class="flex items-center gap-2">
                        <span>{{ patient.hospitalDisplay || 'غير محدد' }}</span>
                      </div>
                    </td>

                    <td class="actions-col">
                      <div class="flex gap-3 justify-center">
                        <button @click="openViewModal(patient)" title="عرض التفاصيل">
                          <Icon icon="famicons:open-outline" class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="7" class="text-center py-8 text-gray-500">
                      <div class="flex flex-col items-center justify-center">
                        <Icon icon="mdi:database-off-outline" class="w-12 h-12 text-gray-300 mb-2" />
                        <p class="text-lg font-medium">لا توجد بيانات للعرض</p>
                        <p v-if="searchTerm" class="text-sm text-gray-400 mt-1">لا توجد نتائج مطابقة لبحثك</p>
                        <p v-else-if="selectedHospital !== 'all'" class="text-sm text-gray-400 mt-1">
                          لا توجد مرضى في {{ hospitals.find(h => h.id.toString() === selectedHospital)?.name || 'هذا المستشفى' }}
                        </p>
                        <p v-else class="text-sm text-gray-400 mt-1">لا توجد بيانات مرضى مسجلة في النظام</p>
                      </div>
                    </td>
                  </tr>
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
      <div class="flex items-center">
        <Icon icon="mdi:check-circle-outline" class="w-5 h-5 ml-2" />
        <span>{{ successMessage }}</span>
      </div>
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
      <div class="flex items-center">
        <Icon icon="mdi:information-outline" class="w-5 h-5 ml-2" />
        <span>{{ infoMessage }}</span>
      </div>
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
.hospital-col {
  width: 200px;
  min-width: 180px;
}

/* تنسيقات للجدول الفارغ */
tbody tr td[colspan] {
  height: 300px;
}

/* تحسينات للاستجابة */
@media (max-width: 640px) {
  .flex-col.sm\:flex-row {
    flex-direction: column !important;
  }
  
  .w-full.sm\:max-w-xl {
    max-width: 100% !important;
  }
  
  .w-full.sm\:w-auto {
    width: 100% !important;
  }
  
  .justify-end {
    justify-content: flex-start !important;
  }
}
</style>