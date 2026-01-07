<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

// استيراد المكونات المنفصلة
import PatientViewModal from "@/components/patientDoctor/PatientViewModal.vue";
import AddMedicationModal from "@/components/patientDoctor/AddMedicationModal.vue";
import DispensationModal from "@/components/patientDoctor/DispensationModal.vue";
import DefaultLayout from "@/components/DefaultLayout.vue";
import Toast from "@/components/Shared/Toast.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

// ----------------------------------------------------
// 1. تكوين Axios
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

// إضافة interceptor للتعامل مع الأخطاء
api.interceptors.response.use(
  response => response,
  error => {
    console.error('API Error:', error.response?.data || error.message);
    return Promise.reject(error);
  }
);

// ----------------------------------------------------
// 2. بيانات المرضى - أصبحت تأتي من API
// ----------------------------------------------------
const patients = ref([]);
const isLoading = ref(true);
const hasError = ref(false);
const errorMessage = ref("");

// ----------------------------------------------------
// 3. دوال API
// ----------------------------------------------------
// جلب جميع المرضى
const fetchPatients = async (search = '') => {
  isLoading.value = true;
  hasError.value = false;
  errorMessage.value = "";
  
  // إشعار بدء التحميل
  showSuccessAlert("جاري تحميل قائمة المرضى...");
  
  try {
    const params = {};
    if (search) {
      params.search = search;
    }
    const response = await api.get('/patients', { params });
    // BaseApiController يُرجع البيانات في response.data.data
    const patientsData = response.data.data || response.data;
    patients.value = patientsData.map(patient => ({
      ...patient,
      lastUpdated: patient.lastUpdated ? new Date(patient.lastUpdated).toISOString() : new Date().toISOString(),
      // إضافة خصائص العرض للمكونات
      nameDisplay: patient.name || patient.nameDisplay || 'غير متوفر',
      nationalIdDisplay: patient.nationalId || patient.nationalIdDisplay || 'غير متوفر',
      birthDisplay: patient.birth || patient.birthDisplay || 'غير متوفر'
    }));
    
    // إشعار نجاح التحميل
    showSuccessAlert(`تم تحميل ${patients.value.length} مريض بنجاح`);
  } catch (err) {
    hasError.value = true;
    const errorData = err.response?.data;
    
    if (err.response?.status === 401) {
      errorMessage.value = "انتهت صلاحية الجلسة. الرجاء تسجيل الدخول مرة أخرى.";
      showErrorAlert("انتهت صلاحية الجلسة. الرجاء تسجيل الدخول مرة أخرى.");
    } else if (err.response?.status === 403) {
      errorMessage.value = "ليس لديك صلاحية لعرض قائمة المرضى.";
      showWarningAlert("ليس لديك صلاحية لعرض قائمة المرضى.");
    } else if (err.response?.status === 404) {
      errorMessage.value = "لم يتم العثور على بيانات المرضى.";
      showWarningAlert("لم يتم العثور على بيانات المرضى.");
    } else if (err.response?.status === 422) {
      errorMessage.value = "بيانات البحث غير صالحة.";
      showErrorAlert("بيانات البحث غير صالحة.");
    } else if (err.response?.status === 500) {
      errorMessage.value = "حدث خطأ في الخادم. الرجاء المحاولة مرة أخرى لاحقاً.";
      showErrorAlert("حدث خطأ في الخادم. الرجاء المحاولة مرة أخرى لاحقاً.");
    } else if (err.code === 'NETWORK_ERROR' || !err.response) {
      errorMessage.value = "فشل الاتصال بالخادم. الرجاء التحقق من اتصال الإنترنت.";
      showErrorAlert("فشل الاتصال بالخادم. الرجاء التحقق من اتصال الإنترنت.");
    } else if (errorData?.message) {
      errorMessage.value = errorData.message;
      showErrorAlert(` ${errorData.message}`);
    } else {
      errorMessage.value = 'حدث خطأ غير متوقع في جلب بيانات المرضى.';
      showErrorAlert("حدث خطأ غير متوقع في جلب بيانات المرضى.");
    }
  } finally {
    isLoading.value = false;
  }
};

// جلب بيانات مريض محدد
const fetchPatientDetails = async (patientId) => {
  try {
    const response = await api.get(`/patients/${patientId}`);
    // BaseApiController يُرجع البيانات في response.data.data
    const patientData = response.data.data || response.data;
    
    // تحويل البيانات لتتوافق مع ما يتوقعه المكون
    return {
      ...patientData,
      // الحفاظ على رقم الملف
      fileNumber: patientData.fileNumber || patientData.file_number || patientData.id || patientId,
      // إضافة خصائص العرض (الـ API يعيد name, nationalId, birth)
      nameDisplay: patientData.name || patientData.nameDisplay || 'غير متوفر',
      nationalIdDisplay: patientData.nationalId || patientData.nationalIdDisplay || patientData.national_id || 'غير متوفر',
      birthDisplay: formatDate(patientData.birth || patientData.birthDisplay || patientData.birth_date || 'غير متوفر'),
      // التأكد من وجود مصفوفة الأدوية مع معالجة البيانات
      medications: (patientData.medications || []).map(med => {
        // الحصول على وحدة القياس من API أو استخدام "حبة" كافتراضي
        const unit = med.unit || 'حبة';
        
        // التأكد من أن dosage و monthlyQuantity في التنسيق الصحيح
        // إذا كانت monthlyQuantity رقم، نحولها إلى نص منسق مع الوحدة الصحيحة
        if (typeof med.monthlyQuantity === 'number') {
          med.monthlyQuantity = med.monthlyQuantity > 0 ? med.monthlyQuantity + ' ' + unit : 'غير محدد';
        }
        // استخدام daily_quantity مباشرة من البيانات
        if ((!med.dosage || med.dosage === 'غير محدد')) {
          const dailyQty = med.dailyQuantity || med.daily_quantity || 0;
          if (dailyQty > 0) {
            med.dosage = dailyQty + ' ' + unit + ' يومياً';
          }
        }
        // التأكد من وجود وحدة القياس في الكائن
        if (!med.unit) {
          med.unit = unit;
        }
        // تنسيق تاريخ الإسناد
        if (med.assignmentDate) {
          med.assignmentDate = formatDate(med.assignmentDate);
        } else if (med.assignment_date) {
          med.assignmentDate = formatDate(med.assignment_date);
        } else if (med.created_at) {
          med.assignmentDate = formatDate(med.created_at);
        }
        return med;
      })
    };
  } catch (err) {
    console.error('خطأ في جلب بيانات المريض:', err);
    const errorData = err.response?.data;
    
    if (err.response?.status === 404) {
      showWarningAlert(" المريض غير موجود أو تم حذفه.");
    } else if (err.response?.status === 403) {
      showWarningAlert("ليس لديك صلاحية لعرض بيانات هذا المريض.");
    } else if (errorData?.message) {
      showErrorAlert(` ${errorData.message}`);
    } else {
      showErrorAlert("حدث خطأ في تحميل بيانات المريض.");
    }
    
    // لا نعرض خطأ، نرجع بيانات افتراضية
    return {
      ...selectedPatient.value,
      fileNumber: selectedPatient.value?.fileNumber || selectedPatient.value?.file_number || selectedPatient.value?.id || patientId,
      nameDisplay: selectedPatient.value?.name || selectedPatient.value?.nameDisplay || 'غير متوفر',
      nationalIdDisplay: selectedPatient.value?.nationalId || selectedPatient.value?.nationalIdDisplay || selectedPatient.value?.national_id || 'غير متوفر',
      birthDisplay: selectedPatient.value?.birth || selectedPatient.value?.birthDisplay || selectedPatient.value?.birth_date || 'غير متوفر',
      medications: []
    };
  }
};

// تحديث دواء موجود (دواء واحد فقط)
const updateMedicationAPI = async (patientId, pivotId, medicationData) => {
  try {
    const response = await api.put(`/patients/${patientId}/medications/${pivotId}`, medicationData);
    return response.data;
  } catch (err) {
    const errorData = err.response?.data;
    
    if (err.response?.status === 404) {
      throw new Error("الدواء غير موجود أو تم حذفه.");
    } else if (err.response?.status === 422) {
      throw new Error("بيانات الدواء غير صالحة.");
    } else if (errorData?.message) {
      throw new Error(errorData.message);
    } else {
      throw new Error("حدث خطأ في تحديث الدواء.");
    }
  }
};

// تحديث بيانات المريض (بعد إضافة/تعديل/حذف دواء)
const updatePatientMedications = async (patientId, medications) => {
  try {
    // تحويل الأدوية إلى التنسيق الذي يتوقعه الـ API
    const medicationsPayload = medications.map(med => {
      let monthlyQuantity = 0;
      
      // إذا كانت monthlyQuantity موجودة كرقم
      if (typeof med.monthlyQuantity === 'number') {
        monthlyQuantity = med.monthlyQuantity;
      } 
      // إذا كانت monthlyQuantity نصية مثل "30 حبة"
      else if (typeof med.monthlyQuantity === 'string') {
        const match = med.monthlyQuantity.match(/(\d+)/);
        monthlyQuantity = match ? parseInt(match[1]) : 0;
      }
      // إذا كان dosage موجوداً، احسب منه
      else if (med.dosage) {
        const match = med.dosage.match(/(\d+(?:\.\d+)?)/);
        if (match) {
          const dailyQty = parseFloat(match[1]);
          monthlyQuantity = Math.round(dailyQty * 30);
        }
      }
      
      // التأكد من وجود قيمة صالحة
      if (monthlyQuantity <= 0) {
        monthlyQuantity = 30; // قيمة افتراضية
      }
      
      return {
        drugId: med.id || med.drugId || null,
        drugName: med.drugName || med.name || '',
        dosage: med.dosage || `${monthlyQuantity / 30} حبة يومياً`,
        monthlyQuantity: monthlyQuantity,
        note: med.note || null
      };
    });
    
    const response = await api.put(`/patients/${patientId}/medications`, {
      medications: medicationsPayload
    });
    
    // BaseApiController يُرجع البيانات في response.data.data
    const updatedData = response.data.data || response.data;
    
    // إعادة جلب البيانات المحدثة من API
    if (updatedData && updatedData.medications) {
      return updatedData;
    }
    
    // إذا لم تكن البيانات كاملة، إعادة الجلب
    return await fetchPatientDetails(patientId);
  } catch (err) {
    console.error('خطأ في تحديث أدوية المريض:', err);
    const errorData = err.response?.data;
    
    if (err.response?.status === 422) {
      throw new Error("بيانات الأدوية غير صالحة.");
    } else if (errorData?.message) {
      throw new Error(errorData.message);
    } else {
      throw new Error("حدث خطأ في تحديث أدوية المريض.");
    }
  }
};

// جلب سجل الصرف
const fetchDispensationHistory = async (patientId) => {
  try {
    const response = await api.get(`/patients/${patientId}/dispensation-history`);
    // BaseApiController يُرجع البيانات في response.data.data
    return response.data.data || response.data || [];
  } catch (err) {
    console.error('خطأ في جلب سجل الصرف:', err);
    const errorData = err.response?.data;
    
    if (err.response?.status === 404) {
      showWarningAlert(" لا يوجد سجل صرف لهذا المريض.");
    } else if (errorData?.message) {
      showErrorAlert(` ${errorData.message}`);
    } else {
      showErrorAlert(" حدث خطأ في جلب سجل الصرف.");
    }
    
    // في حالة الخطأ، نرجع مصفوفة فارغة
    return [];
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

// دالة لتنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return 'غير محدد';
    
    // إذا كان التاريخ بصيغة ISO string (مثل 2021-10-17T00:00:00.000000Z)
    if (dateString.includes('T')) {
        try {
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}/${month}/${day}`;
        } catch (e) {
            return dateString;
        }
    }
    
    // إذا كان التاريخ بصيغة Y/m/d أو Y-m-d
    if (dateString.includes('/') || dateString.includes('-')) {
        // تحويل من Y-m-d إلى Y/m/d
        return dateString.replace(/-/g, '/');
    }
    
    return dateString;
};

const calculateAge = (birthDateString) => {
    if (!birthDateString) return 0;
    
    // معالجة صيغة ISO string
    let dateStr = birthDateString;
    if (birthDateString.includes('T')) {
        try {
            const date = new Date(birthDateString);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            dateStr = `${year}/${month}/${day}`;
        } catch (e) {
            return 0;
        }
    }
    
    const parts = dateStr.split('/');
    if (parts.length !== 3) return 0;

    const birthDate = new Date(parts[0], parts[1] - 1, parts[2]);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();

    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
};

// دالة لمسح فلتر التاريخ
const clearDateFilter = () => {
    dateFrom.value = "";
    dateTo.value = "";
};

const sortPatients = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredPatients = computed(() => {
    // البحث يتم الآن عبر API (server-side)، لذلك نستخدم البيانات كما هي
    let list = [...patients.value];

    // فلترة حسب التاريخ (تاريخ الميلاد)
    if (dateFrom.value || dateTo.value) {
        list = list.filter((patient) => {
            const birthDate = patient.birth || patient.birthDisplay || patient.birth_date;
            if (!birthDate) return false;

            // تحويل تاريخ الميلاد إلى Date object
            let birthDateObj;
            try {
                // معالجة صيغة ISO string
                if (birthDate.includes('T')) {
                    birthDateObj = new Date(birthDate);
                } else if (birthDate.includes('/')) {
                    // معالجة صيغة Y/m/d
                    const parts = birthDate.split('/');
                    if (parts.length === 3) {
                        birthDateObj = new Date(parts[0], parts[1] - 1, parts[2]);
                    } else {
                        return false;
                    }
                } else if (birthDate.includes('-')) {
                    // معالجة صيغة Y-m-d
                    birthDateObj = new Date(birthDate);
                } else {
                    return false;
                }

                if (isNaN(birthDateObj.getTime())) return false;
            } catch {
                return false;
            }

            birthDateObj.setHours(0, 0, 0, 0); // إزالة الوقت للمقارنة

            let matchesFrom = true;
            let matchesTo = true;

            if (dateFrom.value) {
                const fromDate = new Date(dateFrom.value);
                fromDate.setHours(0, 0, 0, 0);
                matchesFrom = birthDateObj >= fromDate;
            }

            if (dateTo.value) {
                const toDate = new Date(dateTo.value);
                toDate.setHours(23, 59, 59, 999); // نهاية اليوم
                matchesTo = birthDateObj <= toDate;
            }

            return matchesFrom && matchesTo;
        });
    }

    // فقط الفرز يتم على العميل (client-side)
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === 'name') {
                comparison = a.name.localeCompare(b.name, 'ar');
            } else if (sortKey.value === 'birth') {
                const ageA = calculateAge(a.birth);
                const ageB = calculateAge(b.birth);
                comparison = ageA - ageB;
            } else if (sortKey.value === 'lastUpdated') {
                const dateA = new Date(a.lastUpdated);
                const dateB = new Date(b.lastUpdated);
                comparison = dateA.getTime() - dateB.getTime();
            }

            return sortOrder.value === 'asc' ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 5. نظام التنبيهات المطور (Toast System)
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
// 6. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isAddMedicationModalOpen = ref(false);
const isDispensationModalOpen = ref(false);
const selectedPatient = ref({});
const dispensationHistory = ref([]);

// ----------------------------------------------------
// 7. دوال فتح وإغلاق الـ Modals
// ----------------------------------------------------
const openViewModal = async (patient) => {
  // استخدام fileNumber أو id كمعرف المريض
  const patientId = patient.fileNumber || patient.id || patient.file_number;
  
  try {
    if (patientId) {
      // جلب البيانات المحدثة للمريض من API
      const patientData = await fetchPatientDetails(patientId);
      if (patientData) {
        selectedPatient.value = {
        ...patientData,
        nameDisplay: patientData.name || patientData.nameDisplay || 'غير متوفر',
      };
        isViewModalOpen.value = true;
      }
    } else {
      // إذا لم يكن هناك معرف، نستخدم البيانات المحلية فقط
      selectedPatient.value = {
        ...patient,
        fileNumber: patient.fileNumber || patient.file_number || patient.id,
        nameDisplay: patient.name || patient.nameDisplay || 'غير متوفر',
        nationalIdDisplay: patient.nationalId || patient.nationalIdDisplay || patient.national_id || 'غير متوفر',
        birthDisplay: formatDate(patient.birth || patient.birthDisplay || patient.birth_date || 'غير متوفر'),
        medications: (patient.medications || []).map(med => ({
          ...med,
          assignmentDate: formatDate(med.assignmentDate || med.assignment_date || med.created_at)
        }))
      };
      isViewModalOpen.value = true;
    }
  } catch (err) {
    // في حالة الخطأ، نستخدم البيانات المحلية مع إضافة خصائص العرض
    selectedPatient.value = {
      ...patient,
      fileNumber: patient.fileNumber || patient.file_number || patient.id || patientId,
      nameDisplay: patient.name || patient.nameDisplay || 'غير متوفر',
      nationalIdDisplay: patient.nationalId || patient.nationalIdDisplay || patient.national_id || 'غير متوفر',
      birthDisplay: formatDate(patient.birth || patient.birthDisplay || patient.birth_date || 'غير متوفر'),
      medications: (patient.medications || []).map(med => ({
        ...med,
        assignmentDate: formatDate(med.assignmentDate || med.assignment_date || med.created_at)
      }))
    };
    isViewModalOpen.value = true;
  }
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedPatient.value = {};
};

const openAddMedicationModal = () => {
    isAddMedicationModalOpen.value = true;
    isViewModalOpen.value = false;
};

const closeAddMedicationModal = () => {
    isAddMedicationModalOpen.value = false;
    isViewModalOpen.value = true;
};

const openDispensationModal = async () => {
  try {
    // جلب سجل الصرف من API
    const history = await fetchDispensationHistory(selectedPatient.value.fileNumber);
    dispensationHistory.value = history;
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
  } catch (err) {
    // في حالة الخطأ، نعرض سجل فارغ
    dispensationHistory.value = [];
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
  }
};

const closeDispensationModal = () => {
    isDispensationModalOpen.value = false;
    isViewModalOpen.value = true;
};

// ----------------------------------------------------
// 8. دوال إدارة الأدوية (محدثة للتعامل مع API)
// ----------------------------------------------------
// دالة لجلب اسم المستخدم الحالي
const getCurrentUserName = () => {
  try {
    const userDataStr = localStorage.getItem('user_data');
    if (userDataStr) {
      const userData = JSON.parse(userDataStr);
      return userData.full_name || userData.name || 'غير محدد';
    }
  } catch (err) {
    console.error('خطأ في جلب بيانات المستخدم:', err);
  }
  return 'غير محدد';
};

const addMedicationToPatient = async (medicationsData) => {
  try {
    showSuccessAlert('جاري الاضافة...');

    // تحويل البيانات إلى التنسيق الذي يتوقعه الـ API (مثل doctor/patients)
    const medicationsPayload = medicationsData.map(med => {
      // تحويل الجرعة اليومية إلى شهرية
      const dailyQty = med.quantity || med.dailyQuantity || 0;
      const monthlyQuantity = Math.round(dailyQty * 30);
      
      // بناء payload - فقط أضف daily_quantity إذا كانت موجودة وقيمة أكبر من 0
      const payload = {
        drug_id: med.drugId || med.id,
        quantity: monthlyQuantity, // الكمية الشهرية
      };
      
      // إضافة daily_quantity فقط إذا كانت موجودة وقيمة أكبر من 0
      if (dailyQty && dailyQty > 0) {
        payload.daily_quantity = Math.round(dailyQty);
      }
      
      return payload;
    });

    try {
      // استخدام POST /patients/{id}/medications (store) مثل doctor/patients
      await api.post(`/patients/${selectedPatient.value.fileNumber}/medications`, {
        medications: medicationsPayload
      });

      // إعادة جلب بيانات المريض المحدثة من API
      const freshPatientData = await fetchPatientDetails(selectedPatient.value.fileNumber);
      if (freshPatientData) {
        selectedPatient.value = freshPatientData;
        
        // تحديث البيانات المحلية
        const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
        if (patientIndex !== -1) {
          patients.value[patientIndex] = freshPatientData;
        }
      }

      showSuccessAlert(`تم إضافة ${medicationsData.length} دواء بنجاح للمريض ${selectedPatient.value.nameDisplay || selectedPatient.value.name}`);
    } catch (apiError) {
      console.error('خطأ في إضافة الأدوية:', apiError);
      const errorData = apiError.response?.data;
      
      if (apiError.response?.status === 422) {
        showErrorAlert("بيانات الأدوية غير صالحة. يرجى التحقق من المعلومات المدخلة.");
      } else if (apiError.response?.status === 404) {
        showWarningAlert("المريض غير موجود أو تم حذفه.");
      } else if (apiError.response?.status === 403) {
        showWarningAlert("ليس لديك صلاحية لإضافة أدوية لهذا المريض.");
      } else if (errorData?.message) {
        showErrorAlert(` ${errorData.message}`);
      } else {
        showErrorAlert("حدث خطأ في إضافة الأدوية. الرجاء المحاولة مرة أخرى.");
      }
    }
  } catch (err) {
    console.error('خطأ في إضافة الأدوية:', err);
    showErrorAlert(" حدث خطأ في إضافة الأدوية. الرجاء المحاولة مرة أخرى.");
  }
};

const handleEditMedication = async (medIndex, newDosage) => {
  try {
    showSuccessAlert('جاري التعديل...');

    const medication = selectedPatient.value.medications[medIndex];
    const pivotId = medication.pivot_id || medication.id;

    if (!pivotId) {
      showWarningAlert("لا يمكن تحديد معرف الدواء للتعديل.");
      return;
    }

    // تحويل الجرعة اليومية إلى شهرية (الـ API يتوقع monthly_quantity)
    const monthlyQuantity = Math.round(newDosage * 30);

    if (monthlyQuantity <= 0) {
      showErrorAlert("الكمية الشهرية يجب أن تكون أكبر من الصفر.");
      return;
    }

    const medicationPayload = {
      dosage: monthlyQuantity, // API يتوقع integer
      daily_quantity: Math.round(newDosage)
    };

    try {
      // تحديث الدواء مباشرة باستخدام API
      await api.put(`/patients/${selectedPatient.value.fileNumber}/medications/${pivotId}`, medicationPayload);

      // إعادة جلب بيانات المريض المحدثة
      const updatedPatient = await fetchPatientDetails(selectedPatient.value.fileNumber);
      if (updatedPatient) {
        selectedPatient.value = updatedPatient;
        
        // تحديث البيانات المحلية
        const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
        if (patientIndex !== -1) {
          patients.value[patientIndex] = updatedPatient;
        }
      }

      showSuccessAlert(`تم تعديل الجرعة الدوائية بنجاح`);
    } catch (apiError) {
      console.error('خطأ في تعديل الدواء:', apiError);
      showErrorAlert(`فشل في تعديل الدواء: ${apiError.response?.data?.message || apiError.message}`);
    }
  } catch (err) {
    console.error('خطأ في تعديل الدواء:', err);
    showErrorAlert("حدث خطأ في تعديل الدواء. الرجاء المحاولة مرة أخرى.");
  }
};

const handleDeleteMedication = async (medIndex) => {
  try {
    showSuccessAlert('جاري الحذف...');

    const medication = selectedPatient.value.medications[medIndex];
    const medicationName = medication.drugName || medication.name;
    const pivotId = medication.pivot_id || medication.id;

    if (!pivotId) {
      showWarningAlert("لا يمكن تحديد معرف الدواء للحذف.");
      return;
    }

    try {
      // حذف الدواء مباشرة من API
      await api.delete(`/patients/${selectedPatient.value.fileNumber}/medications/${pivotId}`);

      // إعادة جلب بيانات المريض المحدثة
      const updatedPatient = await fetchPatientDetails(selectedPatient.value.fileNumber);
      if (updatedPatient) {
        selectedPatient.value = updatedPatient;
        
        // تحديث البيانات المحلية
        const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
        if (patientIndex !== -1) {
          patients.value[patientIndex] = updatedPatient;
        }
      }

      showSuccessAlert(`تم حذف الدواء "${medicationName}" بنجاح`);
    } catch (apiError) {
      console.error('خطأ في حذف الدواء:', apiError);
      const errorData = apiError.response?.data;
      
      if (apiError.response?.status === 404) {
        showWarningAlert("الدواء غير موجود أو تم حذفه مسبقاً.");
      } else if (apiError.response?.status === 403) {
        showWarningAlert("ليس لديك صلاحية لحذف هذا الدواء.");
      } else if (errorData?.message) {
        showErrorAlert(` ${errorData.message}`);
      } else {
        showErrorAlert("حدث خطأ في حذف الدواء. الرجاء المحاولة مرة أخرى.");
      }
    }
  } catch (err) {
    console.error('خطأ في حذف الدواء:', err);
    showErrorAlert(" حدث خطأ في حذف الدواء. الرجاء المحاولة مرة أخرى.");
  }
};

// ----------------------------------------------------
// 9. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredPatients.value.length;

    const printWindow = window.open('', '_blank', 'height=600,width=800');

    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showErrorAlert("فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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
                    </tr>
                </thead>
                <tbody>
        `;

        filteredPatients.value.forEach(patient => {
            tableHtml += `
                <tr>
                    <td>${patient.fileNumber}</td>
                    <td>${patient.name}</td>
                    <td>${patient.nationalId}</td>
                    <td>${patient.birth}</td>
                    <td>${patient.phone}</td>
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
            showSuccessAlert("تم تجهيز التقرير بنجاح للطباعة.");
        } else {
            showSuccessAlert("تم فتح نافذة الطباعة ولكن الجدول فارغ.");
        }
    };
};

// ----------------------------------------------------
// 10. دورة حياة المكون
// ----------------------------------------------------
// دعم البحث مع API (debounced)
let searchTimeout = null;
watch(searchTerm, (newValue) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  // انتظار 500ms بعد توقف المستخدم عن الكتابة قبل استدعاء API
  searchTimeout = setTimeout(() => {
    fetchPatients(newValue);
  }, 500);
});

onMounted(() => {
  fetchPatients();
});

// إعادة تحميل البيانات عند الحاجة
const reloadData = () => {
  fetchPatients(searchTerm.value);
};
</script>

<template>
   <DefaultLayout>
            <main class="flex-1 p-4 sm:p-5 pt-3">
            
                <!-- المحتوى الرئيسي -->
                <div>
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-2 gap-3 sm:gap-0">
                        <div class="flex flex-col sm:flex-row justify-between items-center  gap-3 sm:gap-0">
                            <div class="flex items-center gap-3 w-full sm:max-w-xl">
                                <search v-model="searchTerm" />
                            
                            <!-- زر إظهار/إخفاء فلتر التاريخ -->
                            <button
                                @click="showDateFilter = !showDateFilter"
                                class="h-11 w-18 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
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
                                <div tabindex="0" role="button" class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23
                    rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden
                    text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                                    <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
                                    فرز
                                </div>
                                <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] border-[#ffffff8d]
                    rounded-[35px] w-52 text-right">
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
                            <p class="text-sm font-semibold text-gray-600 self-end sm:self-center">
                                عدد النتائج :
                                <span class="text-[#4DA1A9] text-lg font-bold">{{ filteredPatients.length }}</span>
                            </p>
                        </div>
                        </div>
                        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
                            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                                <btnprint @click="printTable" />
                            
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col">
                        <div
                            class="overflow-y-auto flex-1"
                            style="
                                scrollbar-width: auto;
                                scrollbar-color: grey transparent;
                                direction: ltr;
                            "
                        >
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

                                <tbody>
                                    <tr v-if="isLoading">
                                        <td colspan="6" class="p-4">
                                            <TableSkeleton :rows="5" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="hasError">
                                        <td colspan="6" class="py-12">
                                            <ErrorState :message="errorMessage || 'تعذر تحميل بيانات المرضى من الخادم. الرجاء التحقق من اتصال الإنترنت والمحاولة مرة أخرى.'" :retry="fetchPatients" />
                                        </td>
                                    </tr>
                                    <template v-else>
                                        <tr 
                                            v-for="(patient, index) in filteredPatients"
                                            :key="index"
                                            class="hover:bg-gray-100 border border-gray-300 transition-colors duration-150"
                                        >
                                            <td class="file-number-col">{{ patient.fileNumber }}</td>
                                            <td class="name-col">{{ patient.name }}</td>
                                            <td class="national-id-col">{{ patient.nationalId }}</td>
                                            <td class="birth-date-col">{{ formatDate(patient.birth) }}</td>
                                            <td class="phone-col">{{ patient.phone }}</td>

                                            <td class="actions-col">
                                                <div class="flex gap-3 justify-center">
                                                    <button @click="openViewModal(patient)" class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95">
                                                        <Icon
                                                            icon="famicons:open-outline"
                                                            class="w-4 h-4 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="filteredPatients.length === 0">
                                            <td colspan="6" class="py-12">
                                                <EmptyState message="لا توجد بيانات لعرضها" />
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
        @add-medication="openAddMedicationModal"
        @dispensation-record="openDispensationModal"
        @edit-medication="handleEditMedication"
        @delete-medication="handleDeleteMedication"
    />

    <AddMedicationModal
        :is-open="isAddMedicationModalOpen"
        :patient="selectedPatient"
        @close="closeAddMedicationModal"
        @save="addMedicationToPatient"
    />

    <DispensationModal
        :is-open="isDispensationModalOpen"
        :patient="selectedPatient"
        :dispensation-history="dispensationHistory"
        @close="closeDispensationModal"
    />

    <Toast
        :show="isAlertVisible"
        :message="alertMessage"
        :type="alertType"
        @close="isAlertVisible = false"
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
成功