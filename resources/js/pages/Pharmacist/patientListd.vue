<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

// استيراد المكونات المنفصلة
import PatientViewModal from "@/components/forpharmacist/PatientViewModal.vue";
import AddMedicationModal from "@/components/patientDoctor/AddMedicationModal.vue";
import DispensationModal from "@/components/patientDoctor/DispensationModal.vue";

// ----------------------------------------------------
// 1. تكوين Axios
// ----------------------------------------------------
const API_BASE_URL = "https://api.your-domain.com"; // استبدل بالرابط الفعلي
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
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
// 2. بيانات المرضى - أصبحت تأتي من API
// ----------------------------------------------------
const patients = ref([  {
        fileNumber: 1001,
        name: 'علي محمد سالم العلواني',
        nationalId: '123456789012',
        birth: '1967/07/22',
        phone: '0911234567',
        lastUpdated: new Date('2024-01-15T10:00:00').toISOString(),
        nationalIdDisplay: '12369852793',
        nameDisplay: 'علي محمد سالم العلواني',
        birthDisplay: '22/07/1967',
        healthCenter: 'مركز طرابلس الجامعي',
        medications: [
            { drugName: 'Metformin', dosage: 'قرص واحد', monthlyQuantity: '30 حبة', assignmentDate: '2020/10/20', expirationDate: '2022/10/20', assignedBy: 'د. محمد خالد' },
            { drugName: 'Amlodipine', dosage: 'قرصين', monthlyQuantity: '60 حبة', assignmentDate: '2020/10/20', expirationDate: '2025/10/20', assignedBy: 'د. محمد خالد' },
            { drugName: 'Gliclazide', dosage: 'قرص واحد', monthlyQuantity: '30 حبة', assignmentDate: '2025/10/23', expirationDate: '2025/10/23', assignedBy: 'د. لجين أبوجناح' },
        ]
    },]);
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
    const response = await api.get('/api/patients');
    patients.value = response.data.map(patient => ({
      ...patient,
      lastUpdated: new Date(patient.lastUpdated).toISOString()
    }));
  } catch (err) {
    hasError.value = true;
    errorMessage.value = err.response?.data?.message || 'حدث خطأ في جلب بيانات المرضى';
    
    
  } finally {
    isLoading.value = false;
  }
};

// جلب بيانات مريض محدد
const fetchPatientDetails = async (patientId) => {
  try {
    const response = await api.get(`/api/patients/${patientId}`);
    return response.data;
  } catch (err) {
    // لا نعرض خطأ، نرجع بيانات افتراضية
    return {
      ...selectedPatient.value,
      medications: []
    };
  }
};

// تحديث بيانات المريض (بعد إضافة/تعديل/حذف دواء)
const updatePatientMedications = async (patientId, medications) => {
  try {
    const response = await api.put(`/api/patients/${patientId}/medications`, {
      medications
    });
    return response.data;
  } catch (err) {
    // في حالة الخطأ، نرجع البيانات المحلية المحدثة
    const patientIndex = patients.value.findIndex(p => p.fileNumber === patientId);
    if (patientIndex !== -1) {
      patients.value[patientIndex].medications = medications;
      return patients.value[patientIndex];
    }
    throw err;
  }
};

// جلب سجل الصرف
const fetchDispensationHistory = async (patientId) => {
  try {
    const response = await api.get(`/api/patients/${patientId}/dispensation-history`);
    return response.data;
  } catch (err) {
    // في حالة الخطأ، نرجع مصفوفة فارغة
    return [];
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
    // جلب البيانات المحدثة للمريض من API
    const patientData = await fetchPatientDetails(patient.fileNumber);
    if (patientData) {
      selectedPatient.value = patientData;
      isViewModalOpen.value = true;
    }
  } catch (err) {
    // في حالة الخطأ، نستخدم البيانات المحلية
    selectedPatient.value = patient;
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
const addMedicationToPatient = async (medicationsData) => {
  try {
    const newMedications = medicationsData.map(med => ({
      drugName: med.name,
      dosage: `${med.quantity} ${med.unit} يومياً`,
      monthlyQuantity: `${med.quantity * 30} ${med.unit === 'حبة/قرص' ? 'حبة' : med.unit}`,
      assignmentDate: new Date().toISOString().split('T')[0].replace(/-/g, '/'),
      expirationDate: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0].replace(/-/g, '/'),
      assignedBy: 'د. المستخدم الحالي'
    }));

    try {
      // محاولة تحديث الأدوية في الـ API
      const updatedPatient = await updatePatientMedications(
        selectedPatient.value.fileNumber,
        [...selectedPatient.value.medications, ...newMedications]
      );

      // تحديث البيانات المحلية
      const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
      if (patientIndex !== -1) {
        patients.value[patientIndex].medications = updatedPatient.medications;
        selectedPatient.value = patients.value[patientIndex];
      }

      showSuccessAlert(`✅ تم إضافة ${newMedications.length} دواء بنجاح للمريض ${selectedPatient.value.nameDisplay}`);
    } catch (apiError) {
      // في حالة فشل الاتصال بالـ API، نحدث البيانات محليًا فقط
      const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
      if (patientIndex !== -1) {
        if (!patients.value[patientIndex].medications) {
          patients.value[patientIndex].medications = [];
        }
        patients.value[patientIndex].medications = [
          ...patients.value[patientIndex].medications,
          ...newMedications
        ];
        selectedPatient.value = patients.value[patientIndex];
      }
      
      showInfoAlert(`تم إضافة الأدوية محليًا (غير متصل بالخادم)`);
    }
  } catch (err) {
    showInfoAlert('تم إضافة الأدوية محليًا');
  }
};

const handleEditMedication = async (medIndex, newDosage) => {
  try {
    const updatedMedications = [...selectedPatient.value.medications];
    updatedMedications[medIndex] = {
      ...updatedMedications[medIndex],
      dosage: newDosage.toString(),
      monthlyQuantity: `${newDosage * 30} حبة`
    };

    try {
      // محاولة التحديث في الـ API
      const updatedPatient = await updatePatientMedications(
        selectedPatient.value.fileNumber,
        updatedMedications
      );

      // تحديث البيانات المحلية
      const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
      if (patientIndex !== -1) {
        patients.value[patientIndex].medications = updatedPatient.medications;
        selectedPatient.value = patients.value[patientIndex];
      }

      showSuccessAlert(`✅ تم تعديل الجرعة الدوائية بنجاح`);
    } catch (apiError) {
      // في حالة فشل الاتصال، نحدث البيانات محليًا
      const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
      if (patientIndex !== -1) {
        patients.value[patientIndex].medications = updatedMedications;
        selectedPatient.value = patients.value[patientIndex];
      }
      
      showInfoAlert(`تم التعديل محليًا (غير متصل بالخادم)`);
    }
  } catch (err) {
    showInfoAlert('تم التعديل محليًا');
  }
};

const handleDeleteMedication = async (medIndex) => {
  try {
    const updatedMedications = [...selectedPatient.value.medications];
    const medicationName = updatedMedications[medIndex].drugName;
    updatedMedications.splice(medIndex, 1);

    try {
      // محاولة التحديث في الـ API
      const updatedPatient = await updatePatientMedications(
        selectedPatient.value.fileNumber,
        updatedMedications
      );

      // تحديث البيانات المحلية
      const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
      if (patientIndex !== -1) {
        patients.value[patientIndex].medications = updatedPatient.medications;
        selectedPatient.value = patients.value[patientIndex];
      }

      showSuccessAlert(`🗑️ تم حذف الدواء ${medicationName} بنجاح`);
    } catch (apiError) {
      // في حالة فشل الاتصال، نحدث البيانات محليًا
      const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
      if (patientIndex !== -1) {
        patients.value[patientIndex].medications = updatedMedications;
        selectedPatient.value = patients.value[patientIndex];
      }
      
      showInfoAlert(`تم الحذف محليًا (غير متصل بالخادم)`);
    }
  } catch (err) {
    showInfoAlert('تم الحذف محليًا');
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