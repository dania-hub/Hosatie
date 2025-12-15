<script setup>
import { ref, computed, onMounted } from "vue";
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

// ----------------------------------------------------
// 1. تكوين Axios
// ----------------------------------------------------
const API_BASE_URL = "/api/doctor"; // استخدام API الخاص بالدكتور
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
    const token = localStorage.getItem('auth_token');
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
// 2. بيانات المرضى - أصبحت تأتي من API
// ----------------------------------------------------
const patients = ref([ ]);
const isLoading = ref(false);
const hasError = ref(false);
const errorMessage = ref("");

// ----------------------------------------------------
// 3. دوال API
// ----------------------------------------------------
// جلب جميع المرضى
const fetchPatients = async () => {
  isLoading.value = true;
  hasError.value = false;
  errorMessage.value = "";
  
  try {
    const response = await api.get('/patients');
    
    // BaseApiController يُرجع البيانات في response.data.data
    const patientsData = response.data.data || response.data;
    
    // التحقق من وجود بيانات
    if (patientsData && Array.isArray(patientsData)) {
      if (patientsData.length === 0) {
        // لا توجد بيانات في قاعدة البيانات
        patients.value = [];
        showInfoAlert('✅ تم الاتصال بالخادم بنجاح. لا توجد بيانات مرضى حالياً.');
      } else {
        // توجد بيانات - إضافة خصائص العرض
        patients.value = patientsData.map(patient => ({
          ...patient,
          lastUpdated: patient.lastUpdated ? new Date(patient.lastUpdated).toISOString() : new Date().toISOString(),
          // إضافة خصائص العرض للمكونات
          nameDisplay: patient.name || patient.nameDisplay || 'غير متوفر',
          nationalIdDisplay: patient.nationalId || patient.nationalIdDisplay || 'غير متوفر',
          birthDisplay: patient.birth || patient.birthDisplay || 'غير متوفر'
        }));
        showSuccessAlert(`✅ تم تحميل ${patientsData.length} مريض بنجاح`);
      }
    } else {
      // البيانات ليست بالشكل المتوقع
      patients.value = [];
      console.error('شكل البيانات غير متوقع:', response.data);
      showInfoAlert('⚠️ تم الاتصال بالخادم لكن البيانات بصيغة غير متوقعة');
    }
  } catch (err) {
    hasError.value = true;
    console.error('خطأ في جلب بيانات المرضى:', err);
    
    // تحديد نوع الخطأ
    if (err.response) {
      // الخادم رد بخطأ
      const status = err.response.status;
      
      if (status === 401) {
        errorMessage.value = 'خطأ في المصادقة. يرجى تسجيل الدخول مرة أخرى';
        showInfoAlert('🔒 خطأ في المصادقة. يرجى تسجيل الدخول مرة أخرى');
      } else if (status === 403) {
        errorMessage.value = 'ليس لديك صلاحية للوصول إلى هذه البيانات';
        showInfoAlert('🚫 ليس لديك صلاحية للوصول إلى هذه البيانات');
      } else if (status === 404) {
        errorMessage.value = 'المسار غير موجود';
        showInfoAlert('❌ المسار غير موجود. تحقق من إعدادات API');
      } else if (status === 500) {
        errorMessage.value = 'خطأ في الخادم';
        showInfoAlert('⚠️ خطأ في الخادم. يرجى المحاولة لاحقاً');
      } else {
        errorMessage.value = err.response.data?.message || `خطأ ${status}`;
        showInfoAlert(`❌ خطأ: ${errorMessage.value}`);
      }
    } else if (err.request) {
      // الطلب تم إرساله لكن لم يتم استلام رد
      errorMessage.value = 'لا يمكن الاتصال بالخادم';
      showInfoAlert('📡 لا يمكن الاتصال بالخادم. تحقق من اتصال الإنترنت أو تأكد من تشغيل الخادم');
    } else {
      // خطأ في إعداد الطلب
      errorMessage.value = err.message || 'حدث خطأ غير متوقع';
      showInfoAlert(`❌ خطأ: ${errorMessage.value}`);
    }
    
    patients.value = [];
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
      // إضافة خصائص العرض (الـ API يعيد name, nationalId, birth)
      nameDisplay: patientData.name || patientData.nameDisplay || 'غير متوفر',
      nationalIdDisplay: patientData.nationalId || patientData.nationalIdDisplay || 'غير متوفر',
      birthDisplay: patientData.birth || patientData.birthDisplay || 'غير متوفر',
      // تحويل بيانات الأدوية لتتوافق مع ما يتوقعه المكون
      medications: (patientData.medications || []).map(med => {
        // الـ API يعيد dosage كرقم (monthly_quantity)، نحوله إلى نص منسق
        const monthlyQty = med.dosage || med.monthlyQuantity || 0;
        const dailyQty = monthlyQty > 0 ? Math.round((monthlyQty / 30) * 10) / 10 : 0;
        const dosageText = dailyQty > 0 
          ? (dailyQty % 1 === 0 ? dailyQty.toString() : dailyQty.toFixed(1)) + ' حبة يومياً'
          : 'غير محدد';
        
        // استخدام البيانات من API مباشرة
        const assignmentDate = med.assignmentDate || null;
        const assignedBy = med.assignedBy || null;
        
        return {
          ...med,
          dosage: dosageText, // تحويل من رقم إلى نص منسق
          monthlyQuantity: monthlyQty, // الكمية الشهرية من API
          assignmentDate: assignmentDate, // تاريخ الإسناد من API
          assignedBy: assignedBy // اسم الطبيب من API
        };
      })
    };
  } catch (err) {
    // لا نعرض خطأ، نرجع بيانات افتراضية
    return {
      ...selectedPatient.value,
      medications: [],
      nameDisplay: selectedPatient.value.name || selectedPatient.value.nameDisplay || 'غير متوفر',
      nationalIdDisplay: selectedPatient.value.nationalId || selectedPatient.value.nationalIdDisplay || 'غير متوفر',
      birthDisplay: selectedPatient.value.birth || selectedPatient.value.birthDisplay || 'غير متوفر'
    };
  }
};

// جلب سجل الصرف
const fetchDispensationHistory = async (patientId) => {
  try {
    const response = await api.get(`/patients/${patientId}/dispensations`);
    // BaseApiController يُرجع البيانات في response.data.data
    const historyData = response.data.data || response.data;
    
    // تحويل البيانات من API إلى الشكل الذي يتوقعه المكون
    // API يعيد: drug_name, pharmacist
    // المكون يتوقع: drugName, assignedBy
    if (Array.isArray(historyData)) {
      return historyData.map(item => ({
        ...item,
        drugName: item.drug_name || item.drugName,
        assignedBy: item.pharmacist || item.assignedBy,
        date: item.date ? item.date.split(' ')[0] : item.date // إزالة الوقت من التاريخ
      }));
    }
    
    return [];
  } catch (err) {
    console.error('خطأ في جلب سجل الصرف:', err);
    // في حالة الخطأ، نرجع مصفوفة فارغة
    return [];
  }
};

// ----------------------------------------------------
// 4. دوال إدارة الأدوية (API)
// ----------------------------------------------------
// إضافة دواء جديد للمريض
const addMedicationToPatientAPI = async (patientId, medicationData) => {
  try {
    const response = await api.post(`/patients/${patientId}/medications`, medicationData);
    return response.data;
  } catch (err) {
    throw err;
  }
};

// تحديث دواء موجود
const updateMedicationAPI = async (patientId, pivotId, medicationData) => {
  try {
    const response = await api.put(`/patients/${patientId}/medications/${pivotId}`, medicationData);
    return response.data;
  } catch (err) {
    throw err;
  }
};

// حذف دواء
const deleteMedicationAPI = async (patientId, pivotId) => {
  try {
    const response = await api.delete(`/patients/${patientId}/medications/${pivotId}`);
    return response.data;
  } catch (err) {
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
    const parts = birthDateString.split('/');
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

const sortPatients = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredPatients = computed(() => {
    let list = patients.value;
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(patient =>
            patient.fileNumber.toString().includes(search) ||
            patient.name.toLowerCase().includes(search) ||
            patient.nationalId.includes(search) ||
            patient.birth.includes(search) ||
            patient.phone.includes(search)
        );
    }

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
const isAddMedicationModalOpen = ref(false);
const isDispensationModalOpen = ref(false);
const selectedPatient = ref({});
const dispensationHistory = ref([]);

// ----------------------------------------------------
// 7. دوال فتح وإغلاق الـ Modals
// ----------------------------------------------------
const openViewModal = async (patient) => {
  try {
    const patientData = await fetchPatientDetails(patient.fileNumber);
    if (patientData) {
      // تحويل البيانات إلى الشكل المتوقع للمكون
      selectedPatient.value = {
        ...patientData,
        // إضافة خصائص العرض
        nameDisplay: patientData.name || patientData.nameDisplay || 'غير متوفر',
        nationalIdDisplay: patientData.nationalId || patientData.nationalIdDisplay || 'غير متوفر',
        birthDisplay: patientData.birth || patientData.birthDisplay || 'غير متوفر',
        // تحويل الأدوية إلى الشكل المتوقع
        medications: (patientData.medications || []).map(med => {
          // الـ API يعيد dosage كرقم (monthly_quantity)، نحوله إلى نص منسق
          const monthlyQty = med.dosage || med.monthlyQuantity || 0;
          const dailyQty = monthlyQty > 0 ? Math.round((monthlyQty / 30) * 10) / 10 : 0;
          const dosageText = dailyQty > 0 
            ? (dailyQty % 1 === 0 ? dailyQty.toString() : dailyQty.toFixed(1)) + ' حبة يومياً'
            : 'غير محدد';
          
          return {
            ...med,
            // إضافة الحقول المطلوبة للمكون
            monthlyQuantity: monthlyQty, // الكمية الشهرية من API
            assignmentDate: med.assignmentDate || new Date().toISOString().split('T')[0].replace(/-/g, '/'),
            assignedBy: med.assignedBy || 'غير محدد',
            // التأكد من وجود الحقول الأساسية
            drugName: med.drugName || med.name || 'غير محدد',
            dosage: dosageText, // تحويل من رقم إلى نص منسق
            note: med.note || med.notes || ''
          };
        })
      };
      console.log('selectedPatient after API:', selectedPatient.value); // إضافة للتحقق
      isViewModalOpen.value = true;
    }
  } catch (err) {
    // في حالة الخطأ، نستخدم البيانات المحلية مع تحويلها
    selectedPatient.value = {
      ...patient,
      nameDisplay: patient.name || patient.nameDisplay || 'غير متوفر',
      nationalIdDisplay: patient.nationalId || patient.nationalIdDisplay || 'غير متوفر',
      birthDisplay: patient.birth || patient.birthDisplay || 'غير متوفر',
      medications: (patient.medications || []).map(med => ({
        ...med,
        monthlyQuantity: med.dosage || med.monthlyQuantity || 'غير محدد',
        assignmentDate: med.assignmentDate || new Date().toISOString().split('T')[0].replace(/-/g, '/'),
        assignedBy: med.assignedBy || 'غير محدد',
        drugName: med.drugName || med.name || 'غير محدد',
        dosage: med.dosage || med.monthlyQuantity || 'غير محدد',
        note: med.note || med.notes || ''
      }))
    };
    console.log('selectedPatient from local data:', selectedPatient.value); // إضافة للتحقق
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
    const history = await fetchDispensationHistory(selectedPatient.value.fileNumber);
    dispensationHistory.value = history;
    console.log('dispensationHistory:', dispensationHistory.value); // إضافة للتحقق
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
  } catch (err) {
    dispensationHistory.value = [];
    console.log('dispensationHistory (empty):', dispensationHistory.value); // إضافة للتحقق
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
const addMedicationToPatient = async (medicationsData) => {
  try {
    // تحويل البيانات إلى التنسيق الذي يتوقعه الـ API
    // الـ API يتوقع: medications array مع drug_id, quantity (monthly), note
    const medicationsPayload = medicationsData.map(med => {
      // تحويل الجرعة اليومية إلى شهرية
      const dailyQty = med.dailyQuantity || med.quantity || 0;
      const monthlyQuantity = Math.round(dailyQty * 30);
      
      return {
        drug_id: med.drugId || med.id,
        quantity: monthlyQuantity, // الكمية الشهرية
        note: med.note || med.notes || null
      };
    });

    try {
      // إرسال جميع الأدوية مرة واحدة كـ array
      await addMedicationToPatientAPI(selectedPatient.value.fileNumber, { medications: medicationsPayload });

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

      showSuccessAlert(`✅ تم إضافة ${medicationsData.length} دواء بنجاح للمريض ${selectedPatient.value.name}`);
    } catch (apiError) {
      console.error('خطأ في إضافة الأدوية:', apiError);
      const errorMessage = apiError.response?.data?.message || apiError.message || 'حدث خطأ غير معروف';
      showInfoAlert(`فشل في إضافة الأدوية: ${errorMessage}`);
    }
  } catch (err) {
    console.error('خطأ في إضافة الأدوية:', err);
    showInfoAlert('حدث خطأ في إضافة الأدوية');
  }
};

const handleEditMedication = async (medIndex, newDosage) => {
  try {
    const medication = selectedPatient.value.medications[medIndex];
    const pivotId = medication.pivot_id || medication.id;

    if (!pivotId) {
      showInfoAlert('خطأ: لا يمكن تحديد معرف الدواء للتعديل');
      return;
    }

    // تحويل الجرعة اليومية إلى شهرية (الـ API يتوقع monthly_quantity)
    const monthlyQuantity = Math.round(newDosage * 30);

    if (monthlyQuantity <= 0) {
      showInfoAlert('خطأ: الكمية الشهرية يجب أن تكون أكبر من الصفر');
      return;
    }

    const medicationPayload = {
      dosage: monthlyQuantity // API يتوقع integer
    };

    try {
      // محاولة التحديث في الـ API
      await updateMedicationAPI(
        selectedPatient.value.fileNumber,
        pivotId,
        medicationPayload
      );

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

      showSuccessAlert(`✅ تم تعديل الجرعة الدوائية بنجاح`);
    } catch (apiError) {
      console.error('خطأ في تعديل الدواء:', apiError);
      showInfoAlert(`فشل في تعديل الدواء: ${apiError.response?.data?.message || apiError.message}`);
    }
  } catch (err) {
    console.error('خطأ في تعديل الدواء:', err);
    showInfoAlert('حدث خطأ في تعديل الدواء');
  }
};

const handleDeleteMedication = async (medIndex) => {
  try {
    const medication = selectedPatient.value.medications[medIndex];
    const medicationName = medication.drugName || medication.drug_name || medication.name;
    const pivotId = medication.pivot_id || medication.id;

    try {
      // محاولة الحذف من الـ API
      await deleteMedicationAPI(
        selectedPatient.value.fileNumber,
        pivotId
      );

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

      showSuccessAlert(`🗑️ تم حذف الدواء ${medicationName} بنجاح`);
    } catch (apiError) {
      console.error('خطأ في حذف الدواء:', apiError);
      showInfoAlert(`فشل في حذف الدواء: ${apiError.response?.data?.message || apiError.message}`);
    }
  } catch (err) {
    console.error('خطأ في حذف الدواء:', err);
    showInfoAlert('حدث خطأ في حذف الدواء');
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

// إعادة تحميل البيانات عند الحاجة
const reloadData = () => {
  fetchPatients();
};
</script>

<template>
    <div class="drawer lg:drawer-open" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />

            <main class="flex-1 p-4 sm:p-5 pt-3">
                <!-- حالة التحميل -->
                <div v-if="isLoading" class="flex justify-center items-center h-64">
                    <div class="text-center">
                        <Icon icon="eos-icons:loading" class="w-12 h-12 text-[#4DA1A9] animate-spin mx-auto" />
                        <p class="mt-4 text-gray-600">جاري تحميل البيانات...</p>
                    </div>
                </div>

                <!-- المحتوى الرئيسي -->
                <div v-else>
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
                        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0">
                            <div class="flex items-center gap-3 w-full sm:max-w-xl">
                                <search v-model="searchTerm" />
                            
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
                                <button 
                                    @click="reloadData" 
                                    class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200"
                                    title="إعادة تحميل البيانات"
                                >
                                    <Icon icon="material-symbols:refresh" class="w-5 h-5 ml-1" />
                                    تحديث
                                </button>
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

                                    <tbody v-if="filteredPatients.length > 0">
                                        <tr
                                            v-for="(patient, index) in filteredPatients"
                                            :key="index"
                                            class="hover:bg-gray-100 border border-gray-300"
                                        >
                                            <td class="file-number-col">{{ patient.fileNumber }}</td>
                                            <td class="name-col">{{ patient.name }}</td>
                                            <td class="national-id-col">{{ patient.nationalId }}</td>
                                            <td class="birth-date-col">{{ patient.birth }}</td>
                                            <td class="phone-col">{{ patient.phone }}</td>

                                            <td class="actions-col">
                                                <div class="flex gap-3 justify-center">
                                                    <button @click="openViewModal(patient)">
                                                        <Icon
                                                            icon="famicons:open-outline"
                                                            class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="6" class="text-center py-8 text-gray-500">
                                                <div class="flex flex-col items-center justify-center">
                                                    <Icon icon="mdi:database-off-outline" class="w-12 h-12 text-gray-300 mb-2" />
                                                    <p class="text-lg font-medium">لا توجد بيانات للعرض</p>
                                                    <p class="text-sm text-gray-400 mt-1">قم بتحديث الصفحة أو تحقق من اتصالك بالإنترنت</p>
                                                    <button 
                                                        @click="reloadData" 
                                                        class="mt-4 inline-flex items-center px-4 py-2 bg-[#4DA1A9] text-white rounded-lg hover:bg-[#3a8c94] transition-colors duration-200"
                                                    >
                                                        <Icon icon="material-symbols:refresh" class="w-5 h-5 ml-1" />
                                                        إعادة تحميل
                                                    </button>
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
        </div>

        <Sidebar />
    </div>

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