<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import inputadd from "@/components/btnaddDepa.vue";
import btnprint from "@/components/btnprint.vue";
import departmentAddModel from "@/components/forhospitaladmin/departmentAddModel.vue";
import departmentEditModel from "@/components/forhospitaladmin/departmentEditModel.vue";
import departmentViewModel from "@/components/forhospitaladmin/departmentViewModel.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. إعدادات API
// ----------------------------------------------------
const DEPARTMENTS_API_URL = "/api/admin-hospital/departments";
const EMPLOYEES_API_URL = "/api/admin-hospital/staff";

// إنشاء instance مخصصة من axios مع interceptor لإضافة الـ token تلقائياً
const api = axios.create({
    baseURL: '/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة interceptor لإضافة الـ token تلقائياً
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

// إضافة interceptor للتعامل مع أخطاء المصادقة
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            console.error('Unauthenticated - Please login again.');
        }
        return Promise.reject(error);
    }
);

// بيانات التطبيق
const availableEmployees = ref([]);
const statusFilter = ref("all");
const departments = ref([]);

// ----------------------------------------------------
// 2. الحالة العامة للتطبيق
// ----------------------------------------------------
const loading = ref(true);
const loadingEmployees = ref(true);
const error = ref(null);
const employeesError = ref(null);

// ----------------------------------------------------
// 3. دوال جلب البيانات من API
// ----------------------------------------------------

// جلب بيانات الأقسام
const fetchDepartments = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await api.get('/admin-hospital/departments');
        
        // التحقق من بنية الاستجابة
        let data = [];
        if (response.data) {
            if (Array.isArray(response.data)) {
                data = response.data;
            } else if (response.data.data && Array.isArray(response.data.data)) {
                data = response.data.data;
            } else if (response.data.success && Array.isArray(response.data.data)) {
                data = response.data.data;
            }
        }
        
        departments.value = data.map(dept => ({
            ...dept,
            nameDisplay: dept.name || "",
            managerNameDisplay: dept.managerName || "",
        }));
    } catch (err) {
        console.error("Error fetching departments:", err);
        departments.value = [];
        const errorMessage = err.response?.data?.message || err.message || "فشل في تحميل بيانات الأقسام.";
        error.value = errorMessage;
    } finally {
        loading.value = false;
    }
};

// جلب بيانات الموظفين (مدراء الأقسام والأطباء)
const fetchEmployees = async () => {
    loadingEmployees.value = true;
    employeesError.value = null;
    
    try {
        // جلب مدراء الأقسام والأطباء بشكل متوازي
        const [managersResponse, staffResponse] = await Promise.all([
            api.get('/admin-hospital/employees'), // مدراء الأقسام
            api.get('/admin-hospital/staff')     // جميع الموظفين (لجلب الأطباء)
        ]);
        
        // معالجة بيانات مدراء الأقسام
        let managersData = [];
        if (managersResponse.data) {
            if (Array.isArray(managersResponse.data)) {
                managersData = managersResponse.data;
            } else if (managersResponse.data.data && Array.isArray(managersResponse.data.data)) {
                managersData = managersResponse.data.data;
            } else if (managersResponse.data.success && Array.isArray(managersResponse.data.data)) {
                managersData = managersResponse.data.data;
            }
        }
        
        // معالجة بيانات الموظفين (لجلب الأطباء)
        let staffData = [];
        if (staffResponse.data) {
            if (Array.isArray(staffResponse.data)) {
                staffData = staffResponse.data;
            } else if (staffResponse.data.data && Array.isArray(staffResponse.data.data)) {
                staffData = staffResponse.data.data;
            } else if (staffResponse.data.success && Array.isArray(staffResponse.data.data)) {
                staffData = staffResponse.data.data;
            }
        }
        
        console.log('Raw staff data from API:', staffData);
        console.log('Sample staff item:', staffData[0]);
        
        // فلترة الأطباء فقط من قائمة الموظفين
        const doctorsData = staffData.filter(emp => {
            // التحقق من أن النوع هو 'doctor' أو 'طبيب'
            const role = emp.role || emp.type || '';
            const isDoctor = role === 'doctor' || role === 'طبيب';
            
            // تسجيل للتشخيص
            if (isDoctor) {
                console.log('Doctor found:', {
                    id: emp.id || emp.fileNumber,
                    name: emp.name || emp.fullName || emp.full_name,
                    role: role,
                    type: emp.type,
                    isActive: emp.isActive,
                    status: emp.status,
                    hasName: !!(emp.name || emp.fullName || emp.full_name),
                    hasEmail: !!emp.email
                });
            } else {
                // تسجيل الموظفين الآخرين للتشخيص
                console.log('Non-doctor employee:', {
                    id: emp.id || emp.fileNumber,
                    name: emp.name || emp.fullName || emp.full_name,
                    role: role,
                    type: emp.type
                });
            }
            
            return isDoctor;
        });
        
        console.log('Total staff data:', staffData.length);
        console.log('Doctors found:', doctorsData.length);
        console.log('Managers found:', managersData.length);
        
        // دمج مدراء الأقسام والأطباء
        const allEmployees = [...managersData, ...doctorsData];
        
        // إزالة التكرارات بناءً على id
        const uniqueEmployees = allEmployees.filter((emp, index, self) =>
            index === self.findIndex(e => (e.id || e.fileNumber) === (emp.id || emp.fileNumber))
        );
        
        console.log('Total unique employees after merge:', uniqueEmployees.length);
        
        // تحويل البيانات إلى الشكل المطلوب
        availableEmployees.value = uniqueEmployees.map(emp => ({
            id: emp.id || emp.fileNumber,
            fileNumber: emp.id || emp.fileNumber,
            name: emp.fullName || emp.name || emp.full_name,
            full_name: emp.fullName || emp.name || emp.full_name,
            nameDisplay: emp.fullName || emp.name || emp.full_name,
            isActive: emp.isActive !== undefined ? emp.isActive : (emp.status === 'active'),
        }));
        
        console.log('Final availableEmployees:', availableEmployees.value.length);
        console.log('Available employees details:', availableEmployees.value.map(emp => ({
            id: emp.id,
            name: emp.name,
            isActive: emp.isActive
        })));
    } catch (err) {
        console.error("Error fetching employees:", err);
        availableEmployees.value = [];
        const errorMessage = err.response?.data?.message || err.message || "فشل في تحميل بيانات الموظفين.";
        employeesError.value = errorMessage;
    } finally {
        loadingEmployees.value = false;
    }
};

// إعادة جلب جميع البيانات
const fetchAllData = async () => {
    await Promise.all([fetchDepartments(), fetchEmployees()]);
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

// التحقق مما إذا كان الموظف مديرًا لقسم
const isEmployeeManager = (employeeId) => {
    return departments.value.some(
        dept => dept.managerId === employeeId && dept.isActive
    );
};

// الحصول على قائمة الموظفين المتاحين لإدارة الأقسام
const availableManagers = computed(() => {
    const filtered = availableEmployees.value.filter(emp => {
        const isActive = emp.isActive;
        const isAlreadyManager = isEmployeeManager(emp.id || emp.fileNumber);
        const shouldInclude = isActive && !isAlreadyManager;
        
        // تسجيل للتشخيص
        if (!shouldInclude && (emp.name || emp.full_name)) {
            console.log('Employee excluded from availableManagers:', {
                id: emp.id,
                name: emp.name || emp.full_name,
                isActive: isActive,
                isAlreadyManager: isAlreadyManager,
                reason: !isActive ? 'غير نشط' : (isAlreadyManager ? 'مدير قسم آخر' : 'غير معروف')
            });
        }
        
        return shouldInclude;
    });
    
    console.log('Available managers count:', filtered.length);
    return filtered;
});

// ----------------------------------------------------
// 6. متغيرات نافذة تأكيد التفعيل/التعطيل
// ----------------------------------------------------
const isStatusConfirmationModalOpen = ref(false);
const departmentToToggle = ref(null);
const statusAction = ref("");

const openStatusConfirmationModal = (department) => {
    departmentToToggle.value = department;
    statusAction.value = department.isActive ? "تعطيل" : "تفعيل";
    isStatusConfirmationModalOpen.value = true;
};

const closeStatusConfirmationModal = () => {
    isStatusConfirmationModalOpen.value = false;
    departmentToToggle.value = null;
    statusAction.value = "";
};

const confirmStatusToggle = async () => {
    if (!departmentToToggle.value) return;

    const newStatus = !departmentToToggle.value.isActive;
    const departmentId = departmentToToggle.value.id;

    try {
        const response = await api.patch(
            `/admin-hospital/departments/${departmentId}/toggle-status`,
            { isActive: newStatus }
        );

        // تحديث البيانات محلياً
        const index = departments.value.findIndex(
            (d) => d.id === departmentId
        );
        if (index !== -1) {
            const responseData = response.data.data || response.data;
            departments.value[index].isActive = responseData.isActive !== undefined ? responseData.isActive : newStatus;
            departments.value[index].lastUpdated = responseData.lastUpdated || new Date().toISOString();
        }

        const successMessage = response.data?.message || ` تم ${statusAction.value} القسم ${departmentToToggle.value.name} بنجاح!`;
        showSuccessAlert(successMessage);
        closeStatusConfirmationModal();
    } catch (error) {
        console.error(`Error ${statusAction.value} department:`, error);
        const errorMessage = error.response?.data?.message || ` فشل ${statusAction.value} القسم.`;
        showSuccessAlert(errorMessage);
        closeStatusConfirmationModal();
    }
};

// ----------------------------------------------------
// 7. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("lastUpdated");
const sortOrder = ref("desc");

const sortDepartments = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredDepartments = computed(() => {
    let list = departments.value;

    // فلتر حسب الحالة
    if (statusFilter.value !== "all") {
        const isActiveFilter = statusFilter.value === "active";
        list = list.filter((department) => department.isActive === isActiveFilter);
    }

    // فلتر حسب البحث
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (department) =>
                department.id?.toString().includes(search) ||
               
                department.name?.toLowerCase().includes(search) ||
                department.managerName?.toLowerCase().includes(search)
               
        );
    }

    // الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "name") {
                comparison = (a.name || "").localeCompare(b.name || "", "ar");
            } else if (sortKey.value === "code") {
                comparison = (a.code || "").localeCompare(b.code || "", "ar");
            } else if (sortKey.value === "manager") {
                const managerA = a.managerName || "";
                const managerB = b.managerName || "";
                comparison = managerA.localeCompare(managerB, "ar");
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
// 8. نظام التنبيهات المطور (Toast System)
// ----------------------------------------------------
const isAlertVisible = ref(false);
const alertMessage = ref("");
const alertType = ref("success");
let alertTimeout = null;

const showAlert = (message, type = "success") => {
    if (alertTimeout) {
        clearTimeout(alertTimeout);
    }

    alertMessage.value = message;
    alertType.value = type;
    isAlertVisible.value = true;

    alertTimeout = setTimeout(() => {
        isAlertVisible.value = false;
    }, 4000);
};

const showSuccessAlert = (message) => showAlert(message, "success");
const showErrorAlert = (message) => showAlert(message, "error");
const showWarningAlert = (message) => showAlert(message, "warning");
const showInfoAlert = (message) => showAlert(message, "info");

// ----------------------------------------------------
// 9. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAddModalOpen = ref(false);
const selectedDepartment = ref({});

const openViewModal = async (department) => {
    try {
        // جلب البيانات المحدثة من API لضمان الحصول على أحدث البيانات
        const response = await api.get(`/admin-hospital/departments/${department.id}`);
        
        const responseData = response.data.data || response.data;
        selectedDepartment.value = {
            ...responseData,
            nameDisplay: responseData.name || "",
            managerNameDisplay: responseData.managerName || "",
        };
        isViewModalOpen.value = true;
    } catch (error) {
        console.error("Error fetching department details:", error);
        // في حالة الخطأ، نستخدم البيانات المحلية
        selectedDepartment.value = { ...department };
        isViewModalOpen.value = true;
    }
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedDepartment.value = {};
};

const openEditModal = (department) => {
    selectedDepartment.value = { ...department };
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    selectedDepartment.value = {};
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

// إضافة قسم جديد
const addDepartment = async (newDepartment) => {
    try {
        const departmentData = {
            name: newDepartment.name,
            managerId: newDepartment.managerId || null,
        };

        const response = await api.post('/admin-hospital/departments', departmentData);
        
        const responseData = response.data.data || response.data;
        departments.value.push({
            ...responseData,
            nameDisplay: responseData.name || "",
            managerNameDisplay: responseData.managerName || "",
        });
        
        closeAddModal();
        const successMessage = response.data?.message || " تم إنشاء القسم بنجاح!";
        showSuccessAlert(successMessage);
    } catch (error) {
        console.error("Error adding department:", error);
        const errorMessage = error.response?.data?.message || error.response?.data?.error || " فشل إنشاء القسم. تحقق من البيانات.";
        showSuccessAlert(errorMessage);
    }
};

// تحديث بيانات قسم
const updateDepartment = async (updatedDepartment) => {
    try {
        const departmentData = {
            name: updatedDepartment.name,
            managerId: updatedDepartment.managerId || null,
            isActive: updatedDepartment.isActive !== undefined ? updatedDepartment.isActive : true,
        };

        const response = await api.put(
            `/admin-hospital/departments/${updatedDepartment.id}`,
            departmentData
        );

        const responseData = response.data.data || response.data;
        const index = departments.value.findIndex(
            (d) => d.id === updatedDepartment.id
        );
        if (index !== -1) {
            departments.value[index] = {
                ...responseData,
                nameDisplay: responseData.name || "",
                managerNameDisplay: responseData.managerName || "",
            };
        }

        closeEditModal();
        const successMessage = response.data?.message || ` تم تعديل بيانات القسم ${updatedDepartment.name} بنجاح!`;
        showSuccessAlert(successMessage);
    } catch (error) {
        console.error("Error updating department:", error);
        const errorMessage = error.response?.data?.message || error.response?.data?.error || " فشل تعديل بيانات القسم.";
        showSuccessAlert(errorMessage);
    }
};

const getStatusTooltip = (isActive) => {
    return isActive ? "تعطيل القسم" : "تفعيل القسم";
};

// ----------------------------------------------------
// 11. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredDepartments.value.length;

    if (resultsCount === 0) {
        showSuccessAlert(" لا توجد بيانات للطباعة.");
        return;
    }

    const printWindow = window.open("", "_blank", "height=600,width=800");

    if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
        showSuccessAlert(
            " فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع."
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

        <h1>قائمة الأقسام (تقرير طباعة)</h1>
        
        <p class="results-info">
            عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>رقم القسم</th>
                    <th>اسم القسم</th>
                    <th>مدير القسم</th>
                    <th>حالة القسم</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredDepartments.value.forEach((department) => {
        tableHtml += `
            <tr>
                <td>${department.id || ''}</td>
                <td>${department.name || ''}</td>
                <td>${department.managerName || 'لا يوجد'}</td>
                <td class="${department.isActive ? "status-active" : "status-inactive"}">
                    ${department.isActive ? "مفعل" : "معطل"}
                </td>
            </tr>
        `;
    });

    tableHtml += `
            </tbody>
        </table>
    `;

    printWindow.document.write("<html><head><title>طباعة قائمة الأقسام</title>");
    printWindow.document.write("</head><body>");
    printWindow.document.write(tableHtml);
    printWindow.document.write("</body></html>");
    printWindow.document.close();

    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
        showSuccessAlert(" تم تجهيز التقرير بنجاح للطباعة.");
    };
};
</script>

<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <!-- حالة التحميل -->
           

            <!-- حالة الخطأ -->
           

            <!-- المحتوى الرئيسي -->
            <div >
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
                    <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <search v-model="searchTerm" />

                      

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
                                    حسب اسم القسم:
                                </li>
                                <li>
                                    <a
                                        @click="sortDepartments('name', 'asc')"
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
                                        @click="sortDepartments('name', 'desc')"
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
                                    حسب رمز القسم:
                                </li>
                                <li>
                                    <a
                                        @click="sortDepartments('code', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'code' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الرمز (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortDepartments('code', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'code' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الرمز (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب مدير القسم:
                                </li>
                                <li>
                                    <a
                                        @click="sortDepartments('manager', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'manager' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        المدير (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortDepartments('manager', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'manager' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        المدير (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب حالة القسم:
                                </li>
                                <li>
                                    <a
                                        @click="sortDepartments('status', 'asc')"
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
                                        @click="sortDepartments('status', 'desc')"
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
                                        @click="sortDepartments('lastUpdated', 'desc')"
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
                                        @click="sortDepartments('lastUpdated', 'asc')"
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
                                filteredDepartments.length
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
                                class="table w-full text-right min-w-[700px] border-collapse"
                            >
                                <thead
                                    class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                >
                                    <tr>
                                        <th class="id-col">رقم القسم</th>
                                        <th class="name-col">اسم القسم</th>
                                        <th class="manager-col">مدير القسم</th>
                                        <th class="status-col">الحالة</th>
                                        <th class="actions-col">الإجراءات</th>
                                    </tr>
                                </thead>

                                <tbody class="text-gray-800">
                                    <tr v-if="loading">
                                        <td colspan="5" class="p-4">
                                            <TableSkeleton :rows="5" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="error">
                                        <td colspan="5" class="py-12">
                                            <ErrorState :message="error" :retry="fetchDepartments" />
                                        </td>
                                    </tr>
                                    <template v-else>
                                        <tr
                                            v-for="(department, index) in filteredDepartments"
                                            :key="department.id || index"
                                            class="hover:bg-gray-100 border border-gray-300"
                                        >
                                            <td class="id-col">
                                                {{ department.id || 'N/A' }}
                                            </td>
                                            <td class="name-col">
                                                {{ department.name || 'N/A' }}
                                            </td>
                                            <td class="manager-col">
                                                {{ department.managerName || 'لا يوجد' }}
                                            </td>
                                            <td class="status-col">
                                                <span
                                                    :class="[
                                                        'px-2 py-1 rounded-full text-xs font-semibold',
                                                        department.isActive
                                                            ? 'bg-green-100 text-green-800 border border-green-200'
                                                            : 'bg-red-100 text-red-800 border border-red-200',
                                                    ]"
                                                >
                                                    {{
                                                        department.isActive
                                                            ? "مفعل"
                                                            : "معطل"
                                                    }}
                                                </span>
                                            </td>

                                            <td class="actions-col">
                                                <div class="flex gap-3 justify-center items-center">
                                                    <button
                                                        @click="openViewModal(department)"
                                                        class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        title="عرض البيانات"
                                                    >
                                                        <Icon
                                                            icon="tabler:eye-minus"
                                                            class="w-4 h-4 text-green-600"
                                                        />
                                                    </button>

                                                    <button
                                                        @click="openEditModal(department)"
                                                        class="p-2 rounded-lg bg-yellow-50 hover:bg-yellow-100 border border-yellow-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        title="تعديل البيانات"
                                                    >
                                                        <Icon
                                                            icon="line-md:pencil"
                                                            class="w-4 h-4 text-yellow-500"
                                                        />
                                                    </button>

                                                    <!-- زر تفعيل/تعطيل القسم -->
                                                    <button
                                                        @click="openStatusConfirmationModal(department)"
                                                        :class="[
                                                            'p-2 rounded-lg border transition-all duration-200 hover:scale-110 active:scale-95',
                                                            department.isActive
                                                                ? 'bg-red-50 hover:bg-red-100 border-red-200'
                                                                : 'bg-green-50 hover:bg-green-100 border-green-200',
                                                        ]"
                                                        :title="getStatusTooltip(department.isActive)"
                                                    >
                                                        <Icon
                                                            v-if="department.isActive"
                                                            icon="pepicons-pop:power-off"
                                                            class="w-4 h-4 text-red-600"
                                                        />
                                                        <Icon
                                                            v-else
                                                            icon="quill:off"
                                                            class="w-4 h-4 text-green-600"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr v-if="filteredDepartments.length === 0">
                                            <td colspan="5" class="py-12">
                                                <EmptyState message="لا توجد بيانات لعرضها" />
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
    <departmentAddModel
        :is-open="isAddModalOpen"
        :available-managers="availableManagers"
        @close="closeAddModal"
        @save="addDepartment"
    />

    <departmentEditModel
        :is-open="isEditModalOpen"
        :available-managers="availableManagers"
        :department="selectedDepartment"
        @close="closeEditModal"
        @save="updateDepartment"
    />

    <departmentViewModel
        :is-open="isViewModalOpen"
        :department="selectedDepartment"
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
                            ? "تفعيل القسم"
                            : "تعطيل القسم"
                    }}
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في {{ statusAction }} القسم
                    <strong>{{ departmentToToggle?.name }}</strong
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
        :show="isAlertVisible"
        :message="alertMessage"
        :type="alertType"
        @close="isAlertVisible = false"
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
.manager-col {
    width: 150px;
    min-width: 150px;
}
.name-col {
    width: 200px;
    min-width: 200px;
}
</style>