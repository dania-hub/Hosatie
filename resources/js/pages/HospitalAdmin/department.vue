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

// ----------------------------------------------------
// 1. إعدادات API
// ----------------------------------------------------
const API_URL = "/api/data-entry/departments";
const EMPLOYEES_API_URL = "/api/data-entry/employees";

// قائمة الموظفين (للحصول على مدراء الأقسام)
const availableEmployees = ref([]);

// فلتر الحالة
const statusFilter = ref("all");

// بيانات الأقسام
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
// ----------------------------------------------------
// 3. دوال جلب البيانات من API (معدلة لاستخدام بيانات وهمية)
// ----------------------------------------------------

// جلب بيانات الأقسام (بيانات وهمية)
const fetchDepartments = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        // محاكاة تأخير API
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // بيانات وهمية للأقسام
        const mockDepartments = [
            {
                id: 1,
                code: "DEPT-100001",
                name: "قسم الطوارئ",
                managerId: 1001,  // مرتبط بموظف fileNumber: 1001
                managerName: "أحمد محمد علي سالم",
                description: "قسم الطوارئ للحالات الطارئة",
                isActive: true,
                lastUpdated: "2024-10-01T10:00:00Z"
            },
            {
                id: 2,
                code: "DEPT-100002",
                name: "قسم الجراحة",
                managerId: 1002,  // مرتبط بموظف fileNumber: 1002
                managerName: "فاطمة حسن عبدالله",
                description: "قسم الجراحة للعمليات الجراحية",
                isActive: true,
                lastUpdated: "2024-09-28T14:30:00Z"
            },
            {
                id: 3,
                code: "DEPT-100003",
                name: "قسم الباطنية",
                managerId: null,  // لا مدير
                managerName: null,
                description: "قسم الباطنية للأمراض الداخلية",
                isActive: false,  // معطل
                lastUpdated: "2024-09-25T08:45:00Z"
            },
            {
                id: 4,
             
                name: "قسم الأطفال",
                managerId: 1003,  // مرتبط بموظف fileNumber: 1003
                managerName: "محمد خالد يوسف",
                description: "قسم الأطفال للرعاية الطبية للأطفال",
                isActive: true,
                lastUpdated: "2024-09-20T16:00:00Z"
            },
            {
                id: 5,
            
                name: "قسم النساء والتوليد",
                managerId: null,  // لا مدير
                managerName: null,
                description: "قسم النساء والتوليد للرعاية النسائية",
                isActive: true,
                lastUpdated: "2024-10-03T12:15:00Z"
            },
            {
                id: 6,
          
                name: "قسم الإدارة",
                managerId: 1004,  // مرتبط بموظف fileNumber: 1004
                managerName: "سارة أحمد محمود",
                description: "قسم الإدارة للشؤون الإدارية",
                isActive: false,  // معطل
                lastUpdated: "2024-09-15T09:00:00Z"
            },
            {
                id: 7,
            
                name: "قسم العناية المركزة",
                managerId: 1005,  // مرتبط بموظف fileNumber: 1005
                managerName: "علي حسن محمد",
                description: "قسم العناية المركزة للمرضى الحرجين",
                isActive: true,
                lastUpdated: "2024-08-10T11:20:00Z"
            },
            {
                id: 8,
                code: "DEPT-100008",
                name: "قسم الأشعة",
                managerId: null,  // لا مدير
                managerName: null,
                description: "قسم الأشعة للتصوير الطبي",
                isActive: true,
                lastUpdated: "2024-07-05T15:45:00Z"
            }
        ];
        
        departments.value = mockDepartments.map(dept => ({
            ...dept,
            nameDisplay: dept.name || "",
            managerNameDisplay: dept.managerName || "",
        }));
    } catch (err) {
        console.error("Error fetching departments:", err);
        departments.value = [];
        error.value = "فشل في تحميل بيانات الأقسام.";
    } finally {
        loading.value = false;
    }
};

// جلب بيانات الموظفين (بيانات وهمية)
const fetchEmployees = async () => {
    loadingEmployees.value = true;
    employeesError.value = null;
    
    try {
        // محاكاة تأخير API
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // بيانات وهمية للموظفين (متوافقة مع الأقسام)
        const mockEmployees = [
            {
                fileNumber: 1001,
                name: "أحمد محمد علي سالم",
                isActive: true,
                role: "مدير القسم"  // مدير قسم الطوارئ
            },
            {
                fileNumber: 1002,
                name: "فاطمة حسن عبدالله",
                isActive: true,
                role: "مدير القسم"  // مدير قسم الجراحة
            },
            {
                fileNumber: 1003,
                name: "محمد خالد يوسف",
                isActive: true,
                role: "مدير القسم"  // مدير قسم الأطفال
            },
            {
                fileNumber: 1004,
                name: "سارة أحمد محمود",
                isActive: true,
                role: "مدير القسم"  // مدير قسم الإدارة
            },
            {
                fileNumber: 1005,
                name: "علي حسن محمد",
                isActive: true,
                role: "مدير القسم"  // مدير قسم العناية المركزة
            },
            {
                fileNumber: 1006,
                name: "لينا عبدالرحمن",
                isActive: true,
                role: "طبيب"  // غير مدير قسم، متاح
            },
            {
                fileNumber: 1007,
                name: "كريم سالم أحمد",
                isActive: true,
                role: "ممرض"  // غير مدير قسم، متاح
            },
            {
                fileNumber: 1008,
                name: "نور محمد حسن",
                isActive: false,  // معطل، غير متاح
                role: "إداري"
            },
            {
                fileNumber: 1009,
                name: "يوسف علي سالم",
                isActive: true,
                role: "طبيب"  // غير مدير قسم، متاح
            },
            {
                fileNumber: 1010,
                name: "مريم خالد عبدالله",
                isActive: true,
                role: "ممرض"  // غير مدير قسم، متاح
            }
        ];
        
        availableEmployees.value = mockEmployees;
    } catch (err) {
        console.error("Error fetching employees:", err);
        availableEmployees.value = [];
        employeesError.value = "فشل في تحميل بيانات الموظفين.";
    } finally {
        loadingEmployees.value = false;
    }
};



// ----------------------------------------------------
// 4. تحميل البيانات عند التهيئة
// ----------------------------------------------------
onMounted(async () => {
    await Promise.all([
        fetchDepartments(), 
        fetchEmployees()
    ]);
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
    return availableEmployees.value.filter(emp => 
        emp.isActive && !isEmployeeManager(emp.fileNumber)
    );
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

    try {
        await axios.patch(
            `${API_URL}/${departmentToToggle.value.id}/status`,
            { isActive: newStatus }
        );

        const index = departments.value.findIndex(
            (d) => d.id === departmentToToggle.value.id
        );
        if (index !== -1) {
            departments.value[index].isActive = newStatus;
            departments.value[index].lastUpdated = new Date().toISOString();
        }

        showSuccessAlert(
            `✅ تم ${statusAction.value} القسم ${departmentToToggle.value.name} بنجاح!`
        );
        closeStatusConfirmationModal();
    } catch (error) {
        console.error(`Error ${statusAction.value} department:`, error);
        showSuccessAlert(`❌ فشل ${statusAction.value} القسم.`);
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
                department.code?.toString().includes(search) ||
                department.name?.toLowerCase().includes(search) ||
                department.managerName?.toLowerCase().includes(search) ||
                department.description?.toLowerCase().includes(search)
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
const selectedDepartment = ref({});

const openViewModal = (department) => {
    selectedDepartment.value = { ...department };
    isViewModalOpen.value = true;
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
            ...newDepartment,
            isActive: true,
            lastUpdated: new Date().toISOString(),
            code: `DEPT-${Date.now().toString().slice(-6)}`
        };

        const response = await axios.post(API_URL, departmentData);
        
        departments.value.push({
            ...response.data,
            nameDisplay: response.data.name || "",
            managerNameDisplay: response.data.managerName || "",
        });
        
        closeAddModal();
        showSuccessAlert("✅ تم إنشاء القسم بنجاح!");
    } catch (error) {
        console.error("Error adding department:", error);
        showSuccessAlert("❌ فشل إنشاء القسم. تحقق من البيانات.");
    }
};

// تحديث بيانات قسم
const updateDepartment = async (updatedDepartment) => {
    try {
        const response = await axios.put(
            `${API_URL}/${updatedDepartment.id}`,
            updatedDepartment
        );

        const index = departments.value.findIndex(
            (d) => d.id === updatedDepartment.id
        );
        if (index !== -1) {
            departments.value[index] = {
                ...response.data,
                nameDisplay: response.data.name || "",
                managerNameDisplay: response.data.managerName || "",
                lastUpdated: new Date().toISOString(),
            };
        }

        closeEditModal();
        showSuccessAlert(
            `✅ تم تعديل بيانات القسم ${updatedDepartment.name} بنجاح!`
        );
    } catch (error) {
        console.error("Error updating department:", error);
        showSuccessAlert("❌ فشل تعديل بيانات القسم.");
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
                    <th>الوصف</th>
                    <th>حالة القسم</th>
                    <th>تاريخ التحديث</th>
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
                <td>${department.description || '-'}</td>
                <td class="${department.isActive ? "status-active" : "status-inactive"}">
                    ${department.isActive ? "مفعل" : "معطل"}
                </td>
                <td>${new Date(department.lastUpdated).toLocaleDateString('ar-SA')}</td>
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
        showSuccessAlert("✅ تم تجهيز التقرير بنجاح للطباعة.");
    };
};
</script>

<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <!-- حالة التحميل -->
            <div v-if="loading || loadingEmployees" class="flex justify-center items-center h-64">
                <div class="text-center">
                    <Icon icon="eos-icons:loading" class="w-12 h-12 text-[#4DA1A9] animate-spin mx-auto mb-4" />
                    <p class="text-gray-600">جاري تحميل البيانات...</p>
                </div>
            </div>

            <!-- حالة الخطأ -->
            <div v-else-if="error || employeesError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <p v-if="error">{{ error }}</p>
                <p v-if="employeesError">{{ employeesError }}</p>
                <button @click="fetchAllData" class="mt-2 text-sm underline">
                    حاول مرة أخرى
                </button>
            </div>

            <!-- المحتوى الرئيسي -->
            <div v-else>
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

                                <tbody>
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
                                                    class="p-1 rounded-full hover:bg-green-100 transition-colors"
                                                    title="عرض البيانات"
                                                >
                                                    <Icon
                                                        icon="tabler:eye-minus"
                                                        class="w-5 h-5 text-green-600"
                                                    />
                                                </button>

                                                <button
                                                    @click="openEditModal(department)"
                                                    class="p-1 rounded-full hover:bg-yellow-100 transition-colors"
                                                    title="تعديل البيانات"
                                                >
                                                    <Icon
                                                        icon="line-md:pencil"
                                                        class="w-5 h-5 text-yellow-500"
                                                    />
                                                </button>

                                                <!-- زر تفعيل/تعطيل القسم -->
                                                <button
                                                    @click="openStatusConfirmationModal(department)"
                                                    :class="[
                                                        'p-1 rounded-full transition-colors',
                                                        department.isActive
                                                            ? 'hover:bg-red-100'
                                                            : 'hover:bg-green-100',
                                                    ]"
                                                    :title="getStatusTooltip(department.isActive)"
                                                >
                                                    <Icon
                                                        v-if="department.isActive"
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

                                    <tr v-if="filteredDepartments.length === 0">
                                        <td
                                            colspan="6"
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
.code-col {
    width: 120px;
    min-width: 120px;
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