<script setup>
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { Icon } from "@iconify/vue";
import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import inputadd from "@/components/btnaddEmp.vue";
import btnprint from "@/components/btnprint.vue";
import employeeAddModel from "@/components/forsuperadmin/employeeAddModel.vue";
import employeeEditModel from "@/components/forsuperadmin/employeeEditModel.vue";
import employeeViewModel from "@/components/forsuperadmin/employeeViewModel.vue";
import Toast from "@/components/Shared/Toast.vue";

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
        } else {
            console.warn('No authentication token found');
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// إضافة interceptor للتعامل مع الأخطاء
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            console.error('Unauthenticated - Please login again.');
        }
        return Promise.reject(error);
    }
);

// ----------------------------------------------------
// 1. إعدادات API
// ----------------------------------------------------
const API_URL = "super-admin/users";
const HOSPITALS_API_URL = "super-admin/hospitals";

// قائمة الأدوار المتاحة (ثابتة لأن API يعيد type)
const employeeRoles = ref([
    { name: "مدير نظام المستشفى", value: "hospital_admin" },
    { name: "مدير المورد", value: "supplier_admin" }
]);

// قائمة المستشفيات المتاحة 
const availableHospitals = ref([]);
const availableSuppliers = ref([]);

// فلتر الحالة
const statusFilter = ref("all");

// فلتر الدور الوظيفي
const roleFilter = ref("all");

// بيانات الموظفين
const employees = ref([]);



// ----------------------------------------------------
// 2. الحالة العامة للتطبيق
// ----------------------------------------------------
const loading = ref(true);
const loadingHospitals = ref(true);
const error = ref(null);
const hospitalsError = ref(null);

// ----------------------------------------------------
// 3. دوال جلب البيانات من API
// ----------------------------------------------------

// جلب بيانات الموظفين
const fetchEmployees = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await api.get(API_URL, {
            params: {
                types: 'hospital_admin,supplier_admin'
            }
        });
        
        // معالجة البيانات المرجعة من API
        // الـ API يعيد البيانات في هيكل: { success: true, data: [...], message: "..." }
        const data = response.data?.data || [];
        
        if (!Array.isArray(data)) {
            console.error("Expected array but got:", typeof data, data);
            employees.value = [];
            error.value = "تنسيق البيانات غير صحيح";
            return;
        }
        
        // تحويل البيانات من API إلى التنسيق المستخدم في الصفحة
        employees.value = data.map(emp => {
            const hospitalId = emp.hospital ? emp.hospital.id : null;
            const supplierId = emp.supplier ? emp.supplier.id : null;
            
            // تحديد الحالة الفعلية: يجب أن يكون status = 'active' ومربوط بمستشفى/مورد
            let actualStatus = emp.status || 'inactive';
            let actualIsActive = emp.status === 'active';
            
            // إذا كان مدير مستشفى وغير مربوط بمستشفى، يعتبر معطلاً
            if (emp.type === 'hospital_admin' && !hospitalId) {
                actualStatus = 'inactive';
                actualIsActive = false;
            }
            
            // إذا كان مدير مورد وغير مربوط بمورد، يعتبر معطلاً
            if (emp.type === 'supplier_admin' && !supplierId) {
                actualStatus = 'inactive';
                actualIsActive = false;
            }
            
            return {
                id: emp.id,
                fileNumber: emp.id, // استخدام id كـ fileNumber للتوافق
                name: emp.fullName || "",
                fullName: emp.fullName || "",
                email: emp.email || "",
                phone: emp.phone || "",
                nationalId: emp.nationalId || "",
                birthDate: emp.birthDate || "",
                role: emp.typeArabic || emp.type || "",
                type: emp.type || "",
                typeArabic: emp.typeArabic || "",
                hospital: emp.hospital ? emp.hospital.name : "",
                hospitalId: hospitalId,
                supplier: emp.supplier ? emp.supplier.name : "",
                supplierId: supplierId,
                isActive: actualIsActive,
                status: actualStatus,
                statusArabic: actualStatus === 'active' ? 'نشط' : 'معطل',
                nameDisplay: emp.fullName || "",
                nationalIdDisplay: emp.nationalId || "",
                birthDisplay: emp.birthDate ? formatDateForDisplay(emp.birthDate) : "",
                lastUpdated: emp.updatedAt || emp.createdAt || new Date().toISOString(),
            };
        });
        
    } catch (err) {
        console.error("Error fetching employees:", err);
        console.error("Error response:", err.response);
        
        // معالجة الأخطاء المختلفة
        if (err.response?.status === 401) {
            error.value = "انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى";
            showSuccessAlert("⚠️ انتهت جلسة العمل، يرجى تسجيل الدخول مجدداً.");
        } else if (err.response?.status === 403) {
            error.value = "ليس لديك الصلاحية للوصول إلى هذه البيانات";
            showSuccessAlert("⛔ عذراً، ليس لديك صلاحية الوصول لهذه البيانات.");
        } else {
            error.value = err.response?.data?.message || err.message || "حدث خطأ أثناء جلب البيانات";
            showSuccessAlert(`❌ ${error.value}`);
        }
        
        employees.value = [];
    } finally {
        loading.value = false;
    }
};

// جلب بيانات المستشفيات
const fetchHospitals = async () => {
    loadingHospitals.value = true;
    hospitalsError.value = null;
    try {
        const response = await api.get(HOSPITALS_API_URL);
        availableHospitals.value = response.data.data || response.data || [];
        
        // Fetch suppliers as well
        const suppliersResponse = await api.get('super-admin/suppliers');
        availableSuppliers.value = suppliersResponse.data.data || suppliersResponse.data || [];
        
    } catch (err) {
        console.error("Error fetching hospitals/suppliers:", err);
        hospitalsError.value = err.response?.data?.message || "حدث خطأ أثناء جلب المستشفيات والموردين";
        availableHospitals.value = [];
        availableSuppliers.value = [];
    } finally {
        loadingHospitals.value = false;
    }
};

// ----------------------------------------------------
// 4. دالة مساعدة لتنسيق التاريخ
// ----------------------------------------------------
const formatDateForDisplay = (dateString) => {
    if (!dateString) return "";
    
    const parts = dateString.split(/[/-]/);
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateString;
};

// ----------------------------------------------------
// 5. تحميل البيانات عند التهيئة
// ----------------------------------------------------
onMounted(async () => {
    await Promise.all([
        fetchEmployees(), 
        fetchHospitals()
    ]);
});

// ----------------------------------------------------
// 6. دوال الحساب
// ----------------------------------------------------

// التحقق من وجود مدير مخزن واحد فقط (غير مطلوب في API الجديد)
const hasWarehouseManager = computed(() => {
    return false; // API الجديد لا يدعم مدير المخزن
});

// الحصول على قائمة أسماء المستشفيات
const hospitalNames = computed(() => {
    return availableHospitals.value.map(hospital => hospital.name);
});
const supplierNames = computed(() => {
    return availableSuppliers.value.map(s => s.name);
});

// قوائم المستشفيات والموردين المحجوزة (لمودال الإضافة)
const filteredHospitalNamesForAdd = computed(() => {
    // المستشفيات التي لديها مدير حالياً
    const occupiedHospitals = employees.value
        .filter(emp => emp.hospital)
        .map(emp => emp.hospital);
        
    // استبعاد المستشفيات المحجوزة
    return hospitalNames.value.filter(name => !occupiedHospitals.includes(name));
});

const filteredSupplierNamesForAdd = computed(() => {
    // الموردين الذين لديهم مدير حالياً
    const occupiedSuppliers = employees.value
        .filter(emp => emp.supplier)
        .map(emp => emp.supplier);
        
    // استبعاد الموردين المحجوزين
    return supplierNames.value.filter(name => !occupiedSuppliers.includes(name));
});

// قوائم المستشفيات والموردين لمودال التعديل
const filteredHospitalNamesForEdit = ref([]);
const filteredSupplierNamesForEdit = ref([]);

// تحديث قوائم التعديل بناءً على الموظف المحدد
const updateEditLists = (employee) => {
    // المستشفيات المحجوزة (باستثناء مستشفى الموظف الحالي)
    const occupiedHospitals = employees.value
        .filter(emp => emp.hospital && emp.hospital !== employee.hospital)
        .map(emp => emp.hospital);
        
    filteredHospitalNamesForEdit.value = hospitalNames.value.filter(name => !occupiedHospitals.includes(name));

    // الموردين المحجوزين (باستثناء مورد الموظف الحالي)
    const occupiedSuppliers = employees.value
        .filter(emp => emp.supplier && emp.supplier !== employee.supplier)
        .map(emp => emp.supplier);

    filteredSupplierNamesForEdit.value = supplierNames.value.filter(name => !occupiedSuppliers.includes(name));
};

// الحصول على قائمة الأقسام التي لها مدير (غير مطلوب في API الجديد)
const availableDepartments = computed(() => {
    return []; // API الجديد لا يدعم الأقسام
});

const departmentsWithManager = computed(() => {
    return []; // API الجديد لا يدعم الأقسام
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

    const userId = employeeToToggle.value.id || employeeToToggle.value.fileNumber;
    const endpoint = employeeToToggle.value.isActive 
        ? `${API_URL}/${userId}/deactivate`
        : `${API_URL}/${userId}/activate`;

    try {
        const response = await api.patch(endpoint);

        const index = employees.value.findIndex(
            (p) => (p.id || p.fileNumber) === userId
        );
        if (index !== -1) {
            employees.value[index].isActive = response.data.data?.status === 'active';
            employees.value[index].status = response.data.data?.status || (employeeToToggle.value.isActive ? 'inactive' : 'active');
            employees.value[index].lastUpdated = new Date().toISOString();
        }

        showSuccessAlert(
            `✅ تم ${statusAction.value} حساب المدير ${employeeToToggle.value.name || employeeToToggle.value.fullName} بنجاح!`
        );
        closeStatusConfirmationModal();
        // إعادة جلب البيانات للتأكد من التحديث
        await fetchEmployees();
    } catch (error) {
        console.error(`Error ${statusAction.value} employee:`, error);
        const errorMessage = error.response?.data?.message || `فشل ${statusAction.value} حساب المدير.`;
        showSuccessAlert(`❌ ${errorMessage}`);
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

const filteredEmployees = computed(() => {
    let list = employees.value;

    // فلتر حسب الحالة
    if (statusFilter.value !== "all") {
        const isActiveFilter = statusFilter.value === "active";
        list = list.filter((employee) => employee.isActive === isActiveFilter);
    }

    // فلتر حسب الدور الوظيفي
    if (roleFilter.value !== "all") {
        list = list.filter((employee) => employee.type === roleFilter.value);
    }

    // فلتر حسب البحث
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (employee) =>
                (employee.id || employee.fileNumber)?.toString().includes(search) ||
                (employee.name || employee.fullName)?.toLowerCase().includes(search) ||
                employee.email?.toLowerCase().includes(search) ||
                employee.nationalId?.includes(search) ||
                (employee.birthDate && employee.birthDate.includes(search)) ||
                employee.phone?.includes(search) ||
                (employee.role && employee.role.toLowerCase().includes(search)) ||
                (employee.typeArabic && employee.typeArabic.toLowerCase().includes(search)) ||
                (employee.hospital && employee.hospital.toLowerCase().includes(search)) ||
                (employee.supplier && employee.supplier.toLowerCase().includes(search)) 
        );
    }

    // الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "name") {
                const nameA = (a.name || a.fullName || "").toString();
                const nameB = (b.name || b.fullName || "").toString();
                comparison = nameA.localeCompare(nameB, "ar");
            } else if (sortKey.value === "birth") {
                const dateA = a.birthDate ? new Date(a.birthDate) : new Date(0);
                const dateB = b.birthDate ? new Date(b.birthDate) : new Date(0);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "lastUpdated") {
                const dateA = new Date(a.lastUpdated || a.updatedAt || a.createdAt || 0);
                const dateB = new Date(b.lastUpdated || b.updatedAt || b.createdAt || 0);
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "role") {
                const roleA = a.typeArabic || a.role || "";
                const roleB = b.typeArabic || b.role || "";
                
                if (!roleA && !roleB) comparison = 0;
                else if (!roleA) comparison = 1;
                else if (!roleB) comparison = -1;
                else comparison = roleA.localeCompare(roleB, "ar");
            } else if (sortKey.value === "status") {
                if (a.isActive === b.isActive) comparison = 0;
                else if (a.isActive && !b.isActive) comparison = -1;
                else comparison = 1;
            } else if (sortKey.value === "hospital") {
                const hospitalA = a.hospital || "";
                const hospitalB = b.hospital || "";
                if (!hospitalA && !hospitalB) comparison = 0;
                else if (!hospitalA) comparison = 1;
                else if (!hospitalB) comparison = -1;
                else comparison = hospitalA.localeCompare(hospitalB, "ar");
            } else if (sortKey.value === "supplier") {
                const supplierA = a.supplier || "";
                const supplierB = b.supplier || "";
                if (!supplierA && !supplierB) comparison = 0;
                else if (!supplierA) comparison = 1;
                else if (!supplierB) comparison = -1;
                else comparison = supplierA.localeCompare(supplierB, "ar");
            } else if (sortKey.value === "department") {
                // API لا يدعم الأقسام
                comparison = 0;
            }

            return sortOrder.value === "asc" ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 9. منطق رسالة النجاح
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
// 10. حالة الـ Modals
// ----------------------------------------------------
const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAddModalOpen = ref(false);
const selectedEmployee = ref({});

const openViewModal = async (employee) => {
    try {
        const userId = employee.id || employee.fileNumber;
        const response = await api.get(`${API_URL}/${userId}`);
        const employeeData = response.data.data || response.data;
        
        selectedEmployee.value = {
            ...employeeData,
            id: employeeData.id,
            fileNumber: employeeData.id,
            name: employeeData.fullName || "",
            fullName: employeeData.fullName || "",
            email: employeeData.email || "",
            phone: employeeData.phone || "",
            nationalId: employeeData.nationalId || "",
            birthDate: employeeData.birthDate || "",
            role: employeeData.typeArabic || employeeData.type || "",
            type: employeeData.type || "",
            hospital: employeeData.hospital ? employeeData.hospital.name : "",
            supplier: employeeData.supplier ? employeeData.supplier.name : "",
            isActive: employeeData.status === 'active',
            status: employeeData.status || "",
            nameDisplay: employeeData.fullName || "",
            nationalIdDisplay: employeeData.nationalId || "",
            birthDisplay: employeeData.birthDate ? formatDateForDisplay(employeeData.birthDate) : "",
        };
        isViewModalOpen.value = true;
    } catch (error) {
        console.error("Error fetching employee details:", error);
        showSuccessAlert("❌ فشل جلب تفاصيل المدير");
    }
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
        supplier: employee.supplier || "", // Ensure supplier is passed correctly
    };
    // تحديث القوائم المتاحة للتعديل
    updateEditLists(selectedEmployee.value);
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
        // تحديد نوع المستخدم من الدور
        let userType = null;
        if (newEmployee.role === "مدير نظام المستشفى" || newEmployee.role === "hospital_admin") {
            userType = "hospital_admin";
        } else if (newEmployee.role === "مدير المورد" || newEmployee.role === "supplier_admin") {
            userType = "supplier_admin";
        } else {
            // محاولة العثور على النوع من القائمة
            const roleObj = employeeRoles.value.find(r => 
                r.name === newEmployee.role || r.value === newEmployee.role
            );
            userType = roleObj?.value || newEmployee.role;
        }

        if (!userType || !['hospital_admin', 'supplier_admin'].includes(userType)) {
            showSuccessAlert("⚠️ يرجى اختيار نوع المستخدم (مدير نظام المستشفى أو مدير المورد)");
            return;
        }

        // إعداد البيانات للإرسال
        const employeeData = {
            type: userType,
            full_name: newEmployee.name || newEmployee.fullName || "",
            email: newEmployee.email || "",
            phone: newEmployee.phone || "",
            national_id: newEmployee.nationalId || null,
            birth_date: newEmployee.birth || newEmployee.birthDate || null,
        };

        // إضافة hospital_id أو supplier_id حسب النوع
        if (userType === "hospital_admin") {
            if (!newEmployee.hospital) {
                showSuccessAlert("⚠️ يرجى اختيار المستشفى");
                return;
            }
            // البحث عن id المستشفى من الاسم
            const hospital = availableHospitals.value.find(h => h.name === newEmployee.hospital);
            if (!hospital) {
                showSuccessAlert("❌ المستشفى المختار غير مسجل في النظام");
                return;
            }
            employeeData.hospital_id = hospital.id;
        } else if (userType === "supplier_admin") {
            if (!newEmployee.supplier) {
                showSuccessAlert("⚠️ يرجى اختيار المورد");
                return;
            }
            // البحث عن id المورد من الاسم
            const supplier = availableSuppliers.value.find(s => s.name === newEmployee.supplier);
            if (!supplier) {
                showSuccessAlert("❌ المورد المختار غير مسجل في النظام");
                return;
            }
            employeeData.supplier_id = supplier.id;
        }

        const response = await api.post(API_URL, employeeData);
        const responseData = response.data.data || response.data;
        
        // إعادة جلب البيانات
        await fetchEmployees();
        
        closeAddModal();
        showSuccessAlert("✅ تم إضافة المدير الجديد بنجاح!");
    } catch (error) {
        console.error("Error adding employee:", error);
        const errorMessage = error.response?.data?.message || error.response?.data?.error || "فشل إضافة المدير. يرجى مراجعة البيانات المدخلة.";
        showSuccessAlert(`❌ ${errorMessage}`);
    }
};

// تحديث بيانات موظف
const updateEmployee = async (updatedEmployee) => {
    try {
        const userId = updatedEmployee.id || updatedEmployee.fileNumber;
        
        // إعداد البيانات للإرسال
        const employeeData = {
            full_name: updatedEmployee.name || updatedEmployee.fullName || "",
            email: updatedEmployee.email || "",
            phone: updatedEmployee.phone || "",
            national_id: updatedEmployee.nationalId || null,
            birth_date: updatedEmployee.birth || updatedEmployee.birthDate || null,
        };

        // إضافة hospital_id أو supplier_id حسب النوع
        // نحتاج أولاً معرفة دور المستخدم الحالي أو الجديد إذا تم تغييره
        // في الـ Modal الحالي، لا يوجد حقل لتغيير الدور مباشرة، لكن يجب تمرير هذه المعلومات.
        // بما أن `selectedEmployee` يحتوي على البيانات الحالية، يمكننا استخدامه.
        // لكن `updateEmployee` تستقبل فقط البيانات المعدلة من النموذج.
        // سنفترض أن `updatedEmployee` يحتوي على الدور إذا تم تعديله، وإلا نستخدم الدور الأصلي.
        
        let userRole = updatedEmployee.role || selectedEmployee.value.role;
         let userType = null;
        if (userRole === "مدير نظام المستشفى" || userRole === "hospital_admin") {
            userType = "hospital_admin";
        } else if (userRole === "مدير المورد" || userRole === "supplier_admin") {
            userType = "supplier_admin";
        }
        
        if (userType === "hospital_admin") {
            if (updatedEmployee.hospital) {
                const hospital = availableHospitals.value.find(h => h.name === updatedEmployee.hospital);
                if (hospital) {
                    employeeData.hospital_id = hospital.id;
                    employeeData.supplier_id = null;
                }
            }
        } else if (userType === "supplier_admin") {
            if (updatedEmployee.supplier) {
                const supplier = availableSuppliers.value.find(s => s.name === updatedEmployee.supplier);
                if (supplier) {
                    employeeData.supplier_id = supplier.id;
                    employeeData.hospital_id = null;
                }
            }
        }

        const response = await api.put(
            `${API_URL}/${userId}`,
            employeeData
        );

        // إعادة جلب البيانات
        await fetchEmployees();

        closeEditModal();
        showSuccessAlert(
            `✅ تم تحديث بيانات المدير بنجاح!`
        );
    } catch (error) {
        console.error("Error updating employee:", error);
        const errorMessage = error.response?.data?.message || error.response?.data?.error || "فشل تعديل بيانات المدير.";
        showSuccessAlert(`❌ ${errorMessage}`);
    }
};

const getStatusTooltip = (isActive) => {
    return isActive ? "تعطيل الحساب" : "تفعيل الحساب";
};

// إعادة تعيين كلمة المرور
const resetPassword = async (employee) => {
    if (!confirm(`هل أنت متأكد من رغبتك في إعادة تعيين كلمة المرور للمدير ${employee.name || employee.fullName}؟`)) {
        return;
    }

    try {
        const userId = employee.id || employee.fileNumber;
        const response = await api.post(`${API_URL}/${userId}/reset-password`);
        
        showSuccessAlert("✅ تم إعادة تعيين كلمة المرور بنجاح! سيتم إرسال كلمة المرور الجديدة إلى البريد الإلكتروني.");
    } catch (error) {
        console.error("Error resetting password:", error);
        const errorMessage = error.response?.data?.message || error.response?.data?.error || "فشل إعادة تعيين كلمة المرور.";
        showSuccessAlert(`❌ ${errorMessage}`);
    }
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

        <h1>قائمة الموظفين </h1>
    
        <p class="results-info">
            عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>رقم الملف</th>
                    <th>الاسم الرباعي</th>
                    <th>الدور الوظيفي</th>
                    <th>المستشفى</th>
                    <th>المورد</th>
                    <th>حالة الحساب</th>
                   
           
                    <th>رقم الهاتف</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredEmployees.value.forEach((employee) => {
        const roleName = employee.typeArabic || employee.role || '';
        
        tableHtml += `
            <tr>
                <td>${employee.id || employee.fileNumber || ''}</td>
                <td>${employee.name || employee.fullName || ''}</td>
                <td>${roleName}</td>
                <td>${employee.hospital || '-'}</td>
                <td>${employee.supplier || '-'}</td>
                <td class="${employee.isActive ? "status-active" : "status-inactive"}">
                    ${employee.isActive ? "مفعل" : (employee.status === 'pending_activation' ? "بانتظار التفعيل" : "معطل")}
                </td>  
            
             
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
            <!-- المحتوى الرئيسي -->
            <div>
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3 sm:gap-0">
                    <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <search v-model="searchTerm" />

                        <!-- فلتر حالة الحساب -->
                        <div class="flex items-center gap-2">
                            <select 
                                v-model="statusFilter"
                                class="h-11 px-3 border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] focus:outline-none cursor-pointer"
                            >
                                <option value="all">جميع الحالات</option>
                                <option value="active">المفعلون فقط</option>
                                <option value="inactive">المعطلون فقط</option>
                            </select>
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
                                        @click="roleFilter = 'all'"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                roleFilter === 'all',
                                        }"
                                    >
                                        جميع الأدوار
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="roleFilter = 'hospital_admin'"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                roleFilter === 'hospital_admin',
                                        }"
                                    >
                                        مدير نظام المستشفى فقط
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="roleFilter = 'supplier_admin'"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                roleFilter === 'supplier_admin',
                                        }"
                                    >
                                        مدير المورد فقط
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب المستشفى:
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('hospital', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'hospital' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        المستشفى (أ - ي)
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('hospital', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'hospital' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        المستشفى (ي - أ)
                                    </a>
                                </li>

                                <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                    حسب المورد:
                                </li>
                                <li>
                                    <a
                                        @click="sortEmployees('supplier', 'asc')"
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
                                        @click="sortEmployees('supplier', 'desc')"
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
                                        <th class="hospital-col">المستشفى</th>
                                        <th class="supplier-col">المورد</th>
                                        <th class="status-col">الحالة</th>
                                   
                                        <th class="phone-col">رقم الهاتف</th>
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
                                            <ErrorState :message="error" :retry="fetchEmployees" />
                                        </td>
                                    </tr>
                                    <template v-else>
                                        <tr
                                            v-for="(employee, index) in filteredEmployees"
                                            :key="employee.id || employee.fileNumber || index"
                                            class="hover:bg-gray-100 border border-gray-300"
                                        >
                                            <td class="file-number-col">
                                                {{ employee.id || employee.fileNumber || 'N/A' }}
                                            </td>
                                            <td class="name-col">
                                                {{ employee.name || employee.fullName || 'N/A' }}
                                            </td>
                                            <td class="role-col">
                                                {{ employee.typeArabic || employee.role || 'N/A' }}
                                            </td>
                                            <td class="hospital-col">
                                                {{ employee.hospital || "-" }}
                                            </td>
                                            <td class="supplier-col">
                                                {{ employee.supplier || "-" }}
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
                                                            : employee.status === 'pending_activation' ? " غير مفعل"
                                                            : "معطل"
                                                    }}
                                                </span>
                                            </td>
                                          
                                            <td class="phone-col">
                                                {{ employee.phone || 'N/A' }}
                                            </td>

                                            <td class="actions-col">
                                                <div class="flex gap-1.5 justify-center items-center flex-wrap">
                                                    <!-- زر عرض البيانات -->
                                                    <button
                                                        @click="openViewModal(employee)"
                                                        class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        title="عرض البيانات"
                                                    >
                                                        <Icon
                                                            icon="tabler:eye"
                                                            class="w-4 h-4 text-green-600"
                                                        />
                                                    </button>

                                                    <!-- زر تعديل البيانات -->
                                                    <button
                                                        @click="openEditModal(employee)"
                                                        class="p-2 rounded-lg bg-yellow-50 hover:bg-yellow-100 border border-yellow-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        title="تعديل البيانات"
                                                    >
                                                        <Icon
                                                            icon="line-md:pencil"
                                                            class="w-4 h-4 text-yellow-600"
                                                        />
                                                    </button>

                                                    <!-- زر تفعيل/تعطيل الحساب -->
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
                                                            class="w-4 h-4 text-red-600"
                                                        />
                                                        <Icon
                                                            v-else
                                                            icon="mdi:power"
                                                            class="w-4 h-4 text-green-600"
                                                        />
                                                    </button>

                                                    <!-- زر إعادة تعيين كلمة المرور -->
                                                    <button
                                                        @click="resetPassword(employee)"
                                                        class="p-2 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                        title="إعادة تعيين كلمة المرور"
                                                    >
                                                        <Icon
                                                            icon="mdi:lock-reset"
                                                            class="w-4 h-4 text-blue-600"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr v-if="filteredEmployees.length === 0">
                                            <td colspan="8" class="py-12">
                                                <EmptyState message="لا توجد بيانات موظفين حالياً" />
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
        :available-hospitals="filteredHospitalNamesForAdd"
        :available-suppliers="filteredSupplierNamesForAdd"
        :available-departments="availableDepartments"
        :available-roles="employeeRoles"
        :departments-with-manager="departmentsWithManager"
        @close="closeAddModal"
        @save="addEmployee"
    />

    <employeeEditModel
        :is-open="isEditModalOpen"
        :has-warehouse-manager="hasWarehouseManager"
        :available-hospitals="filteredHospitalNamesForEdit"
        :available-suppliers="filteredSupplierNamesForEdit"
        :available-departments="availableDepartments"
        :available-roles="employeeRoles"
        :departments-with-manager="departmentsWithManager"
        :employee="selectedEmployee"
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
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" 
        @click.self="closeStatusConfirmationModal"
    >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon 
                        icon="solar:question-circle-bold-duotone" 
                        class="w-10 h-10 text-[#4DA1A9]" 
                    />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">
                    {{ statusAction === "تفعيل" ? "تفعيل حساب الموظف" : "تعطيل حساب الموظف" }}
                </h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من رغبتك في {{ statusAction }} حساب الموظف
                    <strong class="text-[#2E5077]">{{ employeeToToggle?.name }}</strong>؟
                    <br>
                    <span class="text-sm text-[#4DA1A9]">
                        {{ statusAction === "تفعيل" ? "سيتم تمكين الوصول إلى النظام" : "سيتم إيقاف الوصول إلى النظام" }}
                    </span>
                </p>
            </div>
            <div class="flex justify-center bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="closeStatusConfirmationModal" 
                    class="px-15 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-400 bg-gray-100 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="confirmStatusToggle" 
                    class="px-15 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1"
                >
                    {{ statusAction }}
                </button>
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
    width: 180px;
    min-width: 180px;
    max-width: 180px;
    text-align: center;
    padding: 0.5rem;
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
.hospital-col {
    width: 150px;
    min-width: 150px;
}
.supplier-col {
    width: 150px;
    min-width: 150px;
}
.department-col {
    width: 130px;
    min-width: 130px;
}
.phone-col {
    width: 120px;
    min-width: 120px;
}
.birth-col {
    width: 130px;
    min-width: 130px;
}
.name-col {
    width: 120px;
    min-width: 120px;
}
</style>