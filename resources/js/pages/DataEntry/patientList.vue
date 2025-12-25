<script setup>
import { ref, computed, onMounted } from "vue";
import axios from 'axios'; 
import { Icon } from "@iconify/vue";
// 💡 تم استبدال استيراد Navbar و Sidebar بـ DefaultLayout
import DefaultLayout from "@/components/DefaultLayout.vue"; 
import search from "@/components/search.vue";
import inputadd from "@/components/btnadd.vue";
import btnprint from "@/components/btnprint.vue";

// استيراد المكونات المنفصلة
import PatientAddModal from "@/components/patientsDataEntry/PatientAddModel.vue";
import PatientEditModal from "@/components/patientsDataEntry/PatientEditModel.vue";
import PatientViewModal from "@/components/patientsDataEntry/PatientViewModel.vue";

// ----------------------------------------------------
// 1. بيانات المرضى والـ Endpoint
// ----------------------------------------------------
const API_URL = '/api/data-entry/patients';
const patients = ref([]);

// دالة لمعادلة شكل تاريخ الميلاد وإزالة وقت/منطقة الزمن (T...)
const normalizeBirthDate = (value) => {
    if (!value) return 'غير متوفر';
    if (typeof value === 'string') {
        // إذا كان التاريخ بصيغة ISO مثل 2025-12-07T00:00:00.000000Z نأخذ الجزء قبل T
        if (value.includes('T')) {
            return value.split('T')[0];
        }
        return value;
    }
    return String(value);
};

// Helper to get headers with token
const getAuthHeaders = () => {
    const token = localStorage.getItem('auth_token');
    return {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    };
};

// ----------------------------------------------------
// 2. منطق جلب البيانات (Fetch)
// ----------------------------------------------------
const fetchPatients = async () => {
    try {
        const response = await axios.get(API_URL, getAuthHeaders());
        
        // Handle response from sendSuccess (response.data.data is the array)
        const rawData = response.data.data || [];
        
        patients.value = rawData.map(p => ({
            id: p.id,
            fileNumber: p.file_number || `FILE-${p.id}`, // Generate if missing
            name: p.full_name || p.name, // Handle raw 'full_name' or Resource 'name'
            nationalId: p.national_id || p.nationalId,
            // توحيد صيغة تاريخ الميلاد وإزالة الجزء الزائد T...
            birth: normalizeBirthDate(p.birth_date || p.birth),
            phone: p.phone,
            email: p.email, // سيأتي الآن من الـ index أيضاً
            lastUpdated: p.updated_at || new Date().toISOString()
        }));
    } catch (error) {
        console.error("Error fetching patients:", error);
        showSuccessAlert("⚠️ تعذر تحميل بيانات المرضى من الخادم. الرجاء التحقق من اتصال الإنترنت والمحاولة مرة أخرى.");
    }
};

onMounted(() => {
    fetchPatients();
});


// ----------------------------------------------------
// 3. منطق البحث والفرز الموحد (لم يتغير)
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref('lastUpdated');
const sortOrder = ref('desc');

const calculateAge = (birthDateString) => {
    if (!birthDateString || birthDateString === 'غير متوفر') return 0;
    
    let birthDate;
    // Handle YYYY-MM-DD (Backend)
    if (birthDateString.includes('-')) {
        birthDate = new Date(birthDateString);
    } 
    // Handle DD/MM/YYYY (Legacy/Frontend)
    else if (birthDateString.includes('/')) {
        const parts = birthDateString.split('/');
        if (parts.length === 3) {
            birthDate = new Date(parts[2], parts[1] - 1, parts[0]);
        }
    }

    if (!birthDate || isNaN(birthDate.getTime())) return 0;

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
// 4. منطق رسالة النجاح والخطأ
// ----------------------------------------------------
const isSuccessAlertVisible = ref(false);
const successMessage = ref("");
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

// ----------------------------------------------------
// 5. حالة الـ Modals ودوام الفتح/الإغلاق (لم يتغير)
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAddModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedPatient = ref({});
const patientToDelete = ref(null);

const openViewModal = (patient) => { selectedPatient.value = patient; isViewModalOpen.value = true; };
const closeViewModal = () => { isViewModalOpen.value = false; selectedPatient.value = {}; };
const openEditModal = (patient) => { selectedPatient.value = patient; isEditModalOpen.value = true; };
const closeEditModal = () => { isEditModalOpen.value = false; selectedPatient.value = {}; };
const openAddModal = () => { isAddModalOpen.value = true; };
const closeAddModal = () => { isAddModalOpen.value = false; };

// فتح نافذة تأكيد الحذف
const openDeleteModal = (patient) => {
    patientToDelete.value = patient;
    isDeleteModalOpen.value = true;
};

// إغلاق نافذة تأكيد الحذف
const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    patientToDelete.value = null;
};

// ----------------------------------------------------
// 6. دوال إدارة البيانات
// ----------------------------------------------------
const addPatient = async (newPatient) => {
    try {
        // Map frontend fields to backend expected fields
        const payload = {
            full_name: newPatient.name,
            national_id: newPatient.nationalId,
            phone: newPatient.phone,
            email: newPatient.email || `patient${newPatient.nationalId}@example.com`, // Fallback email
            birth_date: newPatient.birth.replace(/\//g, '-') // Ensure YYYY-MM-DD format
        };

        const response = await axios.post(API_URL, payload, getAuthHeaders());
        
        // Store returns PatientResource, so we map it
        const p = response.data.data;
        patients.value.unshift({
            id: p.id,
            fileNumber: p.file_number, // Resource has this
            name: p.name, // Resource maps full_name to name
            nationalId: p.national_id, // Resource has this
            birth: p.birth, // Resource has this
            phone: p.phone,
            email: p.email,
            lastUpdated: new Date().toISOString()
        });

        closeAddModal();
        showSuccessAlert("✅ تم تسجيل بيانات المريض بنجاح!");
    } catch (error) {
        console.error("Error adding patient:", error);
        let msg = "";
        
        if (error.response?.status === 422) {
            const errors = error.response.data?.errors || {};
            
            if (errors.phone) {
                if (errors.phone.includes("has already been taken") || errors.phone.some(e => e.includes("مأخوذ"))) {
                    msg = "⚠️ رقم الهاتف هذا مسجل بالفعل لمريض آخر.";
                } else if (errors.phone[0]) {
                    msg = `⚠️ ${errors.phone[0]}`;
                }
            } else if (errors.national_id) {
                if (errors.national_id.includes("has already been taken") || errors.national_id.some(e => e.includes("مأخوذ"))) {
                    msg = "⚠️ الرقم الوطني هذا مسجل بالفعل لمريض آخر.";
                } else if (errors.national_id[0]) {
                    msg = `⚠️ ${errors.national_id[0]}`;
                }
            } else if (errors.email) {
                if (errors.email.includes("has already been taken") || errors.email.some(e => e.includes("مأخوذ"))) {
                    msg = "⚠️ البريد الإلكتروني هذا مسجل بالفعل لمريض آخر.";
                } else if (errors.email[0]) {
                    msg = `⚠️ ${errors.email[0]}`;
                }
            } else if (errors.full_name) {
                msg = "⚠️ الاسم الرباعي غير صالح.";
            } else if (errors.birth_date) {
                msg = "⚠️ تاريخ الميلاد غير صالح.";
            } else {
                // عرض جميع الأخطاء بشكل عام
                const errorList = Object.values(errors).flat();
                if (errorList.length > 0) {
                    msg = `⚠️ ${errorList[0]}`;
                } else {
                    msg = "⚠️ فشل تسجيل المريض. الرجاء التحقق من البيانات المدخلة.";
                }
            }
        } else if (error.response?.status === 401) {
            msg = "⚠️ انتهت صلاحية الجلسة. الرجاء تسجيل الدخول مرة أخرى.";
        } else if (error.response?.status === 403) {
            msg = "⚠️ ليس لديك صلاحية لإضافة مرضى.";
        } else if (error.response?.status === 500) {
            msg = "⚠️ خطأ في الخادم. الرجاء المحاولة مرة أخرى لاحقاً.";
        } else if (error.code === 'NETWORK_ERROR' || !error.response) {
            msg = "⚠️ فشل الاتصال بالخادم. الرجاء التحقق من اتصال الإنترنت.";
        } else {
            msg = "⚠️ فشل تسجيل المريض. الرجاء المحاولة مرة أخرى.";
        }
        
        showSuccessAlert(msg);
    }
};

const updatePatient = async (updatedPatient) => {
    try {
        const payload = {
            full_name: updatedPatient.name,
            national_id: updatedPatient.nationalId,
            phone: updatedPatient.phone,
            email: updatedPatient.email,
            birth_date: updatedPatient.birth.replace(/\//g, '-') // Ensure YYYY-MM-DD format
        };

        // Use ID for the URL
        const response = await axios.put(`${API_URL}/${updatedPatient.id}`, payload, getAuthHeaders());
        
        // Update returns PatientResource
        const p = response.data.data;
        
        const index = patients.value.findIndex(item => item.id === updatedPatient.id);
        if (index !== -1) {
            // Update local state with returned data
            patients.value[index] = {
                id: p.id,
                fileNumber: p.file_number,
                name: p.name,
                nationalId: p.national_id,
                birth: p.birth,
                phone: p.phone,
                email: p.email,
                lastUpdated: new Date().toISOString()
            };
        }
        
        closeEditModal();
        showSuccessAlert(`✅ تم تعديل بيانات المريض ${p.file_number} بنجاح!`);
    } catch (error) {
        console.error("Error updating patient:", error);
        
        let msg = "";
        
        if (error.response?.status === 422) {
            const errors = error.response.data?.errors || {};
            
            if (errors.phone) {
                if (errors.phone.includes("has already been taken") || errors.phone.some(e => e.includes("مأخوذ"))) {
                    msg = "⚠️ رقم الهاتف هذا مسجل بالفعل لمريض آخر.";
                } else if (errors.phone[0]) {
                    msg = `⚠️ ${errors.phone[0]}`;
                }
            } else if (errors.national_id) {
                if (errors.national_id.includes("has already been taken") || errors.national_id.some(e => e.includes("مأخوذ"))) {
                    msg = "⚠️ الرقم الوطني هذا مسجل بالفعل لمريض آخر.";
                } else if (errors.national_id[0]) {
                    msg = `⚠️ ${errors.national_id[0]}`;
                }
            } else if (errors.email) {
                if (errors.email.includes("has already been taken") || errors.email.some(e => e.includes("مأخوذ"))) {
                    msg = "⚠️ البريد الإلكتروني هذا مسجل بالفعل لمريض آخر.";
                } else if (errors.email[0]) {
                    msg = `⚠️ ${errors.email[0]}`;
                }
            } else if (errors.full_name) {
                msg = "⚠️ الاسم الرباعي غير صالح.";
            } else if (errors.birth_date) {
                msg = "⚠️ تاريخ الميلاد غير صالح.";
            } else {
                // عرض جميع الأخطاء بشكل عام
                const errorList = Object.values(errors).flat();
                if (errorList.length > 0) {
                    msg = `⚠️ ${errorList[0]}`;
                } else {
                    msg = "⚠️ فشل تعديل بيانات المريض.";
                }
            }
        } else if (error.response?.status === 404) {
            msg = "⚠️ المريض غير موجود.";
        } else if (error.response?.status === 401) {
            msg = "⚠️ انتهت صلاحية الجلسة.";
        } else if (error.response?.status === 403) {
            msg = "⚠️ ليس لديك صلاحية لتعديل بيانات المرضى.";
        } else if (error.response?.status === 500) {
            msg = "⚠️ خطأ في الخادم.";
        } else if (error.code === 'NETWORK_ERROR' || !error.response) {
            msg = "⚠️ فشل الاتصال بالخادم.";
        } else {
            msg = "⚠️ فشل تعديل بيانات المريض.";
        }
        
        showSuccessAlert(msg);
    }
};

const confirmDelete = async () => {
    if (!patientToDelete.value) return;
    
    const patient = patientToDelete.value;
    
    try {
        // Use ID for the URL
        await axios.delete(`${API_URL}/${patient.id}`, getAuthHeaders());
        
        const index = patients.value.findIndex(p => p.id === patient.id);
        if (index !== -1) {
            patients.value.splice(index, 1);
        }
        
        closeDeleteModal();
        showSuccessAlert(`✅ تم حذف المريض ${patient.fileNumber} بنجاح!`);
    } catch (error) {
        console.error("Error deleting patient:", error);
        let msg = "";
        
        if (error.response?.status === 404) {
            msg = "⚠️ المريض غير موجود.";
        } else if (error.response?.status === 401) {
            msg = "⚠️ انتهت صلاحية الجلسة.";
        } else if (error.response?.status === 403) {
            msg = "⚠️ ليس لديك صلاحية لحذف المرضى.";
        } else if (error.response?.status === 500) {
            msg = "⚠️ خطأ في الخادم.";
        } else if (error.code === 'NETWORK_ERROR' || !error.response) {
            msg = "⚠️ فشل الاتصال بالخادم.";
        } else {
            msg = "⚠️ فشل حذف المريض.";
        }
        
        showSuccessAlert(msg);
        closeDeleteModal();
    }
};

// ----------------------------------------------------
// 7. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredPatients.value.length;
    
    const printWindow = window.open('', '_blank', 'height=600,width=800');
    
    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showSuccessAlert("⚠️ فشل عملية الطباعة. الرجاء السماح بفتح النوافذ المنبثقة.");
        return;
    }

    let tableHtml = `
        <style>
            body { 
                font-family: 'Arial', sans-serif; 
                direction: rtl; 
                padding: 20px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 15px;
            }
            th, td { 
                border: 1px solid #ccc; 
                padding: 10px; 
                text-align: right; 
            }
            th { 
                background-color: #f2f2f2; 
                font-weight: bold; 
            }
            h1 { 
                text-align: center; 
                color: #2E5077;
                margin-bottom: 10px; 
            }
            .results-info { 
                text-align: right; 
                margin-bottom: 15px; 
                font-size: 16px; 
                font-weight: bold; 
                color: #4DA1A9; 
            }
            .print-date { 
                text-align: left; 
                font-size: 14px; 
                color: #666; 
                margin-bottom: 20px; 
            }
        </style>

        <h1>قائمة المرضى - تقرير طباعة</h1>
        
        <div class="print-date">
            تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')}
        </div>
        
        <p class="results-info">
            عدد المرضى: ${resultsCount}
        </p>
        
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
        
        <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #888;">
            تم إنشاء هذا التقرير تلقائياً من نظام إدارة المرضى
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
        showSuccessAlert("✅ تم تجهيز التقرير بنجاح للطباعة.");
    };
};
</script>

<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
                
                <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <search v-model="searchTerm" />
                  
                    
                    <div class="dropdown dropdown-start">
                        <div tabindex="0" role="button" class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23
        rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden 
        text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                            <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
                            فرز
                        </div>
                        <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg  bg-white border-2  hover:border hover:border-[#a8a8a8]  border-[#ffffff8d] 
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

                <div class="flex items-end gap-3 w-full sm:w-auto justify-end">
                    <inputadd @open-modal="openAddModal" />
                    <btnprint @click="printTable" />
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
                                <tr 
                                    v-for="(patient, index) in filteredPatients"
                                    :key="index"
                                    class="hover:bg-gray-100 border border-gray-300 transition-colors duration-150"
                                >
                                    <td class="file-number-col">{{ patient.fileNumber }}</td>
                                    <td class="name-col">{{ patient.name }}</td>
                                    <td class="national-id-col">{{ patient.nationalId }}</td>
                                    <td class="birth-date-col">{{ patient.birth }}</td>
                                    <td class="phone-col">{{ patient.phone }}</td>

                                    <td class="actions-col">
                                        <div class="flex gap-3 justify-center items-center">
                                            <button 
                                                @click="openViewModal(patient)"
                                                class="p-1 rounded-full hover:bg-green-100 transition-colors duration-200"
                                                title="عرض البيانات"
                                            >
                                                <Icon
                                                    icon="tabler:eye-minus"
                                                    class="w-5 h-5 text-green-600"
                                                />
                                            </button>

                                            <button 
                                                @click="openEditModal(patient)"
                                                class="p-1 rounded-full hover:bg-yellow-100 transition-colors duration-200"
                                                title="تعديل البيانات"
                                            >
                                                <Icon
                                                    icon="line-md:pencil"
                                                    class="w-5 h-5 text-yellow-500"
                                                />
                                            </button>

                                            <button 
                                                @click="openDeleteModal(patient)"
                                                class="p-1 rounded-full hover:bg-red-100 transition-colors duration-200"
                                                title="حذف المريض"
                                            >
                                                <Icon
                                                    icon="line-md:account-delete"
                                                    class="w-5 h-5 text-red-600"
                                                />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr v-if="filteredPatients.length === 0">
                                    <td colspan="6" class="text-center py-12 text-gray-500">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <Icon icon="solar:documents-bold-duotone" class="w-12 h-12 text-gray-300" />
                                            <p class="text-lg font-medium">لا توجد بيانات لعرضها</p>
                                            <p class="text-sm text-gray-400">حاول تغيير كلمة البحث أو إضافة مرضى جدد</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </DefaultLayout>

    <!-- نافذة إضافة مريض -->
    <PatientAddModal
        :is-open="isAddModalOpen"
        @close="closeAddModal"
        @save="addPatient"
    />

    <!-- نافذة تعديل مريض -->
    <PatientEditModal
        :is-open="isEditModalOpen"
        :patient="selectedPatient"
        @close="closeEditModal"
        @save="updatePatient"
    />

    <!-- نافذة عرض بيانات المريض -->
    <PatientViewModal
        :is-open="isViewModalOpen"
        :patient="selectedPatient"
        @close="closeViewModal"
    />

    <!-- نافذة تأكيد الحذف -->
    <div v-if="isDeleteModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeDeleteModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon icon="solar:trash-bin-trash-bold-duotone" class="w-8 h-8 text-red-500" />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">حذف المريض</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من حذف هذا المريض من النظام؟
                    <br>
                    <span class="text-sm text-red-500">لا يمكن التراجع عن هذا الإجراء</span>
                </p>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="closeDeleteModal" 
                    class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="confirmDelete" 
                    class="flex-1 px-4 py-2.5 rounded-xl bg-red-500 text-white font-medium hover:bg-red-600 transition-colors duration-200 shadow-lg shadow-red-500/20"
                >
                    حذف نهائي
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Notification -->
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
            class="fixed top-4 right-55 z-[1000] p-4 text-right rounded-lg shadow-xl max-w-xs transition-all duration-300"
            dir="rtl"
            :class="{
                'bg-green-50 border border-green-200 text-green-800': successMessage.includes('✅'),
                'bg-red-50 border border-red-200 text-red-800': successMessage.includes('⚠️'),
                'bg-blue-50 border border-blue-200 text-blue-800': !successMessage.includes('✅') && !successMessage.includes('⚠️')
            }"
        >
            <div class="flex items-start gap-3">
                <Icon 
                    :icon="successMessage.includes('✅') ? 'solar:check-circle-bold' : 'solar:danger-triangle-bold'" 
                    class="w-5 h-5 mt-0.5 flex-shrink-0"
                    :class="successMessage.includes('✅') ? 'text-green-600' : 'text-red-600'"
                />
                <div>
                    <p class="font-medium text-sm whitespace-pre-line">{{ successMessage.replace('✅', '').replace('⚠️', '') }}</p>
                </div>
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

/* تنسيقات الجدول */
.table {
    border-collapse: separate;
    border-spacing: 0;
}
.table th {
    position: sticky;
    top: 0;
    background-color: #9aced2;
    font-weight: 600;
    padding: 12px 16px;
    white-space: nowrap;
}
.table td {
    padding: 10px 16px;
    vertical-align: middle;
}
.table tr:hover {
    background-color: #f9fafb;
}
</style>