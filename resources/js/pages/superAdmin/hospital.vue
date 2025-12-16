<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import inputadd from "@/components/btnaddhos.vue";
import btnprint from "@/components/btnprint.vue";
import hospitalAddModel from "@/components/forsuperadmin/hospitalAddModel.vue";
import hospitalEditModel from "@/components/forsuperadmin/hospitalEditModel.vue";
import hospitalViewModel from "@/components/forsuperadmin/hospitalViewModel.vue";

// ----------------------------------------------------
// 1. إعدادات API واحدة فقط
// ----------------------------------------------------
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "http://localhost:3000";
const API_URL = `${API_BASE_URL}/api`;

// بيانات التطبيق
const availableSuppliers = ref([]);
const availableManagers = ref([]);
const statusFilter = ref("all");
const hospitals = ref([]);

// ----------------------------------------------------
// 2. الحالة العامة للتطبيق
// ----------------------------------------------------
const loading = ref(true);
const error = ref(null);

// ----------------------------------------------------
// 3. دوال جلب البيانات من API
// ----------------------------------------------------

// جلب جميع البيانات مرة واحدة
const fetchAllData = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        // جلب جميع البيانات في طلب واحد
        const response = await axios.get(`${API_URL}/all-data`);
        const data = response.data;
        
        // تعيين بيانات المستشفيات
        hospitals.value = data.hospitals.map(hospital => ({
            ...hospital,
            nameDisplay: hospital.name || "",
            supplierNameDisplay: hospital.supplierName || "",
            managerNameDisplay: hospital.managerName || "",
            managerId: hospital.managerId || null
        }));
        
        // تعيين بيانات الموردين
        availableSuppliers.value = data.suppliers || [];
        
        // تعيين بيانات المدراء
        availableManagers.value = data.managers || [];
        
    } catch (err) {
        console.error("Error fetching all data:", err);
        error.value = "فشل في تحميل البيانات من الخادم.";
    } finally {
        loading.value = false;
    }
};

// تحديث البيانات عند الحاجة
const refreshData = async () => {
    await fetchAllData();
};

// ----------------------------------------------------
// 4. تحميل البيانات عند التهيئة
// ----------------------------------------------------
onMounted(async () => {
    await fetchAllData();
});

// ----------------------------------------------------
// 5. دوال الحساب
// ----------------------------------------------------

// الحصول على قائمة الموردين المتاحين
const availableSuppliersForHospitals = computed(() => {
    return availableSuppliers.value.filter(supplier => supplier.isActive);
});

// الحصول على قائمة المدراء المتاحين
const availableManagersForHospitals = computed(() => {
    return availableManagers.value.filter(manager => manager.isActive);
});

// ----------------------------------------------------
// 6. متغيرات نافذة تأكيد التفعيل/التعطيل
// ----------------------------------------------------
const isStatusConfirmationModalOpen = ref(false);
const hospitalToToggle = ref(null);
const statusAction = ref("");

const openStatusConfirmationModal = (hospital) => {
    hospitalToToggle.value = hospital;
    statusAction.value = hospital.isActive ? "تعطيل" : "تفعيل";
    isStatusConfirmationModalOpen.value = true;
};

const closeStatusConfirmationModal = () => {
    isStatusConfirmationModalOpen.value = false;
    hospitalToToggle.value = null;
    statusAction.value = "";
};

const confirmStatusToggle = async () => {
    if (!hospitalToToggle.value) return;

    const newStatus = !hospitalToToggle.value.isActive;
    const hospitalId = hospitalToToggle.value.id;

    try {
        await axios.patch(
            `${API_URL}/hospitals/${hospitalId}/toggle-status`,
            { isActive: newStatus }
        );

        // تحديث البيانات محلياً
        const index = hospitals.value.findIndex(
            (h) => h.id === hospitalId
        );
        if (index !== -1) {
            hospitals.value[index].isActive = newStatus;
            hospitals.value[index].lastUpdated = new Date().toISOString();
        }

        showSuccessAlert(
            `✅ تم ${statusAction.value} المستشفى ${hospitalToToggle.value.name} بنجاح!`
        );
        closeStatusConfirmationModal();
    } catch (error) {
        console.error(`Error ${statusAction.value} hospital:`, error);
        showSuccessAlert(`❌ فشل ${statusAction.value} المستشفى.`);
        closeStatusConfirmationModal();
    }
};

// ----------------------------------------------------
// 7. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("lastUpdated");
const sortOrder = ref("desc");

const sortHospitals = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredHospitals = computed(() => {
    let list = hospitals.value;

    // فلتر حسب الحالة
    if (statusFilter.value !== "all") {
        const isActiveFilter = statusFilter.value === "active";
        list = list.filter((hospital) => hospital.isActive === isActiveFilter);
    }

    // فلتر حسب البحث
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (hospital) =>
                hospital.id?.toString().includes(search) ||
                hospital.name?.toLowerCase().includes(search) ||
                hospital.managerName?.toLowerCase().includes(search) ||
                hospital.supplierName?.toLowerCase().includes(search) ||
                hospital.city?.toLowerCase().includes(search) ||
                hospital.region?.toLowerCase().includes(search) ||
                hospital.phone?.includes(search)
        );
    }

    // الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "name") {
                comparison = (a.name || "").localeCompare(b.name || "", "ar");
            } else if (sortKey.value === "city") {
                comparison = (a.city || "").localeCompare(b.city || "", "ar");
            } else if (sortKey.value === "managerName") {
                comparison = (a.managerName || "").localeCompare(b.managerName || "", "ar");
            } else if (sortKey.value === "region") {
                comparison = (a.region || "").localeCompare(b.region || "", "ar");
            } else if (sortKey.value === "supplier") {
                const supplierA = a.supplierName || "";
                const supplierB = b.supplierName || "";
                comparison = supplierA.localeCompare(supplierB, "ar");
            } else if (sortKey.value === "lastUpdated") {
                const dateA = new Date(a.lastUpdated || 0);
                const dateB = new Date(b.lastUpdated || 0);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "status") {
                if (a.isActive === b.isActive) comparison = 0;
                else if (a.isActive && !b.isActive) comparison = -1;
                else comparison = 1;
            }

            return sortOrder.value === "asc" ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 8. منطق رسالة النجاح
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
// 9. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAddModalOpen = ref(false);
const selectedHospital = ref({});

const openViewModal = (hospital) => {
    selectedHospital.value = { ...hospital };
    isViewModalOpen.value = true;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedHospital.value = {};
};

const openEditModal = (hospital) => {
    selectedHospital.value = { ...hospital };
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    selectedHospital.value = {};
};

const openAddModal = () => {
    isAddModalOpen.value = true;
};

const closeAddModal = () => {
    isAddModalOpen.value = false;
};

// ----------------------------------------------------
// 10. دوال إدارة البيانات
// ----------------------------------------------------

// إضافة مستشفى جديد
const addHospital = async (newHospital) => {
    try {
        const hospitalData = {
            ...newHospital,
            isActive: true
        };

        const response = await axios.post(`${API_URL}/hospitals`, hospitalData);
        
        hospitals.value.push({
            ...response.data,
            nameDisplay: response.data.name || "",
            supplierNameDisplay: response.data.supplierName || "",
            managerNameDisplay: response.data.managerName || "",
            managerId: response.data.managerId || null
        });
        
        closeAddModal();
        showSuccessAlert("✅ تم إنشاء المستشفى بنجاح!");
    } catch (error) {
        console.error("Error adding hospital:", error);
        showSuccessAlert("❌ فشل إنشاء المستشفى. تحقق من البيانات.");
    }
};

// تحديث بيانات مستشفى
const updateHospital = async (updatedHospital) => {
    try {
        const response = await axios.put(
            `${API_URL}/hospitals/${updatedHospital.id}`,
            updatedHospital
        );

        const index = hospitals.value.findIndex(
            (h) => h.id === updatedHospital.id
        );
        if (index !== -1) {
            hospitals.value[index] = {
                ...response.data,
                nameDisplay: response.data.name || "",
                supplierNameDisplay: response.data.supplierName || "",
                managerNameDisplay: response.data.managerName || "",
                managerId: response.data.managerId || null
            };
        }

        closeEditModal();
        showSuccessAlert(
            `✅ تم تعديل بيانات المستشفى ${updatedHospital.name} بنجاح!`
        );
    } catch (error) {
        console.error("Error updating hospital:", error);
        showSuccessAlert("❌ فشل تعديل بيانات المستشفى.");
    }
};

const getStatusTooltip = (isActive) => {
    return isActive ? "تعطيل المستشفى" : "تفعيل المستشفى";
};

// ----------------------------------------------------
// 11. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredHospitals.value.length;

    if (resultsCount === 0) {
        showSuccessAlert("❌ لا توجد بيانات للطباعة.");
        return;
    }

    const printWindow = window.open("", "_blank", "height=600,width=800");

    if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
        showSuccessAlert(
            "❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع."
        );
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
            .status-active {
                color: green;
                font-weight: bold;
            }
            .status-inactive {
                color: red;
                font-weight: bold;
            }
        </style>

        <h1>قائمة المستشفيات (تقرير طباعة)</h1>
        
        <p class="results-info">
            عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>رقم المستشفى</th>
                    <th>اسم المستشفى</th>
                    <th>مدير المستشفى</th> 
                    <th>اسم المورد</th>
                    <th>المدينة</th>
                    <th>المنطقة</th>
                    <th>رقم الهاتف</th>
                    <th>الحالة</th>
                    <th>تاريخ التحديث</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredHospitals.value.forEach((hospital) => {
        tableHtml += `
            <tr>
                <td>${hospital.id || ''}</td>
                <td>${hospital.name || ''}</td>
                <td>${hospital.managerName || 'لا يوجد'}</td>
                <td>${hospital.supplierName || 'لا يوجد'}</td>
                <td>${hospital.city || '-'}</td>
                <td>${hospital.region || '-'}</td>
                <td>${hospital.phone || '-'}</td>
                <td class="${hospital.isActive ? "status-active" : "status-inactive"}">
                    ${hospital.isActive ? "مفعل" : "معطل"}
                </td>
                <td>${new Date(hospital.lastUpdated).toLocaleDateString('ar-SA')}</td>
            </tr>
        `;
    });

    tableHtml += `
            </tbody>
        </table>
    `;

    printWindow.document.write("<html><head><title>طباعة قائمة المستشفيات</title>");
    printWindow.document.write("</head><body>");
    printWindow.document.write(tableHtml);
    printWindow.document.write("</body></html>");
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
           

            <!-- المحتوى الرئيسي -->
            <div >
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
                    <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <search v-model="searchTerm" />

                        <!-- فلتر الحالة -->
                        <div class="dropdown dropdown-start">
                            <div
                                tabindex="0"
                                role="button"
                                class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                            >
                                <Icon
                                    icon="mdi:filter"
                                    class="w-5 h-5 ml-2"
                                />
                                فلتر
                            </div>
                            <ul
                                tabindex="0"
                                class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-52 text-right"
                            >
                                <li class="menu-title text-gray-700 font-bold text-sm">
                                    حسب الحالة:
                                </li>
                                <li>
                                    <a
                                        @click="statusFilter = 'all'"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                statusFilter === 'all',
                                        }"
                                    >
                                        الكل
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="statusFilter = 'active'"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                statusFilter === 'active',
                                        }"
                                    >
                                        المفعلة فقط
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="statusFilter = 'inactive'"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                statusFilter === 'inactive',
                                        }"
                                    >
                                        المعطلة فقط
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- فرز -->
                        <div class="dropdown dropdown-start">
                            <div
                                tabindex="0"
                                role="button"
                                class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                            >
                                <Icon
                                    icon="lucide:arrow-down-up"
                                    class="w-5 h-5 ml-2"
                                />
                                فرز
                            </div>
                            <ul
                                tabindex="0"
                                class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-52 text-right"
                            >
                                <li class="menu-title text-gray-700 font-bold text-sm">
                                    حسب اسم المستشفى:
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('name', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'name' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الاسم (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('name', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'name' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الاسم (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب المدينة:
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('city', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'city' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        المدينة (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('city', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'city' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        المدينة (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب المورد:
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('supplier', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'supplier' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        المورد (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('supplier', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'supplier' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        المورد (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب حالة المستشفى:
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('status', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'status' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        المعطلة أولاً
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('status', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'status' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        المفعلة أولاً
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب آخر تحديث:
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('lastUpdated', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'lastUpdated' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الأحدث
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortHospitals('lastUpdated', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'lastUpdated' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الأقدم
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <p class="text-sm font-semibold text-gray-600 self-end sm:self-center">
                            عدد النتائج :
                            <span class="text-[#4DA1A9] text-lg font-bold">{{
                                filteredHospitals.length
                            }}</span>
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
                            <table
                                dir="rtl"
                                class="table w-full text-right min-w-[900px] border-collapse"
                            >
                                <thead
                                    class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                >
                                    <tr>
                                        <th class="id-col">رقم المستشفى</th>
                                        <th class="name-col">اسم المستشفى</th>
                                        <th class="manager-col">مدير المستشفى</th>
                                        <th class="supplier-col">اسم المورد</th>
                                        <th class="region-col">المنطقة</th>
                                        <th class="status-col">الحالة</th>
                                        <th class="actions-col">الإجراءات</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(hospital, index) in filteredHospitals"
                                        :key="hospital.id || index"
                                        class="hover:bg-gray-100 border border-gray-300"
                                    >
                                        <td class="id-col">
                                            {{ hospital.id }}
                                        </td>
                                        <td class="name-col">
                                            {{ hospital.name }}
                                        </td>
                                        <td class="manager-col">
                                            {{ hospital.managerName || '-' }}
                                        </td>
                                        <td class="supplier-col">
                                            {{ hospital.supplierName }}
                                        </td>
                                        <td class="region-col">
                                            {{ hospital.region}}
                                        </td>
                                        <td class="status-col">
                                            <span
                                                :class="[
                                                    'px-2 py-1 rounded-full text-xs font-semibold',
                                                    hospital.isActive
                                                        ? 'bg-green-100 text-green-800 border border-green-200'
                                                        : 'bg-red-100 text-red-800 border border-red-200',
                                                ]"
                                            >
                                                {{
                                                    hospital.isActive
                                                        ? "مفعل"
                                                        : "معطل"
                                                }}
                                            </span>
                                        </td>

                                        <td class="actions-col">
                                            <div class="flex gap-3 justify-center items-center">
                                                <button
                                                    @click="openViewModal(hospital)"
                                                    class="p-1 rounded-full hover:bg-green-100 transition-colors"
                                                    title="عرض البيانات"
                                                >
                                                    <Icon
                                                        icon="tabler:eye-minus"
                                                        class="w-5 h-5 text-green-600"
                                                    />
                                                </button>

                                                <button
                                                    @click="openEditModal(hospital)"
                                                    class="p-1 rounded-full hover:bg-yellow-100 transition-colors"
                                                    title="تعديل البيانات"
                                                >
                                                    <Icon
                                                        icon="line-md:pencil"
                                                        class="w-5 h-5 text-yellow-500"
                                                    />
                                                </button>

                                                <!-- زر تفعيل/تعطيل المستشفى -->
                                                <button
                                                    @click="openStatusConfirmationModal(hospital)"
                                                    :class="[
                                                        'p-1 rounded-full transition-colors',
                                                        hospital.isActive
                                                            ? 'hover:bg-red-100'
                                                            : 'hover:bg-green-100',
                                                    ]"
                                                    :title="getStatusTooltip(hospital.isActive)"
                                                >
                                                    <Icon
                                                        v-if="hospital.isActive"
                                                        icon="pepicons-pop:power-off"
                                                        class="w-5 h-5 text-red-600"
                                                    />
                                                    <Icon
                                                        v-else
                                                        icon="quill:off"
                                                        class="w-5 h-5 text-green-600"
                                                    />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="filteredHospitals.length === 0">
                                        <td
                                            colspan="8"
                                            class="text-center py-8 text-gray-500"
                                        >
                                            لا توجد بيانات لعرضها
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

    <!-- Modals -->
    <hospitalAddModel
        :is-open="isAddModalOpen"
        :available-suppliers="availableSuppliersForHospitals"
        :available-managers="availableManagersForHospitals"
        @close="closeAddModal"
        @save="addHospital"
    />

    <hospitalEditModel
        :is-open="isEditModalOpen"
        :available-suppliers="availableSuppliersForHospitals"
        :available-managers="availableManagersForHospitals"
        :hospital="selectedHospital"
        @close="closeEditModal"
        @save="updateHospital"
    />

    <hospitalViewModel
        :is-open="isViewModalOpen"
        :hospital="selectedHospital"
        @close="closeViewModal"
    />

    <!-- نافذة تأكيد التفعيل/التعطيل -->
    <div
        v-if="isStatusConfirmationModalOpen"
        class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="closeStatusConfirmationModal"
            class="absolute inset-0 bg-black/30 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-white rounded-xl shadow-2xl w-full sm:w-[400px] max-w-[90vw] p-6 sm:p-8 text-center rtl z-[110] transform transition-all duration-300 scale-100"
        >
            <div class="flex flex-col items-center">
                <Icon
                    icon="tabler:alert-triangle-filled"
                    class="w-16 h-16 text-yellow-500 mb-4"
                />
                <p class="text-xl font-bold text-[#2E5077] mb-3">
                    {{
                        statusAction === "تفعيل"
                            ? "تفعيل المستشفى"
                            : "تعطيل المستشفى"
                    }}
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في {{ statusAction }} المستشفى
                    <strong>{{ hospitalToToggle?.name }}</strong
                    >؟
                </p>
                <div class="flex gap-4 justify-center w-full">
                    <button
                        @click="confirmStatusToggle"
                        class="inline-flex items-center px-[25px] py-[9px] border-2 border-[#ffffff8d] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#3a8c94]"
                    >
                        تأكيد
                    </button>
                    <button
                        @click="closeStatusConfirmationModal"
                        class="inline-flex items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>

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
.manager-col {
    width: 150px;
    min-width: 150px;
}

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
    width: 130px;
    min-width: 130px;
    max-width: 130px;
    text-align: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
.id-col {
    width: 100px;
    min-width: 100px;
}
.status-col {
    width: 100px;
    min-width: 100px;
}
.supplier-col {
    width: 150px;
    min-width: 150px;
}
.name-col {
    width: 200px;
    min-width: 200px;
}
.city-col {
    width: 120px;
    min-width: 120px;
}
.region-col {
    width: 120px;
    min-width: 120px;
}
.phone-col {
    width: 140px;
    min-width: 140px;
}
</style>