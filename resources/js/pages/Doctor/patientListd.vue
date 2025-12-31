<template>
    <div class="drawer lg:drawer-open" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />

            <main class="flex-1 p-4 sm:p-5 pt-3">
                    <!-- عناصر التحكم العلوية -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                        <!-- البحث والفرز -->
                        <div class="flex items-center gap-3 w-full sm:max-w-2xl">
                            <search v-model="searchTerm" placeholder="ابحث بالاسم، رقم الملف، أو الرقم الوطني..." />
                            
                            <!-- زر الفرز -->
                            <div class="dropdown dropdown-start">
                                <div tabindex="0" role="button" class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                                    <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
                                    فرز
                                </div>
                                <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] border-[#ffffff8d] rounded-[35px] w-52 text-right">
                                    <li class="menu-title text-gray-700 font-bold text-sm">حسب الاسم:</li>
                                    <li>
                                        <a @click="sortPatients('name', 'asc')"
                                            :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'asc'}">
                                            من أ - ي
                                        </a>
                                    </li>
                                    <li>
                                        <a @click="sortPatients('name', 'desc')"
                                            :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'desc'}">
                                            من ي - أ
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
                                            الأحدث أولاً
                                        </a>
                                    </li>
                                    <li>
                                        <a @click="sortPatients('lastUpdated', 'asc')"
                                            :class="{'font-bold text-[#4DA1A9]': sortKey === 'lastUpdated' && sortOrder === 'asc'}">
                                            الأقدم أولاً
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            
                            <!-- عدد النتائج -->
                            <div class="text-sm font-semibold text-gray-600">
                                عدد النتائج: 
                                <span class="text-[#4DA1A9] text-lg font-bold">{{ filteredPatients.length }}</span>
                            </div>
                        </div>

                        <!-- أزرار الإجراءات -->
                        <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                            <btnprint @click="printTable" />
                            <button 
                                @click="reloadData" 
                                class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200"
                                title="إعادة تحميل البيانات"
                            >
                                <Icon icon="material-symbols:refresh" class="w-5 h-5 ml-1" />
                                تحديث القائمة
                            </button>
                        </div>
                    </div>

                    <!-- جدول المرضى -->
                    <div class="bg-white rounded-2xl shadow overflow-hidden flex flex-col min-h-[500px]">
                        <div
                            class="overflow-y-auto flex-1"
                            style="scrollbar-width: auto; scrollbar-color: grey transparent; direction: ltr;"
                        >
                            <div class="overflow-x-auto h-full">
                                <table dir="rtl" class="table w-full text-right min-w-[700px] border-collapse">
                                    <thead class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300">
                                        <tr>
                                            <th class="file-number-col">رقم الملف</th>
                                            <th class="name-col">الاسم الرباعي</th>
                                            <th class="national-id-col">الرقم الوطني</th>
                                            <th class="birth-date-col">تاريخ الميلاد</th>
                                            <th class="phone-col">رقم الهاتف</th>
                                            <th class="actions-col">الإجراءات</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr v-if="isLoading">
                                            <td colspan="6" class="p-4">
                                                <TableSkeleton :rows="6" />
                                            </td>
                                        </tr>

                                        <tr v-else-if="hasError">
                                            <td colspan="6" class="py-12">
                                                <ErrorState 
                                                    :message="errorMessage || 'حدث خطأ في تحميل البيانات'" 
                                                    :retry="reloadData" 
                                                />
                                            </td>
                                        </tr>

                                        <template v-else>
                                            <tr
                                                v-for="(patient, index) in filteredPatients"
                                                :key="patient.fileNumber"
                                                class="hover:bg-gray-100 border-b border-gray-200 transition-colors duration-150"
                                            >
                                                <td class="file-number-col font-medium text-gray-700">{{ patient.fileNumber }}</td>
                                                <td class="name-col">{{ patient.name }}</td>
                                                <td class="national-id-col">{{ patient.nationalId }}</td>
                                                <td class="birth-date-col">{{ patient.birth }}</td>
                                                <td class="phone-col">{{ patient.phone }}</td>

                                                <td class="actions-col">
                                                    <div class="flex gap-3 justify-center">
                                                        <button 
                                                            @click="openViewModal(patient)"
                                                            class="tooltip tooltip-bottom"
                                                            data-tip="عرض تفاصيل المريض"
                                                        >
                                                            <Icon
                                                                icon="famicons:open-outline"
                                                                class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <tr v-if="filteredPatients.length === 0">
                                                <td colspan="6" class="py-12">
                                                    <EmptyState 
                                                        :message="searchTerm ? `لم يتم العثور على مرضى مطابقين لـ '${searchTerm}'` : 'لا توجد بيانات مرضى متاحة حالياً'" 
                                                    >
                                                        <template #action v-if="searchTerm">
                                                            <button 
                                                                @click="searchTerm = ''"
                                                                class="mt-4 inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200"
                                                            >
                                                                مسح البحث
                                                            </button>
                                                        </template>
                                                    </EmptyState>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
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

    <!-- التنبيهات -->
    <Transition
        enter-active-class="transition duration-300 ease-out transform"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition duration-200 ease-in transform"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
    >
        <div
            v-if="isAlertVisible"
            class="fixed top-4 right-4 z-[1000] p-4 text-right text-white rounded-lg shadow-xl max-w-sm transition-all duration-300"
            :class="alertTypeClass"
            dir="rtl"
        >
            <div class="flex items-start gap-3">
                <Icon :icon="alertIcon" class="w-5 h-5 mt-0.5 flex-shrink-0" />
                <div class="flex-1">
                    <div v-html="alertMessage" class="font-medium"></div>
                    <div v-if="alertDetails" class="text-sm opacity-90 mt-1">{{ alertDetails }}</div>
                </div>
                <button @click="hideAlert" class="text-white hover:text-gray-200">
                    <Icon icon="mdi:close" class="w-4 h-4" />
                </button>
            </div>
            <div class="mt-2 w-full bg-white bg-opacity-30 h-1 rounded-full overflow-hidden">
                <div class="h-full bg-white progress-bar"></div>
            </div>
        </div>
    </Transition>
</template>

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
const API_BASE_URL = "/api/doctor";
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 15000, // زيادة المهلة
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

// معالجة الأخطاء
api.interceptors.response.use(
  response => response,
  error => {
    if (error.code === 'ECONNABORTED') {
      error.response = { 
        status: 408,
        data: { message: 'انتهت مهلة الاتصال. يرجى المحاولة مرة أخرى' }
      };
    } else if (!error.response) {
      error.response = {
        status: 0,
        data: { message: 'تعذر الاتصال بالخادم. تحقق من اتصال الإنترنت' }
      };
    }
    return Promise.reject(error);
  }
);

// ----------------------------------------------------
// 2. بيانات المرضى
// ----------------------------------------------------
const patients = ref([]);
const isLoading = ref(true);
const hasError = ref(false);
const errorMessage = ref("");

// ----------------------------------------------------
// 3. دوال API
// ----------------------------------------------------
const fetchPatients = async () => {
  isLoading.value = true;
  hasError.value = false;
  errorMessage.value = "";
  
  try {
    showAlert('جاري تحميل بيانات المرضى...', '', 'info');
    
    const response = await api.get('/patients');
    
    const patientsData = response.data.data || response.data;
    
    if (patientsData && Array.isArray(patientsData)) {
      if (patientsData.length === 0) {
        patients.value = [];
        showAlert(
          ' تم الاتصال بالخادم بنجاح',
          'لا توجد بيانات مرضى حالياً. يمكنك إضافة مرضى جدد',
          'info'
        );
      } else {
        patients.value = patientsData.map(patient => ({
          ...patient,
          lastUpdated: patient.lastUpdated ? new Date(patient.lastUpdated).toISOString() : new Date().toISOString(),
          name: patient.name || 'غير متوفر',
          nationalId: patient.nationalId || 'غير متوفر',
          birth: patient.birth || 'غير متوفر',
          phone: patient.phone || 'غير متوفر'
        }));
        
        showAlert(
          `تم تحميل ${patientsData.length} مريض بنجاح`,
          'بيانات المرضى جاهزة للعرض',
          'success'
        );
      }
    } else {
      patients.value = [];
      showAlert(
        ' شكل البيانات غير متوقع',
        'تأكد من صحة استجابة الخادم',
        'warning'
      );
    }
  } catch (err) {
    hasError.value = true;
    
    let alertTitle = 'خطأ في تحميل البيانات';
    let alertMessage = '';
    let alertType = 'error';
    
    if (err.response) {
      const status = err.response.status;
      
      switch(status) {
        case 401:
          alertMessage = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى';
          alertType = 'error';
          break;
        case 403:
          alertMessage = 'ليس لديك صلاحية للوصول إلى هذه البيانات';
          alertType = 'warning';
          break;
        case 404:
          alertMessage = 'المسار غير موجود. تحقق من إعدادات API';
          alertType = 'error';
          break;
        case 408:
          alertMessage = 'انتهت مهلة الاتصال. يرجى المحاولة مرة أخرى';
          alertType = 'warning';
          break;
        case 500:
          alertMessage = 'خطأ في الخادم. يرجى المحاولة لاحقاً';
          alertType = 'error';
          break;
        default:
          alertMessage = err.response.data?.message || `خطأ ${status}`;
          alertType = 'error';
      }
    } else if (err.code === 'ECONNABORTED') {
      alertMessage = 'انتهت مهلة الاتصال. يرجى التحقق من اتصال الإنترنت';
      alertType = 'warning';
    } else if (!err.response) {
      alertMessage = 'تعذر الاتصال بالخادم. تحقق من اتصال الإنترنت أو تأكد من تشغيل الخادم';
      alertType = 'error';
    } else {
      alertMessage = err.message || 'حدث خطأ غير متوقع';
      alertType = 'error';
    }
    
    errorMessage.value = alertMessage;
    showAlert(alertTitle, alertMessage, alertType);
    patients.value = [];
  } finally {
    isLoading.value = false;
  }
};

const fetchPatientDetails = async (patientId) => {
  try {
    const response = await api.get(`/patients/${patientId}`);
    const patientData = response.data.data || response.data;
    
    return {
      ...patientData,
      name: patientData.name || 'غير متوفر',
      nationalId: patientData.nationalId || 'غير متوفر',
      birth: patientData.birth || 'غير متوفر',
      medications: (patientData.medications || []).map(med => {
        const unit = med.unit || 'حبة';
        const monthlyQty = med.monthlyQuantityNum || med.monthlyQuantity || 0;
        
        let dosageText = med.dosage;
        if (!dosageText || typeof dosageText === 'number') {
          const dailyQty = med.dailyQuantity || med.daily_quantity || 0;
          dosageText = dailyQty > 0 
            ? dailyQty + ' ' + unit + ' يومياً'
            : 'غير محدد';
        }
        
        return {
          ...med,
          monthlyQuantity: med.monthlyQuantity || (monthlyQty > 0 ? monthlyQty + ' ' + unit : 'غير محدد'),
          monthlyQuantityNum: monthlyQty,
          unit: unit,
          assignmentDate: med.assignmentDate || new Date().toISOString().split('T')[0].replace(/-/g, '/'),
          assignedBy: med.assignedBy || 'غير محدد',
          drugName: med.drugName || med.name || 'غير محدد',
          dosage: dosageText,
         
        };
      })
    };
  } catch (err) {
    console.error('خطأ في جلب تفاصيل المريض:', err);
    return null;
  }
};

const fetchDispensationHistory = async (patientId) => {
  try {
    const response = await api.get(`/patients/${patientId}/dispensations`);
    const historyData = response.data.data || response.data;
    
    if (Array.isArray(historyData)) {
      return historyData.map(item => ({
        ...item,
        drugName: item.drug_name || item.drugName,
        assignedBy: item.pharmacist || item.assignedBy,
        date: item.date ? item.date.split(' ')[0] : item.date
      }));
    }
    
    return [];
  } catch (err) {
    console.error('خطأ في جلب سجل الصرف:', err);
    return [];
  }
};

// ----------------------------------------------------
// 4. دوال إدارة الأدوية
// ----------------------------------------------------
const addMedicationToPatientAPI = async (patientId, medicationData) => {
  try {
    const response = await api.post(`/patients/${patientId}/medications`, medicationData);
    return response.data;
  } catch (err) {
    throw err;
  }
};

const updateMedicationAPI = async (patientId, pivotId, medicationData) => {
  try {
    const response = await api.put(`/patients/${patientId}/medications/${pivotId}`, medicationData);
    return response.data;
  } catch (err) {
    throw err;
  }
};

const deleteMedicationAPI = async (patientId, pivotId) => {
  try {
    const response = await api.delete(`/patients/${patientId}/medications/${pivotId}`);
    return response.data;
  } catch (err) {
    throw err;
  }
};

// ----------------------------------------------------
// 5. منطق البحث والفرز
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
            patient.fileNumber?.toString().includes(search) ||
            patient.name?.toLowerCase().includes(search) ||
            patient.nationalId?.includes(search) ||
            patient.birth?.includes(search) ||
            patient.phone?.includes(search)
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
// 6. نظام التنبيهات المحسن
// ----------------------------------------------------
const isAlertVisible = ref(false);
const alertMessage = ref("");
const alertDetails = ref("");
const alertType = ref("success");
const alertTimeout = ref(null);

const alertConfig = {
  success: {
    icon: 'mdi:check-circle-outline',
    bgClass: 'bg-green-500',
    iconColor: 'text-white'
  },
  error: {
    icon: 'mdi:alert-circle-outline',
    bgClass: 'bg-red-500',
    iconColor: 'text-white'
  },
  warning: {
    icon: 'mdi:alert-outline',
    bgClass: 'bg-yellow-500',
    iconColor: 'text-white'
  },
  info: {
    icon: 'mdi:information-outline',
    bgClass: 'bg-blue-500',
    iconColor: 'text-white'
  }
};

const alertTypeClass = computed(() => alertConfig[alertType.value]?.bgClass || 'bg-green-500');
const alertIcon = computed(() => alertConfig[alertType.value]?.icon || 'mdi:check-circle-outline');

const showAlert = (title, details = '', type = 'success') => {
  if (alertTimeout.value) {
    clearTimeout(alertTimeout.value);
  }

  alertMessage.value = title;
  alertDetails.value = details;
  alertType.value = type;
  isAlertVisible.value = true;

  alertTimeout.value = setTimeout(() => {
    hideAlert();
  }, 5000);
};

const hideAlert = () => {
  isAlertVisible.value = false;
  alertMessage.value = "";
  alertDetails.value = "";
  if (alertTimeout.value) {
    clearTimeout(alertTimeout.value);
    alertTimeout.value = null;
  }
};

// ----------------------------------------------------
// 7. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isAddMedicationModalOpen = ref(false);
const isDispensationModalOpen = ref(false);
const selectedPatient = ref({});
const dispensationHistory = ref([]);

// ----------------------------------------------------
// 8. دوال فتح وإغلاق الـ Modals
// ----------------------------------------------------
const openViewModal = async (patient) => {
  try {
    showAlert('جاري تحميل تفاصيل المريض...', '', 'info');
    
    const patientData = await fetchPatientDetails(patient.fileNumber);
    if (patientData) {
      selectedPatient.value = {
        ...patientData,
        nameDisplay: patientData.name || patientData.nameDisplay || 'غير متوفر',
        medications: (patientData.medications || []).map(med => ({
          ...med,
          monthlyQuantity: med.dosage || med.monthlyQuantity || 'غير محدد',
          assignmentDate: med.assignmentDate || new Date().toISOString().split('T')[0].replace(/-/g, '/'),
          assignedBy: med.assignedBy || 'غير محدد',
          drugName: med.drugName || med.name || 'غير محدد',
          dosage: med.dosage || med.monthlyQuantity || 'غير محدد',
        
        }))
      };
      isViewModalOpen.value = true;
      showAlert('تم تحميل تفاصيل المريض', `بيانات ${patient.name} جاهزة للعرض`, 'success');
    } else {
      showAlert('تعذر تحميل التفاصيل', 'يبدو أن هناك مشكلة في اتصال الخادم', 'warning');
      selectedPatient.value = patient;
      isViewModalOpen.value = true;
    }
  } catch (err) {
    console.error('خطأ في فتح نافذة المريض:', err);
    showAlert('خطأ في فتح النافذة', 'تعذر تحميل تفاصيل المريض', 'error');
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
    showAlert('جاري تحميل سجل الصرف...', '', 'info');
    
    const history = await fetchDispensationHistory(selectedPatient.value.fileNumber);
    dispensationHistory.value = history;
    
    if (history.length > 0) {
      showAlert(`تم تحميل ${history.length} سجل صرف`, '', 'success');
    } else {
      showAlert('لا توجد سجلات صرف', 'لم يتم العثور على سجلات صرف لهذا المريض', 'info');
    }
    
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
  } catch (err) {
    console.error('خطأ في تحميل سجل الصرف:', err);
    showAlert('تعذر تحميل سجل الصرف', 'تأكد من اتصال الخادم', 'warning');
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
// 9. دوال إدارة الأدوية
// ----------------------------------------------------
const addMedicationToPatient = async (medicationsData) => {
  try {
    showAlert('جاري إضافة الأدوية...', '', 'info');
    
    const medicationsPayload = medicationsData.map(med => {
      const dailyQty = med.dailyQuantity || med.quantity || 0;
      const monthlyQuantity = Math.round(dailyQty * 30);
      
      const payload = {
        drug_id: med.drugId || med.id,
        quantity: monthlyQuantity,
      };
      
      if (dailyQty && dailyQty > 0) {
        payload.daily_quantity = Math.round(dailyQty);
      }
      
   
      
      return payload;
    });

    try {
      await addMedicationToPatientAPI(selectedPatient.value.fileNumber, { medications: medicationsPayload });

      const updatedPatient = await fetchPatientDetails(selectedPatient.value.fileNumber);
      if (updatedPatient) {
        selectedPatient.value = updatedPatient;
        
        const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
        if (patientIndex !== -1) {
          patients.value[patientIndex] = updatedPatient;
        }
      }

      showAlert(
        `تم إضافة ${medicationsData.length} دواء بنجاح`,
        `تمت إضافة الأدوية إلى سجل المريض ${selectedPatient.value.name}`,
        'success'
      );
    } catch (apiError) {
      const errorData = apiError.response?.data || {};
      let errorMsg = errorData.message || apiError.message || 'حدث خطأ غير معروف';
      
      if (errorData.error) {
        errorMsg += ` (${errorData.error})`;
      }
      
      showAlert('فشل في إضافة الأدوية', errorMsg, 'error');
    }
  } catch (err) {
    console.error('خطأ في إضافة الأدوية:', err);
    showAlert('خطأ في إضافة الأدوية', 'حدث خطأ غير متوقع', 'error');
  }
};

const handleEditMedication = async (medIndex, newDosage) => {
  try {
    const medication = selectedPatient.value.medications[medIndex];
    const pivotId = medication.pivot_id || medication.id;

    if (!pivotId) {
      showAlert(' لا يمكن تحديد الدواء', 'معرّف الدواء غير موجود', 'warning');
      return;
    }

    const monthlyQuantity = Math.round(newDosage * 30);

    if (monthlyQuantity <= 0) {
      showAlert('كمية غير صالحة', 'الكمية الشهرية يجب أن تكون أكبر من الصفر', 'warning');
      return;
    }

    const medicationPayload = {
      dosage: monthlyQuantity,
      daily_quantity: Math.round(newDosage)
    };

    showAlert(' جاري تعديل الدواء...', '', 'info');

    try {
      await updateMedicationAPI(
        selectedPatient.value.fileNumber,
        pivotId,
        medicationPayload
      );

      const updatedPatient = await fetchPatientDetails(selectedPatient.value.fileNumber);
      if (updatedPatient) {
        selectedPatient.value = updatedPatient;
        
        const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
        if (patientIndex !== -1) {
          patients.value[patientIndex] = updatedPatient;
        }
      }

      showAlert(
        ' تم تعديل الجرعة الدوائية بنجاح',
        `تم تحديث جرعة ${medication.drugName || 'الدواء'}`,
        'success'
      );
    } catch (apiError) {
      showAlert(
        ' فشل في تعديل الدواء',
        apiError.response?.data?.message || apiError.message,
        'error'
      );
    }
  } catch (err) {
    console.error('خطأ في تعديل الدواء:', err);
    showAlert(' خطأ في تعديل الدواء', 'حدث خطأ غير متوقع', 'error');
  }
};

const handleDeleteMedication = async (medIndex) => {
  try {
    const medication = selectedPatient.value.medications[medIndex];
    const medicationName = medication.drugName || medication.drug_name || medication.name;
    const pivotId = medication.pivot_id || medication.id;

    if (!pivotId) {
      showAlert(' لا يمكن تحديد الدواء', 'معرّف الدواء غير موجود', 'warning');
      return;
    }

    showAlert(' جاري حذف الدواء...', '', 'info');

    try {
      await deleteMedicationAPI(selectedPatient.value.fileNumber, pivotId);

      const updatedPatient = await fetchPatientDetails(selectedPatient.value.fileNumber);
      if (updatedPatient) {
        selectedPatient.value = updatedPatient;
        
        const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
        if (patientIndex !== -1) {
          patients.value[patientIndex] = updatedPatient;
        }
      }

      showAlert(
        ' تم حذف الدواء بنجاح',
        `تم حذف ${medicationName} من سجل المريض`,
        'success'
      );
    } catch (apiError) {
      showAlert(
        ' فشل في حذف الدواء',
        apiError.response?.data?.message || apiError.message,
        'error'
      );
    }
  } catch (err) {
    console.error('خطأ في حذف الدواء:', err);
    showAlert(' خطأ في حذف الدواء', 'حدث خطأ غير متوقع', 'error');
  }
};

// ----------------------------------------------------
// 10. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredPatients.value.length;

    if (resultsCount === 0) {
        showAlert(' لا توجد بيانات للطباعة', 'لم يتم العثور على مرضى لعرضهم في التقرير', 'warning');
        return;
    }

    const printWindow = window.open('', '_blank', 'height=600,width=800');

    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showAlert(
            ' تعذر فتح نافذة الطباعة',
            'يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع أو استخدام Ctrl+P للطباعة المباشرة',
            'error'
        );
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
            .print-date { text-align: left; font-size: 12px; color: #666; margin-bottom: 10px; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>

        <h1>قائمة المرضى - تقرير طباعة</h1>
        <div class="print-date">تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')}</div>
        <p class="results-info">عدد المرضى: ${resultsCount}</p>

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
        <div class="footer">
            تم إنشاء التقرير تلقائياً من نظام إدارة الصيدلية
        </div>
    `;

    printWindow.document.write('<html><head><title>طباعة قائمة المرضى</title>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(tableHtml);
    printWindow.document.write('</body></html>');
    printWindow.document.close();

    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
        showAlert(
            '📄 تم تحضير التقرير للطباعة',
            'يتم فتح نافذة الطباعة الآن',
            'info'
        );
    };
};

// ----------------------------------------------------
// 11. دورة حياة المكون
// ----------------------------------------------------
onMounted(() => {
  fetchPatients();
});

const reloadData = () => {
  fetchPatients();
};
</script>

<style scoped>
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
    text-align: center;
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
    width: 200px;
    min-width: 180px;
}

/* تحسينات عامة */
.hover\:bg-gray-100:hover {
    background-color: #f7fafc;
}

.transition-colors {
    transition-property: background-color, border-color, color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

/* شريط التقدم للتنبيهات */
.progress-bar {
    animation: progress 5s linear forwards;
    width: 100%;
}

@keyframes progress {
    from { width: 100%; }
    to { width: 0%; }
}
</style>