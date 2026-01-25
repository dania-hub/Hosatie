<script setup>
// عدل openViewModal و fetchPatientDetails , openDispensationModal , fetchDispensationHistory , closeDispensationModal
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

// استيراد المكونات المنفصلة
import PatientViewModal from "@/components/forsuperadmin/PatientViewModal.vue";
import DispensationModal from "@/components/forsuperadmin/DispensationModal.vue";
import DefaultLayout from "@/components/DefaultLayout.vue";
import Toast from "@/components/Shared/Toast.vue";

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
        if (error.response?.status === 401) {
            console.error('Unauthenticated - Please login again.');
        }
        return Promise.reject(error);
    }
);

// ----------------------------------------------------
// 2. بيانات المرضى والمستشفيات
// ----------------------------------------------------
const patients = ref([]);
const hospitals = ref([]);

const isLoading = ref(true);
const isLoadingHospitals = ref(false);
const error = ref(null);
const errorMessage = ref("");

// ----------------------------------------------------
// 3. دوال API
// ----------------------------------------------------
// التحقق من اتصال API
const checkAPI = async () => {
  try {
    const response = await api.get('/health');
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
    const response = await api.get('/super-admin/hospitals');
    const hospitalsData = response.data.data || response.data || [];
    hospitals.value = hospitalsData.map(hospital => ({
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
const getSampleHospitals = () => {
  return [
      { id: 1, name: 'مستشفى طرابلس المركزي', code: 'H001' },
      { id: 2, name: 'مستشفى الخضراء', code: 'H002' },
      { id: 3, name: 'مركز طرابلس الطبي', code: 'H003' }
  ];
};

// جلب جميع المرضى
const fetchPatients = async () => {
  isLoading.value = true;
  error.value = null;
  errorMessage.value = "";
  
  try {
    const response = await api.get('/super-admin/patients');
    
    // Check if response.data.data exists (standard Laravel resource/json response)
    const patientsData = response.data.data || response.data;

    // إذا كانت هناك بيانات مستشفيات، ربط اسم المستشفى
    patients.value = patientsData.map(patient => {
      // API returns: fileNumber, fullName, nationalId, birthDate, phone, hospitalName
      const hospitalName = patient.hospitalName || patient.hospital || patient.hospital_name || '';
      
      // البحث عن ID المستشفى وكوده
      let hospitalId = patient.hospitalId || patient.hospital_id || null;
      let hospitalCode = '';
      let foundHospital = null;

      if (hospitalId && hospitals.value.length > 0) {
          foundHospital = hospitals.value.find(h => h.id == hospitalId);
      }

      if (!foundHospital && hospitalName && hospitals.value.length > 0) {
        foundHospital = hospitals.value.find(h => 
          h.name === hospitalName || 
          h.name.includes(hospitalName) || 
          hospitalName.includes(h.name)
        );
        if (foundHospital) {
          hospitalId = foundHospital.id;
        }
      }

      if (foundHospital) {
          hospitalCode = foundHospital.code || '';
      }

      const birthRaw = patient.birthDate || patient.birth || '';
      const nameDisplay = patient.fullName || patient.name || patient.patientName || '';
      const nationalIdDisplay = patient.nationalId || patient.national_id || '';
      
      return {
        id: patient.fileNumber || patient.id || patient.patientId || null, // or patient.id if available, but controller maps fileNumber => id
        fileNumber: patient.fileNumber || patient.id || patient.patientId || '',
        nameDisplay,
        nationalIdDisplay,
        birthDisplay: birthRaw ? formatDateForDisplay(birthRaw) : '',
        birthRaw,
        phone: patient.phone || '',
        hospitalDisplay: hospitalName || 'غير محدد',
        hospitalId: hospitalId, // إضافة hospitalId للتصفية
        hospitalName: hospitalName, // حفظ اسم المستشفى الأصلي
        hospitalCode,
        lastUpdated: patient.updated_at || patient.lastUpdated || patient.updatedAt || null,
        // Keep original fields just in case
        ...patient
      };
    });
    
    console.log('تم جلب المرضى:', patients.value.length);
  } catch (err) {
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
    error.value = errorMessage.value;
    
    // استخدام بيانات تجريبية في حالة فشل الاتصال
    console.error('فشل تحميل المرضى:', errorMessage.value);
    patients.value = [];
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
    
    return date.toLocaleDateString('en-GB', {
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
    
    const response = await api.get(`/super-admin/patients/${patientId}`);
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
    
    const response = await api.get(`/super-admin/patients/${patientId}/dispensation-history`);
    
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
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);
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

const clearDateFilter = () => {
    dateFrom.value = "";
    dateTo.value = "";
};

const filteredPatients = computed(() => {
    let list = patients.value;
    
    // تصفية حسب البحث
    if (searchTerm.value) {
      const search = searchTerm.value.toLowerCase();
      list = list.filter(patient => {
        const values = [
          patient.fileNumber,
          patient.nameDisplay,
          patient.fullName,
          patient.name,
          patient.nationalIdDisplay,
          patient.nationalId,
          patient.birthDisplay,
          patient.phone,
          patient.hospitalDisplay,
          patient.hospitalName
        ];

        return values.some(value => value && value.toString().toLowerCase().includes(search));
      });
    }
    
    // تصفية حسب المستشفى
    if (selectedHospital.value !== 'all') {
        const selectedHospitalId = selectedHospital.value.toString();
        const selectedHospitalObj = hospitals.value.find(h => h.id.toString() === selectedHospitalId);
        
        list = list.filter(patient => {
            // التحقق من ID المستشفى أولاً
            if (patient.hospitalId && patient.hospitalId.toString() === selectedHospitalId) {
                return true;
            }
          if (patient.hospital_id && patient.hospital_id.toString() === selectedHospitalId) {
            return true;
          }
            
            // إذا لم يكن هناك ID، المقارنة بناءً على اسم المستشفى
            if (selectedHospitalObj && patient.hospitalDisplay) {
                return patient.hospitalDisplay === selectedHospitalObj.name ||
                 patient.hospitalName === selectedHospitalObj.name ||
                 patient.hospital === selectedHospitalObj.name ||
                 patient.hospital_name === selectedHospitalObj.name;
            }
            
            return false;
        });
    }

    if (dateFrom.value || dateTo.value) {
        list = list.filter((patient) => {
            const birthDate = patient.birthRaw || patient.birthDate || patient.birth || patient.birthDisplay;
            if (!birthDate || birthDate === 'غير متوفر') return false;

            let birthDateObj;
            try {
                if (birthDate.includes('-')) {
                    birthDateObj = new Date(birthDate);
                } else if (birthDate.includes('/')) {
                    const parts = birthDate.split('/');
                    if (parts.length === 3) {
                        if (parts[0].length === 4) {
                            birthDateObj = new Date(parts[0], parts[1] - 1, parts[2]);
                        } else {
                            birthDateObj = new Date(parts[2], parts[1] - 1, parts[0]);
                        }
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }

                if (isNaN(birthDateObj.getTime())) return false;
            } catch {
                return false;
            }

            birthDateObj.setHours(0, 0, 0, 0);

            let matchesFrom = true;
            let matchesTo = true;

            if (dateFrom.value) {
                const fromDate = new Date(dateFrom.value);
                fromDate.setHours(0, 0, 0, 0);
                matchesFrom = birthDateObj >= fromDate;
            }

            if (dateTo.value) {
                const toDate = new Date(dateTo.value);
                toDate.setHours(23, 59, 59, 999);
                matchesTo = birthDateObj <= toDate;
            }

            return matchesFrom && matchesTo;
        });
    }

    // الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === 'name') {
              comparison = (a.nameDisplay || a.fullName || a.name || '').localeCompare(b.nameDisplay || b.fullName || b.name || '', 'ar');
            } else if (sortKey.value === 'birth') {
              const ageA = calculateAge(a.birthRaw || a.birth || a.birthDate);
              const ageB = calculateAge(b.birthRaw || b.birth || b.birthDate);
                comparison = ageA - ageB;
            } else if (sortKey.value === 'lastUpdated') {
              const dateA = new Date(a.lastUpdated || a.updated_at || a.updatedAt || 0);
              const dateB = new Date(b.lastUpdated || b.updated_at || b.updatedAt || 0);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === 'hospital') {
              comparison = (a.hospitalDisplay || a.hospitalName || '').localeCompare(b.hospitalDisplay || b.hospitalName || '', 'ar');
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
      const count = patients.value.filter(p => 
        (p.hospitalId && p.hospitalId.toString() === hospital.id.toString()) ||
        (p.hospital_id && p.hospital_id.toString() === hospital.id.toString())
      ).length;
      stats.byHospital[hospital.id] = count;
    });
    
    return stats;
});

// ----------------------------------------------------
// 5. منطق الرسائل التنبيهية
// ----------------------------------------------------
const toast = ref({
    show: false,
    type: 'success',
    title: '',
    message: ''
});

const showSuccessAlert = (message) => {
    const isError = message.startsWith('❌') || message.includes('فشل');
    toast.value = {
        show: true,
        type: isError ? 'error' : 'success',
        title: isError ? 'خطأ' : 'نجاح',
        message: message.replace(/^❌ |^✅ /, '')
    };
};

const showInfoAlert = (message) => {
    toast.value = {
        show: true,
        type: 'info',
        title: 'تنبيه',
        message: message
    };
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

        <h1>قائمة المرضى </h1>
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
            // استخدام الحقول الصحيحة للعرض
            const name = patient.nameDisplay || patient.fullName || patient.name || 'غير محدد';
            const nationalId = patient.nationalIdDisplay || patient.nationalId || 'غير محدد';
            const birthDate = patient.birthDisplay || formatDateForDisplay(patient.birthDate) || formatDateForDisplay(patient.birth) || 'غير محدد';
            
            tableHtml += `
                <tr>
                    <td>${patient.fileNumber || 'غير محدد'}</td>
                    <td>${name}</td>
                    <td>${nationalId}</td>
                    <td>${birthDate}</td>
                    <td>${patient.phone || 'غير محدد'}</td>
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
    isLoading.value = true;
    error.value = null;
    
    try {
        // جلب المستشفيات أولاً
        await fetchHospitals();
        // ثم جلب المرضى
        await fetchPatients();
    } catch (err) {
        console.error('فشل تحميل البيانات:', err);
        error.value = "حدث خطأ أثناء تحميل البيانات. يرجى التأكد من اتصال الإنترنت والمحاولة مرة أخرى.";
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
  <DefaultLayout>
    <main class="flex-1 p-4 sm:p-5 pt-3">
      <!-- المحتوى الرئيسي -->
      <div>
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3">

  <!-- الجهة اليسرى: البحث + الفلترة + الفرز -->
  <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">

    <!-- البحث -->
    <search v-model="searchTerm" class="flex-1 min-w-[150px] sm:min-w-[200px]" />

    <button
      @click="showDateFilter = !showDateFilter"
      class="h-11 w-11 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
      :title="showDateFilter ? 'إخفاء فلتر التاريخ' : 'إظهار فلتر التاريخ'"
    >
      <Icon icon="solar:calendar-bold" class="w-5 h-5" />
    </button>

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
  <div class="flex items-center justify-end w-full  sm:w-auto">
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

                                <tbody class="text-gray-800">
                                    <tr v-if="isLoading">
                                        <td colspan="7" class="p-4">
                                            <TableSkeleton :rows="10" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="error">
                                        <td colspan="7" class="py-12">
                                            <ErrorState :message="error" :retry="fetchPatients" />
                                        </td>
                                    </tr>
                                    <template v-else>
                                        <tr
                                            v-for="(patient, index) in filteredPatients"
                                            :key="index"
                                            class="hover:bg-gray-100 border border-gray-300"
                                        >
                                            <td class="file-number-col">
                                              <div class="flex items-center gap-1 justify-start">
                                                  <span v-if="patient.hospitalCode" class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-100">
                                                      {{ patient.hospitalCode }}
                                                  </span>
                                                  <span class="font-medium text-gray-700">{{ patient.fileNumber || 'N/A' }}</span>
                                              </div>
                                            </td>
                                            <td class="name-col">{{ patient.nameDisplay || patient.fullName || patient.name || 'N/A' }}</td>
                                            <td class="national-id-col">{{ patient.nationalIdDisplay || patient.nationalId || 'N/A' }}</td>
                                            <td class="birth-date-col">{{ patient.birthDisplay || formatDateForDisplay(patient.birthDate) || formatDateForDisplay(patient.birth) || 'N/A' }}</td>
                                            <td class="phone-col">{{ patient.phone || 'N/A' }}</td>
                                            <td class="hospital-col">
                                                <div class="flex items-center gap-2">
                                                    <span>{{ patient.hospitalDisplay || 'غير محدد' }}</span>
                                                </div>
                                            </td>

                                            <td class="actions-col">
                                                <div class="flex gap-3 justify-center">
                                                    <button @click="openViewModal(patient)" 
                                                      class="tooltip tooltip-bottom p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                    >
                                                        <Icon icon="famicons:open-outline" class="w-4 h-4 text-green-600 cursor-pointer hover:scale-110 transition-transform" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="filteredPatients.length === 0">
                                            <td colspan="7" class="py-12">
                                                <EmptyState message="لا توجد بيانات مرضى حالياً" />
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
    <Toast 
        :show="toast.show" 
        :type="toast.type" 
        :title="toast.title" 
        :message="toast.message" 
        @close="toast.show = false" 
    />
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
  width: 140px;
  min-width: 140px;
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
