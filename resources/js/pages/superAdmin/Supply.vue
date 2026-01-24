<script setup>
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import inputadd from "@/components/btnaddsu.vue";
import btnprint from "@/components/btnprint.vue";
import supplyAddModel from "@/components/forsuperadmin/supplyAddModel.vue";
import supplyEditModel from "@/components/forsuperadmin/supplyEditModel.vue";
import supplyViewModel from "@/components/forsuperadmin/supplyViewModel.vue";
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
const statusFilter = ref("all");
const suppliers = ref([]);
const availableManagers = ref([]);

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
        const [suppliersResponse, usersResponse] = await Promise.all([
            api.get('/super-admin/suppliers'),
            api.get('/super-admin/users')
        ]);
        
        // معالجة بيانات الموردين
        const suppliersData = suppliersResponse.data.data || [];
        suppliers.value = suppliersData.map(supplier => ({
            ...supplier,
            id: supplier.id,
            name: supplier.name,
            code: supplier.code,
            city: supplier.city,
            address: supplier.address,
            phone: supplier.phone,
            isActive: supplier.status === 'active',
            status: supplier.status,
            admin: supplier.admin || null,
            managerName: supplier.admin?.name || supplier.admin?.full_name || supplier.admin?.fullName || '',
            managerId: supplier.admin?.id || null,
            lastUpdated: supplier.createdAt || new Date().toISOString()
        }));
        
        // معالجة بيانات المدراء (جميع supplier_admin النشطين)
        const usersData = usersResponse.data.data || [];
        availableManagers.value = usersData
            .filter(user => user.type === 'supplier_admin' && (user.status === 'active' || user.status === true))
            .map(user => ({
                ...user,
                id: user.id,
                name: user.fullName || user.full_name || user.name || '',
                email: user.email || '',
                phone: user.phone || '',
                isActive: user.status === 'active' || user.status === true,
                supplierId: user.supplier?.id || user.supplier_id || null
            }));
        
    } catch (err) {
        console.error("Error fetching suppliers data:", err);
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

// الحصول على قائمة المدراء المتاحين (غير المعينين في مورد)
const availableManagersForSuppliers = computed(() => {
    // الحصول على قائمة IDs المدراء المعينين في الموردين الحالية
    // استثناء المورد الحالي عند التعديل
    const currentSupplierId = selectedSupplier.value?.id;
    const assignedManagerIds = new Set(
        suppliers.value
            .filter(s => s.managerId && s.id !== currentSupplierId)
            .map(s => s.managerId)
    );
    
    // إضافة المدير الحالي للمورد إذا كان موجوداً (للسماح بالاحتفاظ به)
    if (selectedSupplier.value?.managerId) {
        assignedManagerIds.delete(selectedSupplier.value.managerId);
    }
    
    // إرجاع جميع المدراء النشطين غير المعينين في مورد آخر
    // أو المدير الحالي للمورد (للسماح بالاحتفاظ به)
    return availableManagers.value.filter(manager => {
        // السماح بالمدير الحالي للمورد
        if (selectedSupplier.value?.managerId === manager.id) {
            return true;
        }
        // السماح فقط بالمدراء النشطين وغير المعينين في مورد آخر
        return manager.isActive && !assignedManagerIds.has(manager.id);
    });
});

// ----------------------------------------------------
// 6. متغيرات نافذة تأكيد التفعيل/التعطيل
// ----------------------------------------------------

const isStatusConfirmationModalOpen = ref(false);
const supplierToToggle = ref(null);
const statusAction = ref("");

const openStatusConfirmationModal = (supplier) => {
    supplierToToggle.value = supplier;
    statusAction.value = supplier.isActive ? "تعطيل" : "تفعيل";
    isStatusConfirmationModalOpen.value = true;
};

const closeStatusConfirmationModal = () => {
    isStatusConfirmationModalOpen.value = false;
    supplierToToggle.value = null;
    statusAction.value = "";
};

const confirmStatusToggle = async () => {
    if (!supplierToToggle.value) return;

    const supplierId = supplierToToggle.value.id;
    const isActivating = !supplierToToggle.value.isActive;

    try {
        const endpoint = isActivating 
            ? `/super-admin/suppliers/${supplierId}/activate`
            : `/super-admin/suppliers/${supplierId}/deactivate`;
        
        const response = await api.patch(endpoint);

        await fetchAllData();

        showSuccessAlert(
            response.data.message || `✅ تم ${statusAction.value} المورد ${supplierToToggle.value.name} بنجاح!`
        );
        closeStatusConfirmationModal();
    } catch (error) {
        console.error(`Error ${statusAction.value} supplier:`, error);
        const errorMessage = error.response?.data?.message || `فشل ${statusAction.value} المورد.`;
        showSuccessAlert(`❌ ${errorMessage}`);
        closeStatusConfirmationModal();
    }
};

// ----------------------------------------------------
// 6. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("lastUpdated");
const sortOrder = ref("desc");

const sortSuppliers = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredSuppliers = computed(() => {
    let list = suppliers.value;

    // فلتر حسب الحالة
    if (statusFilter.value !== "all") {
        const isActiveFilter = statusFilter.value === "active";
        list = list.filter((supplier) => supplier.isActive === isActiveFilter);
    }

    // فلتر حسب البحث
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (supplier) =>
                supplier.id?.toString().includes(search) ||
                supplier.name?.toLowerCase().includes(search) ||
                supplier.code?.toLowerCase().includes(search) ||
                supplier.city?.toLowerCase().includes(search) ||
                supplier.phone?.includes(search) ||
                supplier.managerName?.toLowerCase().includes(search)
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
            } else if (sortKey.value === "code") {
                comparison = (a.code || "").localeCompare(b.code || "", "ar");
            } else if (sortKey.value === "managerName") {
                comparison = (a.managerName || "").localeCompare(b.managerName || "", "ar");
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
// 7. منطق رسالة النجاح
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
// 8. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAddModalOpen = ref(false);
const selectedSupplier = ref({});

const openViewModal = (supplier) => {
    selectedSupplier.value = { ...supplier };
    isViewModalOpen.value = true;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedSupplier.value = {};
};

const openEditModal = (supplier) => {
    selectedSupplier.value = { ...supplier };
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    selectedSupplier.value = {};
};

const openAddModal = () => {
    isAddModalOpen.value = true;
};

const closeAddModal = () => {
    isAddModalOpen.value = false;
};

// ----------------------------------------------------
// 9. دوال إدارة البيانات
// ----------------------------------------------------

// إضافة مورد جديد
const addSupplier = async (newSupplier) => {
    try {
        const supplierData = {
            name: newSupplier.name,
            code: newSupplier.code,
            city: newSupplier.city,
            address: newSupplier.address || null,
            phone: newSupplier.phone || null,
            admin_id: newSupplier.managerId || null,
        };

        const response = await api.post('/super-admin/suppliers', supplierData);
        
        await fetchAllData();
        
        closeAddModal();
        showSuccessAlert(response.data.message || "✅ تم إنشاء المورد بنجاح!");
    } catch (error) {
        console.error("Error adding supplier:", error);
        const errorMessage = error.response?.data?.message || "فشل إنشاء المورد. تحقق من البيانات.";
        showSuccessAlert("❌ " + errorMessage);
    }
};

// تحديث بيانات مورد
const updateSupplier = async (updatedSupplier) => {
    try {
        const supplierData = {
            name: updatedSupplier.name,
            code: updatedSupplier.code,
            city: updatedSupplier.city,
            address: updatedSupplier.address || null,
            phone: updatedSupplier.phone || null,
            admin_id: updatedSupplier.managerId || null,
        };

        const response = await api.put(
            `/super-admin/suppliers/${updatedSupplier.id}`,
            supplierData
        );

        await fetchAllData();

        closeEditModal();
        showSuccessAlert(
            response.data.message || `✅ تم تعديل بيانات المورد ${updatedSupplier.name} بنجاح!`
        );
    } catch (error) {
        console.error("Error updating supplier:", error);
        const errorMessage = error.response?.data?.message || "فشل تعديل بيانات المورد.";
        showSuccessAlert("❌ " + errorMessage);
    }
};

const getStatusTooltip = (isActive) => {
    return isActive ? "تعطيل المورد" : "تفعيل المورد";
};

// ----------------------------------------------------
// 10. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredSuppliers.value.length;

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

        <h1>قائمة شركات التوريد (تقرير طباعة)</h1>
        
        <p class="results-info">
            عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>رقم المورد</th>
                    <th>اسم المورد</th>
                    <th>كود المورد</th>
                    <th>المدينة</th>
                    <th>المسؤول</th>
                    <th>العنوان</th>
                    <th>رقم الهاتف</th>
                    <th>الحالة</th>
                    <th>تاريخ التحديث</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredSuppliers.value.forEach((supplier) => {
        tableHtml += `
            <tr>
                <td>${supplier.id || ''}</td>
                <td>${supplier.name || ''}</td>
                <td>${supplier.code || '-'}</td>
                <td>${supplier.city || '-'}</td>
                <td>${supplier.managerName || '-'}</td>
                <td>${supplier.address || '-'}</td>
                <td>${supplier.phone || '-'}</td>
                <td class="${supplier.isActive ? "status-active" : "status-inactive"}">
                    ${supplier.isActive ? "مفعل" : "معطل"}
                </td>
                <td>${new Date(supplier.lastUpdated).toLocaleDateString('ar-SA')}</td>
            </tr>
        `;
    });

    tableHtml += `
            </tbody>
        </table>
    `;

    printWindow.document.write("<html><head><title>طباعة قائمة شركات التوريد</title>");
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
            <div>
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
                                    حسب اسم المورد:
                                </li>
                                <li>
                                    <a
                                        @click="sortSuppliers('name', 'asc')"
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
                                        @click="sortSuppliers('name', 'desc')"
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
                                        @click="sortSuppliers('city', 'asc')"
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
                                        @click="sortSuppliers('city', 'desc')"
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
                                    حسب المسؤول:
                                </li>
                                <li>
                                    <a
                                        @click="sortSuppliers('managerName', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'managerName' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        المسؤول (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortSuppliers('managerName', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'managerName' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        المسؤول (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب حالة المورد:
                                </li>
                                <li>
                                    <a
                                        @click="sortSuppliers('status', 'asc')"
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
                                        @click="sortSuppliers('status', 'desc')"
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
                                        @click="sortSuppliers('lastUpdated', 'desc')"
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
                                        @click="sortSuppliers('lastUpdated', 'asc')"
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
                                filteredSuppliers.length
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
                                        <th class="id-col">رقم المورد</th>
                                        <th class="name-col">اسم المورد</th>
                                        <th class="code-col">كود المورد</th>
                                        <th class="city-col">المدينة</th>
                                        <th class="manager-col">المسؤول</th>
                                        <th class="phone-col">رقم الهاتف</th>
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
                                            v-for="(supplier, index) in filteredSuppliers"
                                            :key="supplier.id || index"
                                            class="hover:bg-gray-100 border border-gray-300"
                                        >
                                            <td class="id-col">
                                                {{ supplier.id }}
                                            </td>
                                            <td class="name-col">
                                                {{ supplier.name }}
                                            </td>
                                            <td class="code-col">
                                                {{ supplier.code }}
                                            </td>
                                            <td class="city-col">
                                                {{ supplier.city }}
                                            </td>
                                            <td class="manager-col">
                                                {{ supplier.managerName || '-' }}
                                            </td>
                                            <td class="phone-col">
                                                {{ supplier.phone || '-' }}
                                            </td>
                                            <td class="status-col">
                                                <span
                                                    :class="[
                                                        'px-2 py-1 rounded-full text-xs font-semibold',
                                                        supplier.isActive
                                                            ? 'bg-green-100 text-green-800 border border-green-200'
                                                            : 'bg-red-100 text-red-800 border border-red-200',
                                                    ]"
                                                >
                                                    {{
                                                        supplier.isActive
                                                            ? "مفعل"
                                                            : "معطل"
                                                    }}
                                                </span>
                                            </td>

                                            <td class="actions-col">
                                                <div class="flex gap-2 justify-center items-center">
                                                    <!-- زر العرض -->
                                                    <button
                                                        @click="openViewModal(supplier)"
                                                        class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        title="عرض البيانات"
                                                    >
                                                        <Icon
                                                            icon="tabler:eye-minus"
                                                            class="w-4 h-4 text-green-600"
                                                        />
                                                    </button>

                                                    <!-- زر التعديل -->
                                                    <button
                                                        @click="openEditModal(supplier)"
                                                        class="w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-200 bg-amber-50 border border-amber-200 text-amber-600 hover:bg-amber-100 hover:scale-105 active:scale-95"
                                                        title="تعديل البيانات"
                                                    >
                                                        <Icon
                                                            icon="solar:pen-new-square-linear"
                                                            class="w-5 h-5"
                                                        />
                                                    </button>

                                                    <!-- زر تفعيل/تعطيل المورد -->
                                                    <button
                                                        @click="openStatusConfirmationModal(supplier)"
                                                        :class="[
                                                            'w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-200 border hover:scale-105 active:scale-95',
                                                            supplier.isActive
                                                                ? 'bg-red-50 border-red-200 text-red-600 hover:bg-red-100'
                                                                : 'bg-emerald-50 border-emerald-200 text-emerald-600 hover:bg-emerald-100',
                                                        ]"
                                                        :title="getStatusTooltip(supplier.isActive)"
                                                    >
                                                        <Icon
                                                            v-if="supplier.isActive"
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

                                        <tr v-if="filteredSuppliers.length === 0">
                                            <td colspan="8" class="py-12">
                                                <EmptyState message="لا توجد بيانات موردين حالياً" />
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
    <supplyAddModel
        :is-open="isAddModalOpen"
        :available-managers="availableManagersForSuppliers"
        @close="closeAddModal"
        @save="addSupplier"
    />

    <supplyEditModel
        :is-open="isEditModalOpen"
        :supplier="selectedSupplier"
        :available-managers="availableManagersForSuppliers"
        @close="closeEditModal"
        @save="updateSupplier"
    />

    <supplyViewModel
        :is-open="isViewModalOpen"
        :supplier="selectedSupplier"
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
                            ? "تفعيل المورد"
                            : "تعطيل المورد"
                    }}
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في {{ statusAction }} المورد
                    <strong>{{ supplierToToggle?.name }}</strong
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
.code-col {
    width: 120px;
    min-width: 120px;
}
.name-col {
    width: 200px;
    min-width: 200px;
}
.city-col {
    width: 120px;
    min-width: 120px;
}
.manager-col {
    width: 150px;
    min-width: 150px;
}
.phone-col {
    width: 140px;
    min-width: 140px;
}
</style>
