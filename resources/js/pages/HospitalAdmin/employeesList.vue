<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { Icon } from "@iconify/vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import inputadd from "@/components/btnaddEmp.vue";
import btnprint from "@/components/btnprint.vue";
import employeeAddModel from "@/components/forhospitaladmin/employeeAddModel.vue";
import employeeEditModel from "@/components/forhospitaladmin/employeeEditModel.vue";
import employeeViewModel from "@/components/forhospitaladmin/employeeViewModel.vue";

// ----------------------------------------------------
// 1. إعدادات API
// ----------------------------------------------------
const API_URL = "/api/admin-hospital/staff";
const DEPARTMENTS_API_URL = "/api/admin-hospital/departments";
const ROLES_API_URL = "/api/employee-roles"; // رابط API للأدوار

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
        // البحث عن التوكن في كلا المفاتيح (auth_token و token) للتوافق
        const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        } else {
            console.warn('No token found in localStorage');
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
            const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
            console.error('Unauthenticated - Token exists:', !!token);
            if (token) {
                console.error('Token value (first 20 chars):', token.substring(0, 20) + '...');
            } else {
                console.error('No token found. Please login again.');
            }
        }
        return Promise.reject(error);
    }
);

// قائمة الأدوار المتاحة (سيتم جلبها من API)
const employeeRoles = ref([]);

// قائمة الأقسام المتاحة (سيتم جلبها من API)
const availableDepartments = ref([]);

// فلتر الدور الوظيفي
const roleFilter = ref("all");

// بيانات الموظفين
const employees = ref([]);

// ----------------------------------------------------
// 2. الحالة العامة للتطبيق
// ----------------------------------------------------
const loading = ref(true);
const loadingDepartments = ref(true);
const loadingRoles = ref(true);
const error = ref(null);
const departmentsError = ref(null);
const rolesError = ref(null);

// ----------------------------------------------------
// 3. دوال جلب البيانات من API
// ----------------------------------------------------

// جلب بيانات الموظفين
const fetchEmployees = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await api.get('/admin-hospital/staff');
        
        // التحقق من بنية الاستجابة
        let data = [];
        if (response.data) {
            if (Array.isArray(response.data)) {
                // إذا كانت البيانات مصفوفة مباشرة
                data = response.data;
            } else if (response.data.data && Array.isArray(response.data.data)) {
                // إذا كانت البيانات في response.data.data
                data = response.data.data;
            } else if (response.data.success && Array.isArray(response.data.data)) {
                // إذا كانت الاستجابة من sendSuccess
                data = response.data.data;
            }
        }
        
        employees.value = data.map(emp => ({
            ...emp,
            nameDisplay: emp.name || "",
            nationalIdDisplay: emp.nationalId || "",
            birthDisplay: emp.birth ? formatDateForDisplay(emp.birth) : "",
        }));
    } catch (err) {
        console.error("Error fetching employees:", err);
        const errorMessage = err.response?.data?.message || err.message || "فشل في تحميل بيانات الموظفين.";
        error.value = errorMessage;
        employees.value = [];
    } finally {
        loading.value = false;
    }
};

// جلب بيانات الأقسام
const fetchDepartments = async () => {
    loadingDepartments.value = true;
    departmentsError.value = null;
    
    try {
        const response = await api.get('/admin-hospital/departments');
        
        // التحقق من بنية الاستجابة
        let data = [];
        if (response.data) {
            if (Array.isArray(response.data)) {
                // إذا كانت البيانات مصفوفة مباشرة
                data = response.data;
            } else if (response.data.data && Array.isArray(response.data.data)) {
                // إذا كانت البيانات في response.data.data
                data = response.data.data;
            } else if (response.data.success && Array.isArray(response.data.data)) {
                // إذا كانت الاستجابة من sendSuccess
                data = response.data.data;
            }
        }
        
        availableDepartments.value = data.map(dept => ({
            id: dept.id,
            name: dept.name,
            isActive: dept.isActive !== undefined ? dept.isActive : true,
        }));
    } catch (err) {
        console.error("Error fetching departments:", err);
        const errorMessage = err.response?.data?.message || err.message || "فشل في تحميل بيانات الأقسام.";
        departmentsError.value = errorMessage;
        availableDepartments.value = [];
    } finally {
        loadingDepartments.value = false;
    }
};

// جلب بيانات الأدوار الوظيفية
const fetchEmployeeRoles = async () => {
    loadingRoles.value = true;
    rolesError.value = null;
    
    // استخدام قائمة الأدوار الافتراضية مباشرة
    employeeRoles.value = [
        'طبيب',
        'صيدلي',
        'مدير المخزن',
        'مدير القسم',
        'مدخل بيانات',
    ];
    
    loadingRoles.value = false;
};

// ----------------------------------------------------
// 4. دوال مساعدة
// ----------------------------------------------------
const formatDateForDisplay = (dateString) => {
    if (!dateString) return "";
    
    // تحويل من YYYY/MM/DD إلى DD/MM/YYYY
    const parts = dateString.split(/[/-]/);
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateString;
};

// تحويل الدور من العربية إلى الإنجليزية للـ API
const convertRoleToEnglish = (arabicRole) => {
    const roleMap = {
        'طبيب': 'doctor',
        'صيدلي': 'pharmacist',
        'مدير المخزن': 'warehouse_manager',
        'مدير القسم': 'department_head',
        'مدخل بيانات': 'data_entry',
    };
    
    // إذا كان الدور كائن، استخرج الاسم
    const roleName = typeof arabicRole === 'object' ? arabicRole.name : arabicRole;
    
    return roleMap[roleName] || roleName;
};

// إعادة جلب جميع البيانات
const fetchAllData = async () => {
    await Promise.all([
        fetchEmployees(), 
        fetchDepartments(), 
        fetchEmployeeRoles()
    ]);
};

// ----------------------------------------------------
// 5. تحميل البيانات عند التهيئة
// ----------------------------------------------------
onMounted(async () => {
    await fetchAllData();
});

// ----------------------------------------------------
// 6. دوال الحساب
// ----------------------------------------------------

// التحقق من وجود مدير مخزن واحد فقط
const hasWarehouseManager = computed(() => {
    // ابحث عن دور "مدير المخزن" في قائمة الأدوار
    const warehouseManagerRole = employeeRoles.value.find(role => 
        role.name === "مدير المخزن" || role === "مدير المخزن"
    );
    
    if (!warehouseManagerRole) return false;
    
    return employees.value.some(
        (employee) => {
            const roleName = typeof employee.role === 'object' ? employee.role.name : employee.role;
            return roleName === "مدير المخزن" && employee.isActive;
        }
    );
});

// تتبع الأقسام التي لها مدير مفعل
const departmentsWithManager = computed(() => {
    // ابحث عن دور "مدير القسم" في قائمة الأدوار
    const departmentManagerRole = employeeRoles.value.find(role => 
        role.name === "مدير القسم" || role === "مدير القسم"
    );
    
    if (!departmentManagerRole) return [];
    
    return employees.value
        .filter(emp => {
            const roleName = typeof emp.role === 'object' ? emp.role.name : emp.role;
            return roleName === "مدير القسم" && emp.isActive && emp.department;
        })
        .map(emp => emp.department);
});

// ----------------------------------------------------
// 7. متغيرات نافذة تأكيد التفعيل/التعطيل
// ----------------------------------------------------
const isStatusConfirmationModalOpen = ref(false);
const employeeToToggle = ref(null);
const statusAction = ref("");

const openStatusConfirmationModal = (employee) => {
    employeeToToggle.value = employee;
    statusAction.value = employee.isActive ? "تعطيل" : "تفعيل";
    isStatusConfirmationModalOpen.value = true;
};

const closeStatusConfirmationModal = () => {
    isStatusConfirmationModalOpen.value = false;
    employeeToToggle.value = null;
    statusAction.value = "";
};

const confirmStatusToggle = async () => {
    if (!employeeToToggle.value) return;

    const newStatus = !employeeToToggle.value.isActive;

    try {
        const response = await api.patch(
            `/admin-hospital/staff/${employeeToToggle.value.fileNumber}/status`,
            { isActive: newStatus }
        );

        // التحقق من بنية الاستجابة
        const responseData = response.data;
        let successMessage = `✅ تم ${statusAction.value} حساب الموظف ${employeeToToggle.value.name} بنجاح!`;
        
        if (responseData.message) {
            successMessage = "✅ " + responseData.message;
        }

        // إعادة تحميل قائمة الموظفين بعد التحديث
        await fetchEmployees();

        showSuccessAlert(successMessage);
        closeStatusConfirmationModal();
    } catch (error) {
        console.error(`Error ${statusAction.value} employee:`, error);
        console.error("Error response:", error.response?.data);
        
        let errorMessage = `❌ فشل ${statusAction.value} حساب الموظف.`;
        
        if (error.response?.data) {
            const errorData = error.response.data;
            if (errorData.message) {
                errorMessage = "❌ " + errorData.message;
            } else if (errorData.error) {
                errorMessage = "❌ " + errorData.error;
            }
        }
        
        showSuccessAlert(errorMessage);
        closeStatusConfirmationModal();
    }
};

// ----------------------------------------------------
// 8. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("lastUpdated");
const sortOrder = ref("desc");

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

// قائمة الأدوار الوظيفية المتاحة للفلترة
const availableRoles = computed(() => {
    const roles = new Set();
    employees.value.forEach(emp => {
        const roleName = typeof emp.role === 'object' ? emp.role.name : emp.role;
        if (roleName) {
            roles.add(roleName);
        }
    });
    return ['الكل', ...Array.from(roles).sort()];
});

const filteredEmployees = computed(() => {
    let list = employees.value;

    // فلتر حسب الدور الوظيفي
    if (roleFilter.value !== "all" && roleFilter.value !== "الكل") {
        list = list.filter((employee) => {
            const roleName = typeof employee.role === 'object' ? employee.role.name : employee.role;
            return roleName === roleFilter.value;
        });
    }

    // فلتر حسب البحث - تم التحديث للبحث في جميع الحقول
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter((employee) => {
            // إنشاء قائمة بجميع الحقول الممكنة للبحث
            const searchFields = [
                // الحقول الأساسية
                employee.fileNumber?.toString(),
                employee.name,
                employee.nationalId,
                employee.phone,
                employee.email,
                
                // الدور الوظيفي (إذا كان كائن)
                employee.role ? 
                    (typeof employee.role === 'object' ? employee.role.name : employee.role) : 
                    '',
                
                // القسم
                employee.department,
                
                // تاريخ الميلاد (بصيغ مختلفة)
                employee.birth,
                formatDateForDisplay(employee.birth),
                
                // الحالة (نشط/غير نشط)
                employee.isActive ? "مفعل" : "معطل",
                employee.isActive ? "نشط" : "غير نشط",
                employee.isActive ? "active" : "inactive",
                
                // معلومات إضافية قد تكون في بيانات الموظف
                employee.full_name,
                employee.national_id,
                employee.birth_date,
                employee.role_name,
                employee.department_name,
                
                // تحويل جميع الحقول الرقمية إلى نص للبحث
                employee.age?.toString(),
                employee.salary?.toString(),
                employee.id?.toString(),
                employee.user_id?.toString(),
            ];

            // البحث في جميع الحقول
            return searchFields.some(field => 
                field && field.toString().toLowerCase().includes(search)
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
                const roleA = typeof a.role === 'object' ? a.role.name : a.role;
                const roleB = typeof b.role === 'object' ? b.role.name : b.role;
                
                if (!roleA && !roleB) comparison = 0;
                else if (!roleA) comparison = 1;
                else if (!roleB) comparison = -1;
                else comparison = roleA.localeCompare(roleB, "ar");
            } else if (sortKey.value === "status") {
                if (a.isActive === b.isActive) comparison = 0;
                else if (a.isActive && !b.isActive) comparison = -1;
                else comparison = 1;
            } else if (sortKey.value === "department") {
                if (!a.department && !b.department) comparison = 0;
                else if (!a.department) comparison = 1;
                else if (!b.department) comparison = -1;
                else comparison = a.department.localeCompare(b.department, "ar");
            }

            return sortOrder.value === "asc" ? comparison : -comparison;
        });
    }

    return list;
});
// ----------------------------------------------------
// 9. نظام التنبيهات المطور (Toast System)
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
// 10. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAddModalOpen = ref(false);
const selectedEmployee = ref({});

const openViewModal = (employee) => {
    selectedEmployee.value = {
        ...employee,
        nameDisplay: employee.name || "",
        nationalIdDisplay: employee.nationalId || "",
        birthDisplay: employee.birth ? formatDateForDisplay(employee.birth) : "",
    };
    isViewModalOpen.value = true;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedEmployee.value = {};
};

const openEditModal = (employee) => {
    selectedEmployee.value = {
        ...employee,
        nameDisplay: employee.name || "",
        nationalIdDisplay: employee.nationalId || "",
        birthDisplay: employee.birth ? formatDateForDisplay(employee.birth) : "",
    };
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    selectedEmployee.value = {};
};

const openAddModal = () => {
    isAddModalOpen.value = true;
};

const closeAddModal = () => {
    isAddModalOpen.value = false;
};

// ----------------------------------------------------
// 11. دوال إدارة البيانات
// ----------------------------------------------------

// إضافة موظف جديد
const addEmployee = async (newEmployee) => {
    try {
        // التحقق من وجود مدير مخزن إذا كان الدور الجديد هو "مدير المخزن"
        const warehouseManagerRole = employeeRoles.value.find(role => 
            role.name === "مدير المخزن" || role === "مدير المخزن"
        );
        
        if (warehouseManagerRole && newEmployee.role === "مدير المخزن" && hasWarehouseManager.value) {
            showSuccessAlert("❌ يوجد بالفعل مدير مخزن مفعل في النظام!");
            return;
        }

        // التحقق من وجود مدير قسم إذا كان الدور الجديد هو "مدير القسم"
        const departmentManagerRole = employeeRoles.value.find(role => 
            role.name === "مدير القسم" || role === "مدير القسم"
        );
        
        if (departmentManagerRole && newEmployee.role === "مدير القسم") {
            const existingManager = employees.value.find(
                emp => {
                    const roleName = typeof emp.role === 'object' ? emp.role.name : emp.role;
                    return roleName === "مدير القسم" && 
                           emp.department === newEmployee.department && 
                           emp.isActive;
                }
            );
            
            if (existingManager) {
                showSuccessAlert(`❌ القسم "${newEmployee.department}" لديه بالفعل مدير مفعل!`);
                return;
            }
        }

        // تحويل البيانات إلى الصيغة المتوقعة من API
        // تحويل تاريخ الميلاد من YYYY/MM/DD إلى YYYY-MM-DD إذا كان موجوداً
        let birthDate = null;
        if (newEmployee.birth) {
            // إذا كان التاريخ بصيغة YYYY/MM/DD، نحوله إلى YYYY-MM-DD
            birthDate = newEmployee.birth.replace(/\//g, '-');
            // التأكد من الصيغة الصحيحة YYYY-MM-DD
            if (!/^\d{4}-\d{2}-\d{2}$/.test(birthDate)) {
                console.warn('Invalid birth date format:', newEmployee.birth);
                birthDate = null;
            }
        }
        
        const employeeData = {
            full_name: newEmployee.name,
            email: newEmployee.email,
            role: convertRoleToEnglish(newEmployee.role), // تحويل الدور إلى الإنجليزية
            phone: newEmployee.phone || null,
            national_id: newEmployee.nationalId || null,
            birth_date: birthDate,
        };

        // تسجيل البيانات المرسلة للتشخيص
        console.log("Sending employee data:", employeeData);
        
        const response = await api.post('/admin-hospital/staff', employeeData);
        
        // التحقق من بنية الاستجابة
        const responseData = response.data.data || response.data;
        let successMessage = "✅ تم تسجيل بيانات الموظف بنجاح!";
        
        if (responseData.message) {
            successMessage = "✅ " + responseData.message;
        }
        
        // إعادة تحميل قائمة الموظفين بعد الإضافة
        await fetchEmployees();
        
        closeAddModal();
        showSuccessAlert(successMessage);
    } catch (error) {
        console.error("Error adding employee:", error);
        console.error("Error response:", error.response?.data);
        console.error("Error status:", error.response?.status);
        console.error("Error headers:", error.response?.headers);
        
        let errorMessage = "❌ فشل تسجيل الموظف.";
        
        if (error.response?.data) {
            const errorData = error.response.data;
            
            // معالجة أخطاء التحقق من Laravel
            if (errorData.errors) {
                const validationErrors = Object.values(errorData.errors).flat();
                errorMessage = "❌ " + validationErrors.join(', ');
            } else if (errorData.message) {
                errorMessage = "❌ " + errorData.message;
            } else if (errorData.error) {
                errorMessage = "❌ " + errorData.error;
            }
        } else if (error.message) {
            errorMessage = "❌ " + error.message;
        }
        
        // إضافة تفاصيل إضافية للخطأ
        if (error.response?.status === 422) {
            errorMessage += " (خطأ في التحقق من البيانات)";
        } else if (error.response?.status === 400) {
            errorMessage += " (طلب غير صحيح)";
        } else if (error.response?.status === 404) {
            errorMessage += " (المورد غير موجود)";
        } else if (error.response?.status === 500) {
            errorMessage += " (خطأ في الخادم)";
        }
        
        showSuccessAlert(errorMessage);
    }
};

// تحديث بيانات موظف
const updateEmployee = async (updatedEmployee) => {
    try {
        // التحقق من وجود مدير مخزن إذا كان الدور الجديد هو "مدير المخزن"
        const warehouseManagerRole = employeeRoles.value.find(role => 
            role.name === "مدير المخزن" || role === "مدير المخزن"
        );
        
        // استخدام id إذا كان fileNumber غير موجود
        const employeeId = updatedEmployee.fileNumber || updatedEmployee.id;
        
        if (warehouseManagerRole && updatedEmployee.role === "مدير المخزن" && hasWarehouseManager.value) {
            const currentEmployee = employees.value.find(
                (p) => (p.fileNumber || p.id) === employeeId
            );
            
            const currentRoleName = currentEmployee ? 
                (typeof currentEmployee.role === 'object' ? currentEmployee.role.name : currentEmployee.role) : 
                '';
            
            if (!currentEmployee || currentRoleName !== "مدير المخزن") {
                showSuccessAlert("❌ يوجد بالفعل مدير مخزن مفعل في النظام!");
                return;
            }
        }

        // التحقق من وجود مدير قسم إذا كان الدور الجديد هو "مدير القسم"
        const departmentManagerRole = employeeRoles.value.find(role => 
            role.name === "مدير القسم" || role === "مدير القسم"
        );
        
        if (departmentManagerRole && updatedEmployee.role === "مدير القسم") {
            const currentEmployee = employees.value.find(
                emp => (emp.fileNumber || emp.id) === employeeId
            );
            
            const existingManager = employees.value.find(
                emp => {
                    const roleName = typeof emp.role === 'object' ? emp.role.name : emp.role;
                    const empId = emp.fileNumber || emp.id;
                    return roleName === "مدير القسم" && 
                           emp.department === updatedEmployee.department && 
                           emp.isActive &&
                           empId !== employeeId;
                }
            );
            
            if (existingManager) {
                showSuccessAlert(`❌ القسم "${updatedEmployee.department}" لديه بالفعل مدير مفعل!`);
                return;
            }
        }

        // تحويل البيانات إلى الصيغة المتوقعة من API
        const employeeData = {
            full_name: updatedEmployee.name,
            email: updatedEmployee.email,
            role: convertRoleToEnglish(updatedEmployee.role), // تحويل الدور إلى الإنجليزية
            phone: updatedEmployee.phone || null,
            national_id: updatedEmployee.nationalId || null,
            birth_date: updatedEmployee.birth || null,
        };

        if (!employeeId) {
            showSuccessAlert("❌ فشل تعديل بيانات الموظف: رقم الموظف غير محدد.");
            return;
        }
        
        // ملاحظة: قد تحتاج إلى إضافة route للتحديث في API
        const response = await api.put(
            `/admin-hospital/staff/${employeeId}`,
            employeeData
        );

        // إعادة تحميل قائمة الموظفين بعد التحديث
        await fetchEmployees();

        closeEditModal();
        const employeeName = updatedEmployee.name || 'الموظف';
        showSuccessAlert(
            `✅ تم تعديل بيانات الموظف ${employeeName} بنجاح!`
        );
    } catch (error) {
        console.error("Error updating employee:", error);
        const errorMessage = error.response?.data?.message || "❌ فشل تعديل بيانات الموظف.";
        showSuccessAlert(errorMessage);
    }
};

const getStatusTooltip = (isActive) => {
    return isActive ? "تعطيل الحساب" : "تفعيل الحساب";
};

// ----------------------------------------------------
// 12. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredEmployees.value.length;

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

        <h1>قائمة الموظفين (تقرير طباعة)</h1>
        
        <p class="results-info">
            عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>رقم الملف</th>
                    <th>الاسم الرباعي</th>
                    <th>الدور الوظيفي</th>
                    <th>القسم</th>
                    <th>حالة الحساب</th>
                    <th>الرقم الوطني</th>
                    <th>تاريخ الميلاد</th>
                    <th>رقم الهاتف</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredEmployees.value.forEach((employee) => {
        const roleName = typeof employee.role === 'object' ? employee.role.name : employee.role;
        
        tableHtml += `
            <tr>
                <td>${employee.fileNumber || ''}</td>
                <td>${employee.name || ''}</td>
                <td>${roleName || ''}</td>
                <td>${employee.department || '-'}</td>
                <td class="${employee.isActive ? "status-active" : "status-inactive"}">
                    ${employee.isActive ? "مفعل" : "معطل"}
                </td>
                <td>${employee.nationalId || ''}</td>
                <td>${employee.birth || ''}</td>
                <td>${employee.phone || ''}</td>
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
</script>

<template>
    <DefaultLayout>
        <main class="flex-1 p-4 sm:p-5 pt-3">
            <!-- حالة التحميل -->
           

            <!-- حالة الخطأ -->
           

            <!-- المحتوى الرئيسي -->
            <div>
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
                    <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <search v-model="searchTerm" />

                        <!-- فلتر الدور الوظيفي -->
                        <div class="dropdown dropdown-start">
                            <div
                                tabindex="0"
                                role="button"
                                class="inline-flex items-center justify-between h-11 px-4 py-2 border-2 border-[#ffffff8d] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] min-w-[150px]"
                            >
                                <span>
                                    {{ roleFilter === 'all' || roleFilter === 'الكل' ? 'جميع الأدوار' : roleFilter }}
                                </span>
                                <Icon icon="lucide:chevron-down" class="w-4 h-4 mr-2" />
                            </div>
                            <ul
                                tabindex="0"
                                class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] rounded-[35px] w-52 text-right"
                            >
                                <li class="menu-title text-gray-700 font-bold text-sm">حسب الدور الوظيفي:</li>
                                <li v-for="role in availableRoles" :key="role">
                                    <a
                                        @click="roleFilter = role === 'الكل' ? 'all' : role"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                (roleFilter === role) || (role === 'الكل' && roleFilter === 'all'),
                                        }"
                                    >
                                        {{ role }}
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
                                    حسب الاسم:
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('name', 'asc')"
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
                                        @click="sortEmployees('name', 'desc')"
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
                                    حسب الدور الوظيفي:
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('role', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'role' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الدور الوظيفي (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('role', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'role' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الدور الوظيفي (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب القسم:
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('department', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'department' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        القسم (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('department', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'department' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        القسم (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب حالة الحساب:
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('status', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'status' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        المعطلون أولاً
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('status', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'status' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        المفعلون أولاً
                                    </a>
                                </li>
                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب العمر:
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('birth', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'birth' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الأصغر سناً أولاً
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('birth', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'birth' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الأكبر سناً أولاً
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب آخر تحديث:
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('lastUpdated', 'desc')"
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
                                        @click="sortEmployees('lastUpdated', 'asc')"
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
                                filteredEmployees.length
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
                                        <th class="file-number-col">رقم الملف</th>
                                        <th class="name-col">الإسم الرباعي</th>
                                        <th class="role-col">الدور الوظيفي</th>
                                        <th class="department-col">القسم</th>
                                        <th class="status-col">الحالة</th>
                                        <th class="phone-col">رقم الهاتف</th>
                                        <th class="actions-col">الإجراءات</th>
                                    </tr>
                                </thead>

                                    <tbody class="text-gray-800">
                                        <tr v-if="loading">
                                            <td colspan="7" class="p-4">
                                                <TableSkeleton :rows="5" />
                                            </td>
                                        </tr>
                                        <tr v-else-if="error">
                                            <td colspan="7" class="py-12">
                                                <ErrorState :message="error" :retry="fetchEmployees" />
                                            </td>
                                        </tr>
                                        <template v-else>
                                            <tr
                                                v-for="employee in filteredEmployees"
                                                :key="employee.fileNumber"
                                                class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                            >
                                                <td class="font-semibold text-gray-700">
                                                    {{ employee.fileNumber }}
                                                </td>
                                                <td>
                                                    {{ employee.nameDisplay }}
                                                </td>
                                                <td>
                                                    {{ employee.role && typeof employee.role === 'object' ? employee.role.name : employee.role }}
                                                </td>
                                                <td>
                                                    {{ employee.department || "-" }}
                                                </td>
                                                <td class="status-col">
                                                    <span
                                                        :class="[
                                                            'status-badge px-3 py-1 rounded-full text-xs font-bold inline-block border',
                                                            employee.isActive
                                                                ? 'bg-green-100/70 text-green-700 border-green-700/60'
                                                                : 'bg-red-100/70 text-red-700 border-red-700/60',
                                                        ]"
                                                    >
                                                        {{ employee.isActive ? "مفعل" : "معطل" }}
                                                    </span>
                                                </td>
                                                <td>
                                                    {{ employee.phone || "-" }}
                                                </td>
                                                <td class="actions-col">
                                                    <div class="flex gap-3 justify-center items-center">
                                                        <button
                                                            @click="openViewModal(employee)"
                                                             class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                            title="عرض البيانات"
                                                        >
                                                            <Icon
                                                            icon="tabler:eye-minus"
                                                            class="w-4 h-4 text-green-600"   />
                                                        </button>
                                                        <button
                                                          
                                                            @click="openEditModal(employee)"
                                                              class="p-2 rounded-lg bg-yellow-50 hover:bg-yellow-100 border border-yellow-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                            title="تعديل البيانات"
                                                        >
                                                            <Icon
                                                            icon="line-md:pencil"
                                                            class="w-4 h-4 text-yellow-500"    />
                                                        </button>
                                                        <button
                                                            @click="openStatusConfirmationModal(employee)"
                                                            :class="[
                                                                'p-2 rounded-lg border transition-all duration-200 hover:scale-110 active:scale-95',
                                                                employee.isActive
                                                                    ? 'bg-red-50 hover:bg-red-100 border-red-200'
                                                                    : 'bg-green-50 hover:bg-green-100 border-green-200',
                                                            ]"
                                                            :title="getStatusTooltip(employee.isActive)"
                                                           
 
                                                        >
                                                            <Icon
                                                            v-if="employee.isActive"
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
                                            <tr v-if="filteredEmployees.length === 0">
                                                <td colspan="7" class="py-12">
                                                    <EmptyState message="لا يوجد موظفين لعرضهم" />
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
    <employeeAddModel
        :is-open="isAddModalOpen"
        :has-warehouse-manager="hasWarehouseManager"
        :available-departments="availableDepartments"
        :available-roles="employeeRoles"
        :departments-with-manager="departmentsWithManager"
        @close="closeAddModal"
        @save="addEmployee"
    />

    <employeeEditModel
        :is-open="isEditModalOpen"
        :has-warehouse-manager="hasWarehouseManager"
        :available-departments="availableDepartments"
        :available-roles="employeeRoles"
        :departments-with-manager="departmentsWithManager"
        :patient="selectedEmployee"
        @close="closeEditModal"
        @save="updateEmployee"
    />

    <employeeViewModel
        :is-open="isViewModalOpen"
        :patient="selectedEmployee"
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
                            ? "تفعيل حساب الموظف"
                            : "تعطيل حساب الموظف"
                    }}
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في {{ statusAction }} حساب الموظف
                    <strong>{{ employeeToToggle?.name }}</strong
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
.national-id-col {
    width: 130px;
    min-width: 130px;
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