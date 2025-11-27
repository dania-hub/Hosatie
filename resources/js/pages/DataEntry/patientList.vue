<script setup>
import { ref, computed, onMounted } from "vue";
import axios from 'axios'; // 💡 تم إضافة استيراد axios
import { Icon } from "@iconify/vue";
import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import inputadd from "@/components/btnadd.vue";
import btnprint from "@/components/btnprint.vue";
import btnform from "@/components/btnform.vue";
import Btncancel from "@/components/btncancel.vue";

// استيراد المكونات المنفصلة
import PatientAddModal from "@/components/patientsDataEntry/PatientAddModel.vue";
import PatientEditModal from "@/components/patientsDataEntry/PatientEditModel.vue";
import PatientViewModal from "@/components/patientsDataEntry/PatientViewModel.vue";

// ----------------------------------------------------
// 1. بيانات المرضى والـ Endpoint
// ----------------------------------------------------
const API_URL = '/api/patients'; // 💡 Endpoint الخاص بقائمة المرضى
const patients = ref([]);

// ----------------------------------------------------
// 2. منطق جلب البيانات (Fetch)
// ----------------------------------------------------

const fetchPatients = async () => {
    try {
        const response = await axios.get(API_URL);
        // نفترض أن API يرجع مصفوفة من المرضى
        patients.value = response.data; 
    } catch (error) {
        console.error("Error fetching patients:", error);
        // يمكن إضافة منطق لإظهار رسالة خطأ للمستخدم هنا
        showSuccessAlert("❌ فشل تحميل بيانات المرضى من الخادم.");
    }
};

// جلب البيانات عند تحميل المكون
onMounted(() => {
    fetchPatients();
});


// ----------------------------------------------------
// 3. منطق البحث والفرز الموحد (بقي كما هو)
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref('lastUpdated');
const sortOrder = ref('desc');

// (دالة calculateAge و sortPatients و filteredPatients لم تتغير وبقيت في الكود)
const calculateAge = (birthDateString) => {
    if (!birthDateString) return 0;
    const parts = birthDateString.split('/');
    if (parts.length !== 3) return 0; 
    
    // Note: Assuming D/M/Y format for simplicity in this demo.
    const birthDate = new Date(parts[2], parts[1] - 1, parts[0]); 
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
// 4. منطق رسالة النجاح)
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
// 5. حالة الـ Modals ودوام الفتح/الإغلاق )
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAddModalOpen = ref(false);
const selectedPatient = ref({});

const openViewModal = (patient) => { selectedPatient.value = patient; isViewModalOpen.value = true; };
const closeViewModal = () => { isViewModalOpen.value = false; selectedPatient.value = {}; };
const openEditModal = (patient) => { selectedPatient.value = patient; isEditModalOpen.value = true; };
const closeEditModal = () => { isEditModalOpen.value = false; selectedPatient.value = {}; };
const openAddModal = () => { isAddModalOpen.value = true; };
const closeAddModal = () => { isAddModalOpen.value = false; };

// ----------------------------------------------------
// 6. دوال إدارة البيانات (تم التعديل لاستخدام Axios)
// ----------------------------------------------------

const addPatient = async (newPatient) => {
    try {
        // 💡 إرسال طلب POST لإضافة مريض جديد
        const response = await axios.post(API_URL, newPatient);
        
        // نفترض أن الـ API يرجع الكائن الجديد بالرقم الملف المخصص
        patients.value.push(response.data); 
        closeAddModal();
        showSuccessAlert("✅ تم تسجيل بيانات المريض بنجاح!");
    } catch (error) {
        console.error("Error adding patient:", error);
        showSuccessAlert("❌ فشل تسجيل المريض. تحقق من البيانات.");
    }
};

const updatePatient = async (updatedPatient) => {
    try {
        // 💡 إرسال طلب PUT/PATCH لتعديل مريض
        // يجب أن يحتوي updatedPatient على fileNumber أو ID
        await axios.put(`${API_URL}/${updatedPatient.fileNumber}`, updatedPatient);
        
        // تحديث القائمة محليًا بعد نجاح التعديل
        const index = patients.value.findIndex(p => p.fileNumber === updatedPatient.fileNumber);
        if (index !== -1) {
             // نفترض أن البيانات التي أرسلتها هي التي ستحل محل البيانات القديمة
            patients.value[index] = { ...updatedPatient, lastUpdated: new Date().toISOString() };
        }
        
        closeEditModal();
        showSuccessAlert(`✅ تم تعديل بيانات المريض ${updatedPatient.fileNumber} بنجاح!`);
    } catch (error) {
        console.error("Error updating patient:", error);
        showSuccessAlert("❌ فشل تعديل بيانات المريض.");
    }
};

const deletePatient = async (fileNumber) => {
    if (!confirm(`هل أنت متأكد من حذف المريض برقم ملف ${fileNumber}؟`)) {
        return;
    }
    
    try {
        // 💡 إرسال طلب DELETE لحذف مريض
        await axios.delete(`${API_URL}/${fileNumber}`);
        
        // تحديث القائمة محليًا بعد نجاح الحذف
        const index = patients.value.findIndex(p => p.fileNumber === fileNumber);
        if (index !== -1) {
            patients.value.splice(index, 1);
        }
        
        showSuccessAlert(`✅ تم حذف المريض ${fileNumber} بنجاح!`);
    } catch (error) {
        console.error("Error deleting patient:", error);
        showSuccessAlert("❌ فشل حذف المريض.");
    }
};

// ----------------------------------------------------
// 7. منطق الطباعة (بقي كما هو)
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredPatients.value.length;
    // ... بقية منطق الطباعة (لم يتغير)
    
    const printWindow = window.open('', '_blank', 'height=600,width=800');
    
    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showSuccessAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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
        </style>

        <h1>قائمة المرضى (تقرير طباعة)</h1>
        
        <p class="results-info">
            عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}
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
    <div class="drawer lg:drawer-open" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />

            <main class="flex-1 p-4 sm:p-5 pt-3">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0">
                    
                    <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <div class="relative w-full sm:max-w-sm">
                            <search v-model="searchTerm" />
                        </div>
                        
                        <div class="dropdown dropdown-start">
                            <div tabindex="0" role="button" class="btn button inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23
      rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden 
      text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">
                                <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
                                فرز
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-lg  bg-white border-2   hover:border hover:border-[#a8a8a8]  border-[#ffffff8d] 
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

                    <div class="flex items-center gap-5 w-full sm:w-auto justify-end">
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
                class="hover:bg-gray-100 border border-gray-300"
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
                            class="p-1 rounded-full hover:bg-green-100 transition-colors"
                            title="عرض البيانات"
                        >
                            <Icon
                                icon="tabler:eye-minus"
                                class="w-5 h-5 text-green-600"
                            />
                        </button>

                        <button 
                            @click="openEditModal(patient)"
                            class="p-1 rounded-full hover:bg-yellow-100 transition-colors"
                            title="تعديل البيانات"
                        >
                            <Icon
                                icon="line-md:pencil"
                                class="w-5 h-5 text-yellow-500"
                            />
                        </button>

                        <button 
                            @click="deletePatient(patient.fileNumber)"
                            class="p-1 rounded-full hover:bg-red-100 transition-colors"
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
                <td colspan="6" class="text-center py-8 text-gray-500">
                    لا توجد بيانات لعرضها
                </td>
            </tr>
        </tbody>
    </table>
</div>
                    </div>
                </div>
            </main>
        </div>

        <Sidebar />
    </div>

    <PatientAddModal
        :is-open="isAddModalOpen"
        @close="closeAddModal"
        @save="addPatient"
    />

    <PatientEditModal
        :is-open="isEditModalOpen"
        :patient="selectedPatient"
        @close="closeEditModal"
        @save="updatePatient"
    />

    <PatientViewModal
        :is-open="isViewModalOpen"
        :patient="selectedPatient"
        @close="closeViewModal"
    />

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
            class="fixed top-4 right-55 z-[1000] p-4 text-right bg-green-500 text-white rounded-lg shadow-xl max-w-xs transition-all duration-300"
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