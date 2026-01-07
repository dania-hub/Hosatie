<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

// استيراد المكونات المنفصلة
import PatientViewModal from "@/components/forpharmacist/PatientViewModal.vue";
import DispensationModal from "@/components/patientDoctor/DispensationModal.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 0. تهيئة API و حالة التحميل
// ----------------------------------------------------
// نستخدم الـ API الخاص بالصيدلي
const API_BASE_URL = "/api/pharmacist";

// تهيئة نسخة خاصة من Axios مع الـ baseURL والتوكن
const api = axios.create({
    baseURL: API_BASE_URL,
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
    },
});

// إضافة التوكن تلقائياً من localStorage (نفس أسلوب ملف الدكتور)
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem("auth_token");
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

const patients = ref([]); // قائمة المرضى ستُملأ من الـ API
const isLoading = ref(true); // حالة التحميل
const hasError = ref(false);
const errorMessage = ref(null);

// ----------------------------------------------------
// 1. دوال جلب البيانات وتحديثها.
// ----------------------------------------------------
const fetchPatients = async () => {
    isLoading.value = true;
    hasError.value = false;
    errorMessage.value = null;
    try {
        // جلب البيانات من الـ API:
        // GET /api/pharmacist/patients  ➜  PatientPharmacistController@index
        const response = await api.get("/patients");

        // BaseApiController يُرجع البيانات بداخل data
        const responseData = response.data?.data ?? response.data;

        if (Array.isArray(responseData)) {
            patients.value = responseData.map((p) => {
                const fileNumber = p.fileNumber ?? p.id;
                const name = p.name || p.nameDisplay || "غير متوفر";
                const nationalId = p.nationalId || p.nationalIdDisplay || "غير متوفر";
                const birth = p.birthDate || p.birth || "غير متوفر";
                const phone = p.phone || "غير متوفر";
                const lastUpdatedRaw = p.lastUpdated || p.updated_at || new Date().toISOString();

                return {
                    fileNumber,
                    name,
                    nationalId,
                    birth,
                    phone,
                    lastUpdated: new Date(lastUpdatedRaw).toISOString(),
                    // خصائص مهيأة للعرض في المودالات
                    nameDisplay: name,
                    nationalIdDisplay: nationalId,
                    birthDisplay: birth,
                    // سيجلب لاحقاً من API أخرى عند الحاجة
                    medications: p.medications || [],
                    dispensationHistory: [],
                };
            });

        } else {
            patients.value = [];
        }
    } catch (error) {
        console.error("خطأ في جلب بيانات المرضى:", error);
        hasError.value = true;
        errorMessage.value = `فشل جلب البيانات: ${error.response?.data?.message || error.message}`;
        showSuccessAlert(errorMessage.value);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    // جلب البيانات عند تحميل الصفحة لأول مرة
    fetchPatients(); 
});


// ----------------------------------------------------
// 2. منطق تأكيد الصرف الفعلي (محدث لاستدعاء الـ API)
// ----------------------------------------------------
// تستقبل بيانات المريض وقائمة الأدوية المصروفة فعلياً
// POST /api/pharmacist/dispense  ➜  PatientPharmacistController@dispense
const handleConfirmation = async (patientData, dispensedMedications) => {
    // بناء حمولة الإرسال (Payload) للـ API
    const payload = {
        patientFileNumber: patientData.fileNumber,
        pharmacistId: 101, // يمكن جلبه لاحقاً من المستخدم المسجل
        dispensationDate: new Date().toISOString(),
        dispensedItems: dispensedMedications.map((med) => ({
            drugName: med.drugName,
            quantity: med.dispensedQuantity,
            // يمكن إضافة drugId أو batchId هنا عند توفره
        })),
    };

    try {
        const response = await api.post("/dispense", payload);
        
        // الحصول على معلومات السجلات المنشأة من الاستجابة
        const responseData = response.data?.data ?? {};
        const dispensations = responseData.dispensations || [];
        const inventoryChanges = responseData.inventory_changes || [];

        // تحديث البيانات في الواجهة عن طريق إعادة جلب القائمة
        await fetchPatients();

        // إظهار رسالة نجاح مع زر التراجع (لمدة 7 ثوانٍ)
        if (dispensations.length > 0 && inventoryChanges.length > 0) {
            showSuccessAlert(
                ` تم صرف الأدوية بنجاح للمريض ${patientData.nameDisplay}`,
                true,
                {
                    dispensations: dispensations,
                    inventory_changes: inventoryChanges,
                }
            );
        } else {
            showSuccessAlert(` تم صرف الأدوية بنجاح للمريض ${patientData.nameDisplay}`, false);
        }
    } catch (error) {
        console.error("خطأ في عملية صرف الأدوية:", error);
        showSuccessAlert(` فشل في صرف الأدوية: ${error.response?.data?.message || error.message}`, false);
    }
};

// ----------------------------------------------------
// 2.1 جلب سجل الصرف من الـ API للمريض المحدد
// ----------------------------------------------------
// GET /api/pharmacist/patients/{fileNumber}/dispensations  ➜  history
const fetchDispensationHistory = async (fileNumber) => {
    try {
        const response = await api.get(`/patients/${fileNumber}/dispensations`);
        const data = response.data?.data ?? response.data;

        const dispensations = data?.dispensations || [];

        // تحويل هيكلة الـ API إلى ما يحتاجه `DispensationModal`
        const historyItems = [];

        dispensations.forEach((disp) => {
            // الـ API يعيد التاريخ بصيغة Y/m/d (مثل 2026/01/04)
            // نستخدمه مباشرة إذا كان بصيغة صحيحة، وإلا نحوله
            let dateString = disp.date || '';
            if (dateString && typeof dateString === 'string') {
                // إذا كان التاريخ بالفعل بصيغة Y/m/d، نستخدمه مباشرة
                if (!/^\d{4}\/\d{1,2}\/\d{1,2}$/.test(dateString)) {
                    // تحويل التاريخ إلى صيغة Y/m/d
                    try {
                        const date = new Date(dateString);
                        if (!isNaN(date.getTime())) {
                            const year = date.getFullYear();
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const day = String(date.getDate()).padStart(2, '0');
                            dateString = `${year}/${month}/${day}`;
                        }
                    } catch (e) {
                        // في حالة الخطأ، نستخدم التاريخ الأصلي
                    }
                }
            }

            (disp.items || []).forEach((item) => {
                historyItems.push({
                    drugName: item.drugName,
                    quantity: item.quantity,
                    date: dateString,
                    assignedBy: disp.pharmacistName,
                });
            });
        });

        showSuccessAlert(response.data?.message || " تم جلب سجل المريض بنجاح.");

        return historyItems;
    } catch (error) {
        console.error("خطأ في جلب سجل الصرف:", error);
        showSuccessAlert(` فشل في جلب سجل الصرف: ${error.response?.data?.message || error.message}`);
        return [];
    }
};

// ----------------------------------------------------
// 2.2 جلب تفاصيل مريض واحد (مع الأدوية) من الـ API
// ----------------------------------------------------
const fetchPatientDetails = async (fileNumber) => {
    try {
        const response = await api.get(`/patients/${fileNumber}`);
        const patientData = response.data?.data ?? response.data;

        return {
            ...patientData,
            fileNumber: patientData.fileNumber ?? fileNumber,
            // خصائص العرض
            nameDisplay: patientData.name ?? patientData.nameDisplay ?? "غير متوفر",
            nationalIdDisplay:
                patientData.nationalId ??
                patientData.nationalIdDisplay ??
                patientData.national_id ??
                "غير متوفر",
            birthDisplay:
                patientData.birth ??
                patientData.birthDisplay ??
                patientData.birth_date ??
                "غير متوفر",
            // التأكد من أن الأدوية في الشكل المتوقع للمودال
            medications: (patientData.medications || []).map((med) => {
                const unit = med.unit || "حبة";
                const monthlyQty = med.monthlyQuantityNum || med.monthlyQuantity || 0;

                let monthlyQuantityText = med.monthlyQuantity;
                if (!monthlyQuantityText) {
                    monthlyQuantityText =
                        monthlyQty > 0 ? `${monthlyQty} ${unit}` : "غير محدد";
                }

                let dosageText = med.dosage;
                if (!dosageText || typeof dosageText === "number") {
                    // استخدام daily_quantity مباشرة من البيانات
                    const dailyQty = med.dailyQuantity || med.daily_quantity || 0;
                    dosageText =
                        dailyQty > 0
                            ? `${dailyQty} ${unit} يومياً`
                            : "غير محدد";
                }

                return {
                    ...med,
                    drugName: med.drugName || med.name || "",
                    dosage: dosageText,
                    monthlyQuantity: monthlyQuantityText,
                    assignmentDate:
                        med.assignmentDate ||
                        new Date()
                            .toISOString()
                            .split("T")[0]
                            .replace(/-/g, "/"),
                    assignedBy: med.assignedBy || "غير محدد",
                    eligibilityStatus: med.eligibilityStatus || "مستحق",
                    dispensedQuantity: med.dispensedQuantity || 0,
                    // معلومات الصرف الشهري
                    totalDispensedThisMonth: med.totalDispensedThisMonth || 0,
                    remainingQuantity: med.remainingQuantity !== undefined ? med.remainingQuantity : (monthlyQty - (med.totalDispensedThisMonth || 0)),
                    remainingQuantityText: med.remainingQuantityText || "",
                };
            }),
        };
    } catch (error) {
        console.error("خطأ في جلب بيانات المريض:", error);
        // في حالة الفشل نرجع بيانات أساسية بدون أدوية
        return {
            fileNumber,
            nameDisplay: "غير متوفر",
            nationalIdDisplay: "غير متوفر",
            birthDisplay: "غير متوفر",
            medications: [],
        };
    }
};

// ----------------------------------------------------
// 3. منطق البحث والفرز الموحد (باقي كما هو)
// ----------------------------------------------------
const searchTerm = ref("");
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);
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

// دالة تحويل التاريخ من صيغة (yyyy/mm/dd) إلى كائن Date للمقارنة
const parseDate = (dateString) => {
    if (!dateString) return null;
    try {
        // محاولة تحويل الصيغة Y/m/d إلى Date
        if (dateString.includes('/')) {
            const parts = dateString.split('/');
            if (parts.length === 3) {
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }
        }
        const date = new Date(dateString);
        return isNaN(date.getTime()) ? null : date;
    } catch {
        return null;
    }
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
    let list = patients.value;
    
    // 1. البحث في جميع الحقول
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

    // 2. فلترة حسب التاريخ (تاريخ الميلاد)
    if (dateFrom.value || dateTo.value) {
        list = list.filter((patient) => {
            const birthDate = patient.birth;
            if (!birthDate || birthDate === 'غير متوفر') return false;

            const birthDateObj = parseDate(birthDate);
            if (!birthDateObj) return false;

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

    // 3. الفرز
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
// 4. نظام التنبيهات المطور (Toast System)
// ----------------------------------------------------
const isAlertVisible = ref(false);
const alertMessage = ref("");
const alertType = ref("success");
const alertActionLabel = ref("");
const undoData = ref(null);
let alertTimeout = null;
let undoTimeout = null;

const showAlert = (message, type = "success", actionLabel = "", undoInfo = null) => {
    if (alertTimeout) {
        clearTimeout(alertTimeout);
    }
    if (undoTimeout) {
        clearTimeout(undoTimeout);
    }

    alertMessage.value = message;
    alertType.value = type;
    alertActionLabel.value = actionLabel;
    undoData.value = undoInfo;
    isAlertVisible.value = true;

    // إخفاء زر التراجع بعد 7 ثوانٍ (بتغيير الـ actionLabel)
    if (actionLabel) {
        undoTimeout = setTimeout(() => {
            alertActionLabel.value = "";
            undoData.value = null;
        }, 7000);
    }

    alertTimeout = setTimeout(() => {
        isAlertVisible.value = false;
    }, actionLabel ? 10000 : 4000);
};

const showSuccessAlert = (message, enableUndo = false, undoInfo = null) => {
    showAlert(message, "success", enableUndo ? "تراجع" : "", undoInfo);
};

const showErrorAlert = (message) => showAlert(message, "error");

const handleAlertAction = () => {
    if (alertActionLabel.value === "تراجع") {
        handleUndoDispense();
    }
};

// دالة التراجع عن الصرف
const handleUndoDispense = async () => {
    if (!undoData.value) {
        return;
    }

    try {
        await api.post("/dispense/undo", {
            dispensations: undoData.value.dispensations,
            inventory_changes: undoData.value.inventory_changes,
        });

        // تحديث البيانات في الواجهة
        await fetchPatients();

        // إخفاء التنبيه
        isAlertVisible.value = false;
        undoData.value = null;
        if (undoTimeout) {
            clearTimeout(undoTimeout);
        }

        // إظهار رسالة نجاح التراجع
        showSuccessAlert(" تم التراجع عن صرف الأدوية بنجاح", false);
    } catch (error) {
        console.error("خطأ في التراجع عن صرف الأدوية:", error);
        showErrorAlert(` فشل في التراجع: ${error.response?.data?.message || error.message}`);
    }
};

// ----------------------------------------------------
// 5. حالة الـ Modals ودو ال فتح وإغلاق (باقي كما هو)
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isDispensationModalOpen = ref(false);
const selectedPatient = ref({});

const openViewModal = async (patient) => {
    try {
        const patientData = await fetchPatientDetails(patient.fileNumber);
        selectedPatient.value = patientData;
    } catch (error) {
        console.error("خطأ في فتح ملف المريض للصيدلي:", error);
        // fallback: استخدام بيانات الصف فقط
        selectedPatient.value = {
            ...patient,
            nameDisplay: patient.name || patient.nameDisplay || "غير متوفر",
            nationalIdDisplay:
                patient.nationalId || patient.nationalIdDisplay || "غير متوفر",
            birthDisplay:
                patient.birth || patient.birthDisplay || "غير متوفر",
            medications: patient.medications || [],
        };
    }
    isViewModalOpen.value = true;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedPatient.value = {};
};

const openDispensationModal = async () => {
    if (!selectedPatient.value || !selectedPatient.value.fileNumber) {
        return;
    }

    // جلب سجل الصرف من الـ API وتخزينه داخل المريض المحدد
    const history = await fetchDispensationHistory(selectedPatient.value.fileNumber);
    selectedPatient.value = {
        ...selectedPatient.value,
        dispensationHistory: history,
    };

    isDispensationModalOpen.value = true;
    isViewModalOpen.value = false;
};

const closeDispensationModal = () => {
    isDispensationModalOpen.value = false;
    isViewModalOpen.value = true;
};

// ----------------------------------------------------
// 6. دوال إدارة الأدوية (محدثة للإشارة لعملية API)
// ----------------------------------------------------
const handleDeleteMedication = async (medIndex) => {
    const patientIndex = patients.value.findIndex(p => p.fileNumber === selectedPatient.value.fileNumber);
    if (patientIndex !== -1) {
        const medication = patients.value[patientIndex].medications[medIndex];
        
        try {
            // **هنا يجب استدعاء API لحذف الدواء، مثلاً:**
            // await axios.delete(`${API_BASE_URL}/patients/${selectedPatient.value.fileNumber}/medications/${medication.id}`);
            
            // تحديث الواجهة بعد الحذف الناجح
            patients.value[patientIndex].medications.splice(medIndex, 1);
            selectedPatient.value = patients.value[patientIndex];
            showSuccessAlert(`🗑️ تم حذف الدواء ${medication.drugName} بنجاح`);

        } catch (error) {
             console.error('خطأ في عملية حذف الدواء:', error);
             showSuccessAlert(` فشل في حذف الدواء: ${error.message}`);
        }
    }
};

// ----------------------------------------------------
// 7. منطق الطباعة (باقي كما هو)
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredPatients.value.length;

    const printWindow = window.open('', '_blank', 'height=600,width=800');

    if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
        showSuccessAlert(" فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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

        <h1>قائمة المرضى </h1>

        <p class="results-info">عدد النتائج : ${resultsCount}</p>

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
        showSuccessAlert(" تم تجهيز التقرير بنجاح للطباعة.");
    };
};
</script>

<template>
    <div class="drawer lg:drawer-open" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />

            <main class="flex-1 p-4 sm:p-5 pt-3">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-3 gap-3 sm:gap-0">

                    <div class="flex flex-col sm:flex-row justify-between items-center  gap-3 sm:gap-0">
                        <div class="flex items-center gap-3 w-full sm:max-w-xl">
                            <search v-model="searchTerm" placeholder="ابحث في جميع الحقول (رقم الملف، الاسم، الرقم الوطني، تاريخ الميلاد، الهاتف...)" />
                            
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
                    <div class="flex items-center gap-5 w-full sm:w-auto justify-end">
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

                                <tbody class="text-gray-800">
                                    <tr v-if="isLoading">
                                        <td colspan="6" class="p-4">
                                            <TableSkeleton :rows="5" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="hasError">
                                        <td colspan="6" class="py-12">
                                            <ErrorState :message="errorMessage" :retry="fetchPatients" />
                                        </td>
                                    </tr>
                                    <template v-else>
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
                                                    <button @click="openViewModal(patient)" class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                          data-tip="معاينة  ">
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
                                                <EmptyState message="لا توجد بيانات مرضى لعرضها" />
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

    <PatientViewModal
        :is-open="isViewModalOpen"
        :patient="selectedPatient"
        @close="closeViewModal"
        @dispensation-record="openDispensationModal"
        @delete-medication="handleDeleteMedication"
        @confirm-dispensation="handleConfirmation" />

    <DispensationModal
        :is-open="isDispensationModalOpen"
        :patient="selectedPatient"
        :dispensation-history="selectedPatient.dispensationHistory || []"
        @close="closeDispensationModal"
    />

    <Toast
        :show="isAlertVisible"
        :message="alertMessage"
        :type="alertType"
        :action-label="alertActionLabel"
        @close="isAlertVisible = false"
        @action="handleAlertAction"
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
</style>