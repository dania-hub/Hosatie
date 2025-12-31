<script setup>
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";
import { Icon } from "@iconify/vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import employeeViewModel from "@/components/forhospitaladmin/employeeViewModel.vue";

// إعداد axios مع interceptor لإضافة التوكن
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
    (error) => Promise.reject(error)
);

// ----------------------------------------------------
// 1. إعدادات API
// ----------------------------------------------------
const API_URL = "/data-entry/employees";
const DEPARTMENTS_API_URL = "/departments";
const ROLES_API_URL = "/employee-roles";
const HOSPITALS_API_URL = "/hospitals";

// ----------------------------------------------------
// 2. البيانات الوهمية (نفس البيانات السابقة)
// ----------------------------------------------------
// const mockEmployees = [];

// const mockDepartments = [];

// const mockEmployeeRoles = [];

// const mockHospitals = [];

// ----------------------------------------------------
// 3. الحالة العامة للتطبيق
// ----------------------------------------------------
const employees = ref([]);
const availableHospitals = ref([]);
const employeeRoles = ref([]);
const availableDepartments = ref([]);
const filteredRoles = ref([]);

const loading = ref(true);
const loadingDepartments = ref(true);
const loadingRoles = ref(true);
const loadingHospitals = ref(true);
const error = ref(null);
const departmentsError = ref(null);
const rolesError = ref(null);
const hospitalsError = ref(null);

// ----------------------------------------------------
// 4. دوال مساعدة
// ----------------------------------------------------
const formatDateForDisplay = (dateString) => {
    if (!dateString) return "";
    
    const parts = dateString.split(/[/-]/);
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateString;
};

// معالجة بيانات الموظفين لتنسيقها للعرض
const processEmployeeData = (employeeData) => {
    return employeeData.map(emp => {
        // استخراج القيم من الهياكل المختلفة
        const hospitalName = emp.hospital?.name || emp.hospital || "غير محدد";
        const hospitalId = emp.hospital?.id || emp.hospitalId || null;
        const departmentName = emp.department?.name || emp.department || "غير محدد";
        const departmentId = emp.department?.id || emp.departmentId || null;
        const roleName = emp.role?.name || emp.role || "غير محدد";
        const roleId = emp.role?.id || emp.roleId || null;
        
        return {
            ...emp,
            nameDisplay: emp.name || "",
            nationalIdDisplay: emp.nationalId || "",
            birthDisplay: emp.birth ? formatDateForDisplay(emp.birth) : "",
            hospitalName,
            hospitalId,
            departmentName,
            departmentId,
            roleName,
            roleId
        };
    });
};

// ----------------------------------------------------
// 5. دوال جلب البيانات من API أو استخدام البيانات الوهمية
// ----------------------------------------------------
const fetchEmployees = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await api.get(API_URL);
        employees.value = processEmployeeData(response.data);
    } catch (err) {
        console.warn("استخدام بيانات وهمية للموظفين:", err.message);
        error.value = "فشل في جلب بيانات الموظفين. يرجى المحاولة مرة أخرى.";
        employees.value = processEmployeeData(mockEmployees);
    } finally {
        loading.value = false;
    }
};

const fetchDepartments = async () => {
    loadingDepartments.value = true;
    departmentsError.value = null;
    
    try {
        const response = await api.get(DEPARTMENTS_API_URL);
        availableDepartments.value = response.data;
    } catch (err) {
        console.warn("استخدام بيانات وهمية للأقسام:", err.message);
        availableDepartments.value = mockDepartments;
    } finally {
        loadingDepartments.value = false;
    }
};

const fetchEmployeeRoles = async () => {
    loadingRoles.value = true;
    rolesError.value = null;
    
    try {
        const response = await api.get(ROLES_API_URL);
        employeeRoles.value = response.data;
        filteredRoles.value = response.data;
    } catch (err) {
        console.warn("استخدام بيانات وهمية للأدوار:", err.message);
        employeeRoles.value = mockEmployeeRoles;
        filteredRoles.value = mockEmployeeRoles;
    } finally {
        loadingRoles.value = false;
    }
};

const fetchHospitals = async () => {
    loadingHospitals.value = true;
    hospitalsError.value = null;
    
    try {
        const response = await api.get(HOSPITALS_API_URL);
        availableHospitals.value = response.data;
    } catch (err) {
        console.warn("استخدام بيانات وهمية للمستشفيات:", err.message);
        availableHospitals.value = mockHospitals;
    } finally {
        loadingHospitals.value = false;
    }
};

// ----------------------------------------------------
// 6. الفلاتر والبحث
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("lastUpdated");
const sortOrder = ref("desc");
const statusFilter = ref("all");
const hospitalFilter = ref("all");
const roleFilter = ref("all");

const calculateAge = (birthDateString) => {
    if (!birthDateString) return 0;
    const parts = birthDateString.split(/[/-]/);
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

const sortEmployees = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredEmployees = computed(() => {
    let list = employees.value;

    // فلتر حسب الحالة
    if (statusFilter.value !== "all") {
        const isActiveFilter = statusFilter.value === "active";
        list = list.filter((employee) => employee.isActive === isActiveFilter);
    }

    // فلتر حسب المستشفى
    if (hospitalFilter.value !== "all") {
        list = list.filter((employee) => {
            return employee.hospitalId == hospitalFilter.value;
        });
    }

    // فلتر حسب الدور الوظيفي (بدون اشتراط اختيار المستشفى أولاً)
    if (roleFilter.value !== "all") {
        list = list.filter((employee) => {
            return employee.roleId == roleFilter.value || employee.roleName === roleFilter.value;
        });
    }

    // فلتر حسب البحث (يشمل جميع البيانات في الجدول)
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter((employee) => {
            // البحث في جميع الحقول المرئية
            return (
                employee.name?.toLowerCase().includes(search) ||
                employee.fileNumber?.toString().includes(search) ||
                employee.phone?.includes(search) ||
                employee.nationalId?.includes(search) ||
                employee.email?.toLowerCase().includes(search) ||
                employee.roleName?.toLowerCase().includes(search) ||
                employee.departmentName?.toLowerCase().includes(search) ||
                employee.hospitalName?.toLowerCase().includes(search)
            );
        });
    }

    // الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "name") {
                comparison = (a.name || "").localeCompare(b.name || "", "ar");
            } else if (sortKey.value === "birth") {
                const ageA = calculateAge(a.birth);
                const ageB = calculateAge(b.birth);
                comparison = ageA - ageB;
            } else if (sortKey.value === "lastUpdated") {
                const dateA = new Date(a.lastUpdated || 0);
                const dateB = new Date(b.lastUpdated || 0);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "role") {
                const roleA = a.roleName;
                const roleB = b.roleName;
                
                if (!roleA && !roleB) comparison = 0;
                else if (!roleA) comparison = 1;
                else if (!roleB) comparison = -1;
                else comparison = roleA.localeCompare(roleB, "ar");
            } else if (sortKey.value === "status") {
                if (a.isActive === b.isActive) comparison = 0;
                else if (a.isActive && !b.isActive) comparison = -1;
                else comparison = 1;
            } else if (sortKey.value === "department") {
                const deptA = a.departmentName;
                const deptB = b.departmentName;
                
                if (!deptA && !deptB) comparison = 0;
                else if (!deptA) comparison = 1;
                else if (!deptB) comparison = -1;
                else comparison = deptA.localeCompare(deptB, "ar");
            } else if (sortKey.value === "hospital") {
                const hospA = a.hospitalName;
                const hospB = b.hospitalName;
                
                if (!hospA && !hospB) comparison = 0;
                else if (!hospA) comparison = 1;
                else if (!hospB) comparison = -1;
                else comparison = hospA.localeCompare(hospB, "ar");
            }

            return sortOrder.value === "asc" ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 7. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredEmployees.value.length;

    if (resultsCount === 0) {
        showSuccessAlert("❌ لا توجد بيانات للطباعة.");
        return;
    }

    const printWindow = window.open("", "_blank", "height=600,width=900");

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
            .filters-info {
                text-align: right;
                margin-bottom: 10px;
                font-size: 14px;
                color: #666;
            }
        </style>

        <h1>قائمة الموظفين (تقرير طباعة)</h1>
        
        <div class="filters-info">
            <strong>المستشفى:</strong> ${hospitalFilter.value === 'all' ? 'الكل' : (availableHospitals.value.find(h => h.id == hospitalFilter.value)?.name || hospitalFilter.value)}<br>
            <strong>الدور الوظيفي:</strong> ${roleFilter.value === 'all' ? 'الكل' : (employeeRoles.value.find(r => r.id == roleFilter.value)?.name || roleFilter.value)}<br>
            <strong>حالة الحساب:</strong> ${statusFilter.value === 'all' ? 'الكل' : (statusFilter.value === 'active' ? 'مفعل فقط' : 'معطل فقط')}
        </div>
        
        <p class="results-info">
            عدد النتائج: ${resultsCount}
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>رقم الملف</th>
                    <th>الاسم الرباعي</th>
                    <th>الدور الوظيفي</th>
                    <th>القسم</th>
                    <th>المستشفى</th>
                    <th>حالة الحساب</th>
                    <th>الرقم الوطني</th>
                    <th>تاريخ الميلاد</th>
                    <th>رقم الهاتف</th>
                    <th>البريد الإلكتروني</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredEmployees.value.forEach((employee) => {
        tableHtml += `
            <tr>
                <td>${employee.fileNumber || ''}</td>
                <td>${employee.name || ''}</td>
                <td>${employee.roleName}</td>
                <td>${employee.departmentName}</td>
                <td>${employee.hospitalName}</td>
                <td class="${employee.isActive ? "status-active" : "status-inactive"}">
                    ${employee.isActive ? "مفعل" : "معطل"}
                </td>
                <td>${employee.nationalId || ''}</td>
                <td>${employee.birth || ''}</td>
                <td>${employee.phone || ''}</td>
                <td>${employee.email || ''}</td>
            </tr>
        `;
    });

    tableHtml += `
            </tbody>
        </table>
    `;

    printWindow.document.write("<html><head><title>طباعة قائمة الموظفين</title>");
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

// ----------------------------------------------------
// 8. رسالة النجاح
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
// 9. حالة عرض الموظف
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const selectedEmployee = ref({});

const openViewModal = (employee) => {
    selectedEmployee.value = {
        ...employee,
        nameDisplay: employee.name || "",
        nationalIdDisplay: employee.nationalId || "",
        birthDisplay: employee.birth ? formatDateForDisplay(employee.birth) : "",
        hospitalName: employee.hospitalName,
        departmentName: employee.departmentName,
        roleName: employee.roleName
    };
    isViewModalOpen.value = true;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedEmployee.value = {};
};

// ----------------------------------------------------
// 10. دالة إعادة تحميل البيانات
// ----------------------------------------------------
const fetchAllData = async () => {
    loading.value = true;
    loadingDepartments.value = true;
    loadingRoles.value = true;
    loadingHospitals.value = true;
    
    await Promise.all([
        fetchEmployees(),
        fetchDepartments(),
        fetchEmployeeRoles(),
        fetchHospitals()
    ]);
};

// ----------------------------------------------------
// 11. دالة إعادة تعيين الفلاتر
// ----------------------------------------------------
const resetFilters = () => {
    hospitalFilter.value = "all";
    roleFilter.value = "all";
    statusFilter.value = "all";
    searchTerm.value = "";
};

// ----------------------------------------------------
// 12. تحميل البيانات عند التهيئة
// ----------------------------------------------------
onMounted(async () => {
    await fetchAllData();
});
</script>

<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <!-- المحتوى الرئيسي -->
            <div>
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-2 sm:gap-0 ">
                    <div class="flex items-center gap-2 w-full sm:max-w-4xl flex-wrap">
                        <!-- شريط البحث -->
                      <div class="w-full sm:w-auto">
    <search v-model="searchTerm" placeholder="بحث في جميع البيانات..." />
</div>

<!-- مجموعة الفلاتر -->
<div class="flex items-center gap-3 flex-wrap">
    <!-- فلتر المستشفى (dropdown جديد) -->
    <div class="dropdown dropdown-start">
        <div
            tabindex="0"
            role="button"
            class="inline-flex items-center justify-between h-12 px-4 py-2 border-2 border-[#ffffff8d]  rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] min-w-[150px]"
        >
            <span>
                {{ hospitalFilter === 'all' ? 'جميع المستشفيات' : getHospitalName(hospitalFilter) }}
            </span>
            <Icon icon="lucide:chevron-down" class="w-4 h-4 mr-2" />
        </div>
        <ul
            tabindex="0"
            class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-52 text-right max-h-60 overflow-y-auto"
        >
            <li>
                <a
                    @click="hospitalFilter = 'all'"
                    :class="{'font-bold text-[#4DA1A9]': hospitalFilter === 'all'}"
                >
                    جميع المستشفيات
                </a>
            </li>
            <li v-for="hospital in availableHospitals" :key="hospital.id">
                <a
                    @click="hospitalFilter = hospital.id"
                    :class="{'font-bold text-[#4DA1A9]': hospitalFilter === hospital.id}"
                >
                    {{ hospital.name }}
                </a>
            </li>
        </ul>
    </div>

    <!-- فلتر الدور الوظيفي (dropdown جديد) -->
    <div class="dropdown dropdown-start">
        <div
            tabindex="0"
            role="button"
            class="inline-flex items-center justify-between h-12 px-4 py-2 border-2 border-[#ffffff8d] h-11 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] min-w-[110px] w-34"
        >
            <span>
                {{ roleFilter === 'all' ? 'جميع الأدوار' : getRoleName(roleFilter) }}
            </span>
            <Icon icon="lucide:chevron-down" class="w-4 h-4 mr-2" />
        </div>
        <ul
            tabindex="0"
            class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-50 text-right  overflow-y-auto"
        >
            <li>
                <a
                    @click="roleFilter = 'all'"
                    :class="{'font-bold text-[#4DA1A9]': roleFilter === 'all'}"
                >
                    جميع الأدوار
                </a>
            </li>
            <li v-for="role in employeeRoles" :key="role.id">
                <a
                    @click="roleFilter = role.id"
                    :class="{'font-bold text-[#4DA1A9]': roleFilter === role.id}"
                >
                    {{ role.name }}
                </a>
            </li>
        </ul>
    </div>

    <!-- زر إعادة تعيين الفلاتر (إن وجد) -->
</div>

<!-- فرز -->
<div class="dropdown dropdown-start">
    <div
        tabindex="0"
        role="button"
        class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-12 w-20 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
    >
        <Icon icon="lucide:arrow-down-up" class="w-5 h-5 ml-2" />
        فرز
    </div>
    <ul
        tabindex="0"
        class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-52 text-right"
    >
        <li class="menu-title text-gray-700 font-bold text-sm">حسب الاسم:</li>
        <li>
            <a
                @click="sortEmployees('name', 'asc')"
                :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'asc'}"
            >
                الاسم (أ - ي)
            </a>
        </li>
        <li>
            <a
                @click="sortEmployees('name', 'desc')"
                :class="{'font-bold text-[#4DA1A9]': sortKey === 'name' && sortOrder === 'desc'}"
            >
                الاسم (ي - أ)
            </a>
        </li>

        <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب الدور الوظيفي:</li>
        <li>
            <a
                @click="sortEmployees('role', 'asc')"
                :class="{'font-bold text-[#4DA1A9]': sortKey === 'role' && sortOrder === 'asc'}"
            >
                الدور الوظيفي (أ - ي)
            </a>
        </li>
        <li>
            <a
                @click="sortEmployees('role', 'desc')"
                :class="{'font-bold text-[#4DA1A9]': sortKey === 'role' && sortOrder === 'desc'}"
            >
                الدور الوظيفي (ي - أ)
            </a>
        </li>

        <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب المستشفى:</li>
        <li>
            <a
                @click="sortEmployees('hospital', 'asc')"
                :class="{'font-bold text-[#4DA1A9]': sortKey === 'hospital' && sortOrder === 'asc'}"
            >
                المستشفى (أ - ي)
            </a>
        </li>
        <li>
            <a
                @click="sortEmployees('hospital', 'desc')"
                :class="{'font-bold text-[#4DA1A9]': sortKey === 'hospital' && sortOrder === 'desc'}"
            >
                المستشفى (ي - أ)
            </a>
        </li>

        <li class="menu-title text-gray-700 font-bold text-sm mt-2">حسب حالة الحساب:</li>
        <li>
            <a
                @click="sortEmployees('status', 'asc')"
                :class="{'font-bold text-[#4DA1A9]': sortKey === 'status' && sortOrder === 'asc'}"
            >
                المعطلون أولاً
            </a>
        </li>
        <li>
            <a
                @click="sortEmployees('status', 'desc')"
                :class="{'font-bold text-[#4DA1A9]': sortKey === 'status' && sortOrder === 'desc'}"
            >
                المفعلون أولاً
            </a>
        </li>
    </ul>
</div>

<!-- عرض عدد النتائج -->
<div class="flex items-center gap-1">
    <p class="text-sm font-semibold text-gray-600">عدد النتائج:</p>
    <span class="text-[#4DA1A9] text-lg font-bold bg-gray-100 px-3 py-1 rounded-full">
        {{ filteredEmployees.length }}
    </span>
</div>
                   
 <div class="flex items-center justify-end w-full sm:w-auto">
    <btnprint @click="printTable" />
  </div>
               
                       
                               <!-- معلومات الفلاتر المطبقة -->
                <!-- <div v-if="hospitalFilter !== 'all' || roleFilter !== 'all' || statusFilter !== 'all'" 
                     class="bg-blue-50 border w-130 border-blue-100 rounded-xl p-3 mb-4">
                    <p class="text-sm text-blue-700 flex items-center gap-2">
                        <Icon icon="tabler:filter" class="w-4 h-4" />
                        <span class="font-medium">الفلاتر المطبقة:</span>
                        
                        <span v-if="hospitalFilter !== 'all'" class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                            مستشفى: {{ availableHospitals.find(h => h.id == hospitalFilter)?.name }}
                        </span>
                        
                        <span v-if="roleFilter !== 'all'" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                            دور: {{ employeeRoles.find(r => r.id == roleFilter)?.name }}
                        </span>
                                              
                    </p>
                </div> -->
                     </div>
               
                </div>
             

                <!-- الجدول الرئيسي -->
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
                                class="table w-full text-right min-w-[800px] border-collapse"
                            >
                                <thead
                                    class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                >
                                    <tr>
                                        <th class="file-number-col">رقم الملف</th>
                                        <th class="name-col">الإسم الرباعي</th>
                                        <th class="role-col">الدور الوظيفي</th>
                                        <th class="department-col">القسم</th>
                                        <th class="hospital-col">المستشفى</th>
                                        <th class="status-col">الحالة</th>
                                        <th class="phone-col">رقم الهاتف</th>
                                        <th class="actions-col">الإجراءات</th>
                                    </tr>
                                </thead>

                                <tbody class="text-gray-800">
                                    <tr v-if="loading || loadingDepartments || loadingRoles || loadingHospitals">
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
                                            v-for="(employee, index) in filteredEmployees"
                                            :key="employee.fileNumber || index"
                                            class="hover:bg-gray-100 border border-gray-300"
                                        >
                                            <td class="file-number-col">
                                                {{ employee.fileNumber || 'N/A' }}
                                            </td>
                                            <td class="name-col">
                                                {{ employee.name || 'N/A' }}
                                            </td>
                                            <td class="role-col">
                                                {{ employee.roleName }}
                                            </td>
                                            <td class="department-col">
                                                {{ employee.departmentName }}
                                            </td>
                                            <td class="hospital-col">
                                                {{ employee.hospitalName }}
                                            </td>
                                            <td class="status-col">
                                                <span
                                                    :class="[
                                                        'px-2 py-1 rounded-full text-xs font-semibold',
                                                        employee.isActive
                                                            ? 'bg-green-100 text-green-800 border border-green-200'
                                                            : 'bg-red-100 text-red-800 border border-red-200',
                                                    ]"
                                                >
                                                    {{
                                                        employee.isActive
                                                            ? "مفعل"
                                                            : "معطل"
                                                    }}
                                                </span>
                                            </td>
                                            <td class="phone-col">
                                                {{ employee.phone || 'N/A' }}
                                            </td>

                                            <td class="actions-col">
                                                <div class="flex gap-3 justify-center items-center">
                                                    <button
                                                        @click="openViewModal(employee)"
                                                        class="p-1 rounded-full hover:bg-green-100 transition-colors"
                                                        title="عرض البيانات"
                                                    >
                                                        <Icon
                                                            icon="tabler:eye-minus"
                                                            class="w-5 h-5 text-green-600"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr v-if="filteredEmployees.length === 0">
                                            <td colspan="8" class="py-12">
                                                <EmptyState message="لا توجد بيانات موظفين" />
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

    <!-- Modal عرض بيانات الموظف -->
    <employeeViewModel
        :is-open="isViewModalOpen"
        :patient="selectedEmployee"
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
    width: 80px;
    min-width: 80px;
    max-width: 80px;
    text-align: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
.file-number-col {
    width: 90px;
    min-width: 90px;
}
.status-col {
    width: 100px;
    min-width: 100px;
}
.role-col {
    width: 130px;
    min-width: 130px;
}
.department-col {
    width: 150px;
    min-width: 150px;
}
.hospital-col {
    width: 150px;
    min-width: 150px;
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