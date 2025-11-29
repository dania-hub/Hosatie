<script setup>
import { ref, computed, onMounted } from "vue"; // 1. استيراد onMounted
import axios from "axios"; // 2. استيراد axios

import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

// استيراد المكونات المنفصلة
import PatientViewModal from "@/components/patientDoctor/PatientViewModal.vue";
import AddMedicationModal from "@/components/patientDoctor/AddMedicationModal.vue";
import DispensationModal from "@/components/patientDoctor/DispensationModal.vue";


// ----------------------------------------------------
// 1. بيانات المرضى (تم التعديل)
// ----------------------------------------------------
const patients = ref([]); // 3. تفريغ المصفوفة الأولية
const isLoading = ref(true); // 4. إضافة متغير لتتبع حالة التحميل

// 5. دالة لجلب بيانات المرضى من الـ API
const fetchPatients = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/patients'); // <-- استبدل هذا بالـ URL الصحيح
        patients.value = response.data;
    } catch (error) {
        console.error("حدث خطأ أثناء جلب بيانات المرضى:", error);
        // يمكنك إضافة رسالة خطأ للمستخدم هنا
    } finally {
        isLoading.value = false;
    }
};

// 6. استدعاء الدالة عند تحميل المكون
onMounted(() => {
    fetchPatients();
});

// ----------------------------------------------------
// 2. منطق البحث والفرز الموحد
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
// 3. منطق رسالة النجاح
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
// 4. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isAddMedicationModalOpen = ref(false);
const isDispensationModalOpen = ref(false);
const selectedPatient = ref({});

// ----------------------------------------------------
// 5. دوال فتح وإغلاق الـ Modals
// ----------------------------------------------------
const openViewModal = (patient) => {
    selectedPatient.value = patient;
    isViewModalOpen.value = true;
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

const openDispensationModal = () => {
    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
};

const closeDispensationModal = () => {
    isDispensationModalOpen.value = false;
    isViewModalOpen.value = true;
};

// ----------------------------------------------------
// 6. دوال إدارة الأدوية (تم التعديل)
// ----------------------------------------------------
const addMedicationToPatient = async (medicationsData) => {
    const patientId = selectedPatient.value.fileNumber; // أو patient.id حسب الـ API
    try {
        // 7. إرسال الأدوية الجديدة إلى الـ API
        const response = await axios.post(`/api/patients/${patientId}/medications`, {
            medications: medicationsData
        }); // <-- استبدل هذا بالـ URL الصحيح

        // 8. تحديث بيانات المريض محلياً من الاستجابة (أفضل ممارسة)
        const updatedPatient = response.data;
        const patientIndex = patients.value.findIndex(p => p.fileNumber === patientId);
        if (patientIndex !== -1) {
            patients.value[patientIndex] = updatedPatient;
            selectedPatient.value = updatedPatient;
        }
        
        showSuccessAlert(`✅ تم إضافة ${medicationsData.length} دواء بنجاح للمريض ${selectedPatient.value.nameDisplay}`);
    } catch (error) {
        console.error("حدث خطأ أثناء إضافة الدواء:", error);
        showSuccessAlert("❌ فشلت عملية إضافة الدواء.");
    }
};

const handleDeleteMedication = async (medIndex) => {
    const patientId = selectedPatient.value.fileNumber;
    const medicationId = selectedPatient.value.medications[medIndex].id; // افترض أن كل دواء له ID
    const medicationName = selectedPatient.value.medications[medIndex].drugName;

    try {
        // 9. إرسال طلب الحذف إلى الـ API
        await axios.delete(`/api/patients/${patientId}/medications/${medicationId}`); // <-- استبدل هذا بالـ URL الصحيح

        // 10. تحديث البيانات محلياً بعد نجاح الحذف
        const patientIndex = patients.value.findIndex(p => p.fileNumber === patientId);
        if (patientIndex !== -1) {
            patients.value[patientIndex].medications.splice(medIndex, 1);
            selectedPatient.value = { ...patients.value[patientIndex] };
            showSuccessAlert(`🗑️ تم حذف الدواء ${medicationName} بنجاح`);
        }
    } catch (error) {
        console.error("حدث خطأ أثناء حذف الدواء:", error);
        showSuccessAlert("❌ فشلت عملية حذف الدواء.");
    }
};

// 11. دالة جديدة لتحديث الدواء
const handleUpdateMedication = async ({ index, newDosage }) => {
    const patientId = selectedPatient.value.fileNumber;
    const medication = selectedPatient.value.medications[index];
    const medicationId = medication.id; // افترض أن كل دواء له ID

    try {
        const response = await axios.put(`/api/patients/${patientId}/medications/${medicationId}`, {
            dosage: newDosage,
            // يمكنك إرسال أي بيانات أخرى تحتاج للتحديث
        });

        // تحديث البيانات محلياً
        const updatedMedication = response.data;
        const patientIndex = patients.value.findIndex(p => p.fileNumber === patientId);
        if (patientIndex !== -1) {
            patients.value[patientIndex].medications[index] = updatedMedication;
            selectedPatient.value = { ...patients.value[patientIndex] };
            showSuccessAlert(`✅ تم تحديث جرعة الدواء ${medication.drugName} بنجاح.`);
        }
    } catch (error) {
        console.error("حدث خطأ أثناء تحديث الدواء:", error);
        showSuccessAlert("❌ فشلت عملية تحديث الدواء.");
    }
};

// ----------------------------------------------------
// 7. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredPatients.value.length;

    const printWindow = window.open('', '_blank', 'height=600,width=800');

    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showSuccessAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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
        </style>

        <h1>قائمة المرضى (تقرير طباعة)</h1>

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
                    </div></div>
                </div>

                <div class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col">

<!-- 12. إضافة حالة التحميل -->
                    <div v-if="isLoading" class="flex items-center justify-center h-full">
                        <p class="text-lg font-semibold text-gray-500">جاري تحميل البيانات...</p>
                    </div>

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
                            </table>
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
        @delete-medication="handleDeleteMedication"
        @update-medication="handleUpdateMedication"  
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
</style>