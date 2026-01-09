<script setup>
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

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
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. إعدادات API
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
    (error) => {
        return Promise.reject(error);
    }
);

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

// جلب جميع البيانات
const fetchAllData = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        // جلب البيانات بشكل متوازي
        const [hospitalsResponse, suppliersResponse, usersResponse] = await Promise.all([
            api.get('/super-admin/hospitals'),
            api.get('/super-admin/suppliers'),
            api.get('/super-admin/users')
        ]);
        
        // معالجة بيانات المستشفيات
        const hospitalsData = hospitalsResponse.data.data || [];
        hospitals.value = hospitalsData.map(hospital => ({
            ...hospital,
            id: hospital.id,
            name: hospital.name,
            nameDisplay: hospital.name || "",
            code: hospital.code,
            type: hospital.type,
            city: hospital.city,
            address: hospital.address,
            phone: hospital.phone,
            isActive: hospital.status === 'active',
            status: hospital.status,
            supplierNameDisplay: hospital.supplier?.name || "",
            supplierId: hospital.supplier?.id || null,
            supplier: hospital.supplier || null,
            managerNameDisplay: hospital.admin?.name || "",
            managerId: hospital.admin?.id || null,
            admin: hospital.admin || null,
            region: hospital.city || "", // للتوافق مع الكود الحالي
            lastUpdated: hospital.createdAt || new Date().toISOString()
        }));
        
        // معالجة بيانات الموردين (فقط الذين لم يتم تعيينهم في مستشفى)
        const suppliersData = suppliersResponse.data.data || [];
        
        availableSuppliers.value = suppliersData
            .map(supplier => ({
                ...supplier,
                id: supplier.id,
                name: supplier.name,
                isActive: supplier.status === 'active' || supplier.status === true
            }));
        
        // معالجة بيانات المدراء (فقط hospital_admin الذين لم يتم تعيينهم في مستشفى)
        const usersData = usersResponse.data.data || [];
        availableManagers.value = usersData
            .filter(user => user.type === 'hospital_admin')
            .map(user => ({
                ...user,
                id: user.id,
                name: user.fullName || user.full_name || user.name || '',
                email: user.email || '',
                phone: user.phone || '',
                isActive: user.status === 'active' || user.status === true
            }));
        
    } catch (err) {
        console.error("Error fetching all data:", err);
        error.value = err.response?.data?.message || "فشل في تحميل البيانات من الخادم.";
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

// الحصول على قائمة الموردين المتاحين (غير المعينين في مستشفى)
const availableSuppliersForHospitals = computed(() => {
    // الحصول على قائمة IDs الموردين المعينين في المستشفيات الحالية
    // استثناء المستشفى الحالي عند التعديل
    const currentHospitalId = selectedHospital.value?.id;
    const assignedSupplierIds = new Set(
        hospitals.value
            .filter(h => h.supplierId && h.id !== currentHospitalId)
            .map(h => h.supplierId)
    );
    
    // إضافة المورد الحالي للمستشفى إذا كان موجوداً (للسماح بالاحتفاظ به)
    if (selectedHospital.value?.supplierId) {
        assignedSupplierIds.delete(selectedHospital.value.supplierId);
    }
    
    return availableSuppliers.value.filter(supplier => 
        supplier.isActive && !assignedSupplierIds.has(supplier.id)
    );
});

// الحصول على قائمة المدراء المتاحين (غير المعينين في مستشفى)
const availableManagersForHospitals = computed(() => {
    // الحصول على قائمة IDs المدراء المعينين في المستشفيات الحالية
    // استثناء المستشفى الحالي عند التعديل
    const currentHospitalId = selectedHospital.value?.id;
    const assignedManagerIds = new Set(
        hospitals.value
            .filter(h => h.managerId && h.id !== currentHospitalId)
            .map(h => h.managerId)
    );
    
    // إضافة المدير الحالي للمستشفى إذا كان موجوداً (للسماح بالاحتفاظ به)
    if (selectedHospital.value?.managerId) {
        assignedManagerIds.delete(selectedHospital.value.managerId);
    }
    
    return availableManagers.value.filter(manager => 
        manager.isActive && !assignedManagerIds.has(manager.id)
    );
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

    const isActivating = !hospitalToToggle.value.isActive;

    try {
        // استخدام endpoint مختلف حسب العملية
        const endpoint = isActivating 
            ? `/super-admin/hospitals/${hospitalId}/activate`
            : `/super-admin/hospitals/${hospitalId}/deactivate`;
        
        const response = await api.patch(endpoint);

        // تحديث البيانات من الخادم للحصول على البيانات الكاملة
        await fetchAllData();

        showSuccessAlert(
            response.data.message || `✅ تم ${statusAction.value} المستشفى ${hospitalToToggle.value.name} بنجاح!`
        );
        closeStatusConfirmationModal();
    } catch (error) {
        console.error(`Error ${statusAction.value} hospital:`, error);
        const errorMessage = error.response?.data?.message || `فشل ${statusAction.value} المستشفى.`;
        showSuccessAlert(`❌ ${errorMessage}`);
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
                hospital.managerNameDisplay?.toLowerCase().includes(search) ||
                hospital.supplierNameDisplay?.toLowerCase().includes(search) ||
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
                comparison = (a.managerNameDisplay || "").localeCompare(b.managerNameDisplay || "", "ar");
            } else if (sortKey.value === "region") {
                comparison = (a.region || "").localeCompare(b.region || "", "ar");
            } else if (sortKey.value === "supplier") {
                const supplierA = a.supplierNameDisplay || "";
                const supplierB = b.supplierNameDisplay || "";
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
        // تحضير البيانات للـ API
        const hospitalData = {
            name: newHospital.name,
            code: newHospital.code,
            type: newHospital.type,
            city: newHospital.city,
            address: newHospital.address || null,
            phone: newHospital.phone || null,
            supplier_id: newHospital.supplierId || null
        };

        const response = await api.post('/super-admin/hospitals', hospitalData);
        
        // تحديث البيانات من الخادم للحصول على البيانات الكاملة
        await fetchAllData();
        
        closeAddModal();
        showSuccessAlert(response.data.message || "✅ تم إنشاء المستشفى بنجاح!");
    } catch (error) {
        console.error("Error adding hospital:", error);
        const errorMessage = error.response?.data?.message || "فشل إنشاء المستشفى. تحقق من البيانات.";
        showSuccessAlert("❌ " + errorMessage);
    }
};

// تحديث بيانات مستشفى
const updateHospital = async (updatedHospital) => {
    try {
        // تحضير البيانات للـ API
        const hospitalData = {
            name: updatedHospital.name,
            code: updatedHospital.code,
            type: updatedHospital.type,
            city: updatedHospital.city,
            address: updatedHospital.address || null,
            phone: updatedHospital.phone || null,
            supplier_id: updatedHospital.supplierId || null
        };

        const response = await api.put(
            `/super-admin/hospitals/${updatedHospital.id}`,
            hospitalData
        );

        // تحديث البيانات من الخادم للحصول على البيانات الكاملة
        await fetchAllData();

        closeEditModal();
        showSuccessAlert(
            response.data.message || `✅ تم تعديل بيانات المستشفى ${updatedHospital.name} بنجاح!`
        );
    } catch (error) {
        console.error("Error updating hospital:", error);
        const errorMessage = error.response?.data?.message || "فشل تعديل بيانات المستشفى.";
        showSuccessAlert("❌ " + errorMessage);
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
                <td>${hospital.managerNameDisplay || 'لا يوجد'}</td>
                <td>${hospital.supplierNameDisplay || 'لا يوجد'}</td>
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

                                <tbody class="text-gray-800">
                                    <tr v-if="loading">
                                        <td colspan="8" class="p-4">
                                            <TableSkeleton :rows="10" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="error">
                                        <td colspan="8" class="py-12">
                                            <ErrorState :message="error" :retry="fetchAllData" />
                                        </td>
                                    </tr>
                                    <template v-else>
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
                                                {{ hospital.managerNameDisplay || '-' }}
                                            </td>
                                            <td class="supplier-col">
                                                {{ hospital.supplierNameDisplay || '-' }}
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
                                                <div class="flex gap-2 justify-center items-center">
                                                    <!-- زر العرض -->
                                                    <button
                                                        @click="openViewModal(hospital)"
                                                        class="w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-200 bg-emerald-50 border border-emerald-200 text-emerald-600 hover:bg-emerald-100 hover:scale-105 active:scale-95"
                                                        title="عرض البيانات"
                                                    >
                                                        <Icon
                                                            icon="solar:eye-broken"
                                                            class="w-5 h-5"
                                                        />
                                                    </button>

                                                    <!-- زر التعديل -->
                                                    <button
                                                        @click="openEditModal(hospital)"
                                                        class="w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-200 bg-amber-50 border border-amber-200 text-amber-600 hover:bg-amber-100 hover:scale-105 active:scale-95"
                                                        title="تعديل البيانات"
                                                    >
                                                        <Icon
                                                            icon="solar:pen-new-square-linear"
                                                            class="w-5 h-5"
                                                        />
                                                    </button>

                                                    <!-- زر تفعيل/تعطيل المستشفى -->
                                                    <button
                                                        @click="openStatusConfirmationModal(hospital)"
                                                        :class="[
                                                            'w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-200 border hover:scale-105 active:scale-95',
                                                            hospital.isActive
                                                                ? 'bg-red-50 border-red-200 text-red-600 hover:bg-red-100'
                                                                : 'bg-emerald-50 border-emerald-200 text-emerald-600 hover:bg-emerald-100',
                                                        ]"
                                                        :title="getStatusTooltip(hospital.isActive)"
                                                    >
                                                        <Icon
                                                            v-if="hospital.isActive"
                                                            icon="solar:forbidden-circle-linear"
                                                            class="w-5 h-5"
                                                        />
                                                        <Icon
                                                            v-else
                                                            icon="solar:power-bold"
                                                            class="w-5 h-5"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr v-if="filteredHospitals.length === 0">
                                            <td colspan="8" class="py-12">
                                                <EmptyState message="لا توجد بيانات مستشفيات حالياً" />
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
        :available-suppliers="availableSuppliers"
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

    <Toast 
        :show="toast.show" 
        :type="toast.type" 
        :title="toast.title" 
        :message="toast.message" 
        @close="toast.show = false" 
    />
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