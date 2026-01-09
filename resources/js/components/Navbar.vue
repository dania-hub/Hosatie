<script setup>
import { ref, onMounted, computed } from "vue";
import { Icon } from "@iconify/vue";
import { Link, usePage } from "@inertiajs/vue3"; // 👈 تم استيراد Link و usePage
import axios from 'axios'; 
import NotificationDropdown from './NotificationDropdown.vue'; 

 
const LOGOUT_ENDPOINT = "/api/logout/dashboard"; 
const PROFILE_ENDPOINT = "/api/profile/dashboard"; 

// حالة التحكم في نافذة تأكيد تسجيل الخروج
const showLogoutConfirmation = ref(false);

const userData = ref({
    name: '',
    email: '',
    type: ''
});

// 🆕 استخدام usePage لجلب بيانات المستخدم (fallback)
const page = usePage();

// 🆕 خاصية محوسبة لبريد المستخدم واسمه
const userEmail = computed(() => {
    if (userData.value.email) return userData.value.email;
    return page.props.auth?.user?.email || 'user@example.com';
});

const userName = computed(() => {
    if (userData.value.name) return userData.value.name;
    return page.props.auth?.user?.name || 'اسم المستخدم';
});

// 🆕 خاصية محوسبة للتحقق من إمكانية عرض الإشعارات
// 💡 لإضافة أدوار جديدة: أضف نوع المستخدم في المصفوفة allowedTypes
const canShowNotifications = computed(() => {
    const userType = userData.value.type || page.props.auth?.user?.type || '';
    const allowedTypes = ['supplier_admin', 'warehouse_manager', 'department_head', 'department_admin', 'pharmacist','hospital_admin','super_admin']; // 👈 أضف الأدوار هنا
    return allowedTypes.includes(userType);
});

// 🆕 خريطة المسارات إلى الأسماء العربية
const routeNames = {
    '/': 'الرئيسية',
    '/profile': 'الملف الشخصي',
    // Super Admin
    '/superAdmin/patients': 'قائمة المرضى',
    '/superAdmin/medications': 'قائمة الأدوية',
    '/superAdmin/operations': 'سجل العمليات',
    '/superAdmin/all-operations': 'سجل العمليات الشامل',
    '/superAdmin/statistics': 'الإحصائيات',
    '/superAdmin/employees': 'المدراء و الموردين',
    '/superAdmin/AllemployeesList': 'الموظفين',
    '/superAdmin/requests': 'طلبات الموردين',
    '/superAdmin/inventory': 'مخزون',
    '/superAdmin/hospital': 'المستشفيات',
    '/superAdmin/Supply': 'شركات التوريد',
    // Hospital Admin
    '/admin/medications': 'قائمة الأدوية',
    '/admin/operations': 'سجل العمليات',
    '/admin/all-operations': 'سجل العمليات الشامل',
    '/admin/statistics': 'الإحصائيات',
    '/admin/employees': 'الموظفين',
    '/admin/requests': 'طلبات التوريد الخارجية',
    '/admin/departments': 'الأقسام',
   
    '/admin/transfer-requests': 'طلبات النقل المرسلة',
    '/admin/complaints': 'طلبات المرضى',
    '/admin/patients': 'قائمة المرضى',
    // Pharmacist
    '/pharmacist/medications': 'قائمة الأدوية',
    '/pharmacist/operations': 'سجل العمليات',
    '/pharmacist/patients': 'قائمة المرضى',
    '/pharmacist/statistics': 'الإحصائيات',
    '/pharmacist/requests': 'طلبات التوريد الداخلي',
    // Doctor
    '/doctor/patients': 'قائمة المرضى',
    '/doctor/operations': 'سجل العمليات',
    '/doctor/statistics': 'الإحصائيات',
    // Data Entry
    '/data-entry/patients': 'قائمة المرضى',
    '/data-entry/operations': 'سجل العمليات',
    '/data-entry/statistics': 'الإحصائيات',
    // Department Manager
    '/department/patients': 'قائمة المرضى',
    '/department/operations': 'سجل العمليات',
    '/department/statistics': 'الإحصائيات',
    '/department/requests': 'طلبات التوريد الداخلي',
    // Storekeeper
    '/storekeeper/medications': 'قائمة الأدوية',
    '/storekeeper/operations': 'سجل العمليات',
    '/storekeeper/requests': 'طلبات التوريد الداخلي',
    '/storekeeper/statistics': 'الإحصائيات',
    '/storekeeper/supply-requests': 'طلبات التوريد',
    // Supplier
    '/Supplier/statistics': 'الإحصائيات',
    '/Supplier/medications': 'قائمة الأدوية',
    '/Supplier/operations': 'سجل العمليات',
    '/Supplier/requests': 'طلبات التوريد الخارجية',
    '/Supplier/supply-requests': 'طلبات التوريد ',
};

// 🆕 خاصية محوسبة للحصول على اسم الصفحة الحالية
const currentPageName = computed(() => {
    // الحصول على المسار الحالي من Inertia أو من window.location
    let currentPath = '';
    if (page && page.url) {
        currentPath = page.url.split('?')[0]; // إزالة query parameters
    } else if (typeof window !== 'undefined') {
        currentPath = window.location.pathname;
    }
    
    // إذا كان المسار فارغًا أو الجذر فقط، نعيد اسمًا فارغًا
    if (!currentPath || currentPath === '/') {
        return '';
    }
    
    // البحث المباشر عن المسار في الخريطة
    if (routeNames[currentPath]) {
        return routeNames[currentPath];
    }
    
    // البحث عن مسار يبدأ بالمسار الحالي (للمسارات الفرعية)
    // نبحث من الأطول إلى الأقصر للحصول على أفضل تطابق
    const sortedRoutes = Object.entries(routeNames)
        .filter(([route]) => route !== '/')
        .sort(([a], [b]) => b.length - a.length);
    
    for (const [route, name] of sortedRoutes) {
        if (currentPath.startsWith(route)) {
            return name;
        }
    }
    
    // إذا لم يتم العثور على المسار، نعيد اسمًا فارغًا بدلاً من "الرئيسية"
    return '';
});

/**
 * 🆕 دالة للحصول على headers مع token المصادقة
 */
const getAuthHeaders = () => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    return {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };
};

/**
 * 🆕 دالة لجلب بيانات المستخدم من API
 */
const fetchUserProfile = async () => {
    try {
        const response = await axios.get(PROFILE_ENDPOINT, getAuthHeaders());
        const data = response.data;
        
        // معالجة البيانات بناءً على هيكل الاستجابة
        if (data.success && data.data) {
            const profile = data.data;
            userData.value = {
                name: profile.full_name || profile.name || profile.fullName || '',
                email: profile.email || '',
                type: profile.type || ''
            };
        } else if (data.full_name || data.name || data.email) {
            // إذا كانت البيانات مباشرة في الاستجابة
            userData.value = {
                name: data.full_name || data.name || data.fullName || '',
                email: data.email || '',
                type: data.type || ''
            };
        }
        
        // جلب الإشعارات فقط إذا كان المستخدم من النوع المصرح له
        // 💡 لإضافة أدوار جديدة: أضف نوع المستخدم في المصفوفة allowedTypes
        const allowedTypes = ['supplier_admin', 'warehouse_manager', 'department_head', 'department_admin', 'pharmacist']; // 👈 أضف الأدوار هنا
        if (allowedTypes.includes(userData.value.type)) {
            // Notifications are handled by NotificationDropdown component
        }
    } catch (e) {
        console.error("Failed to fetch user profile:", e);
        // في حالة الفشل، سيتم استخدام البيانات من Inertia props أو القيم الافتراضية
    }
};

/**
 * 🆕 فتح نافذة تأكيد تسجيل الخروج
 */
const openLogoutConfirmation = () => {
    showLogoutConfirmation.value = true;
};

/**
 * 🆕 إلغاء تسجيل الخروج وإغلاق النافذة
 */
const cancelLogout = () => {
    showLogoutConfirmation.value = false;
};

/**
 * 🆕 دالة لتسجيل الخروج عبر طلب POST باستخدام Axios
 */
const confirmLogout = async () => {
    try {
        const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
        if (!token) {
            window.location.href = '/';
            return;
        }

        await axios.post(LOGOUT_ENDPOINT, {}, getAuthHeaders());
        
        showLogoutConfirmation.value = false;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('token');
        window.location.href = '/';

    } catch (e) {
        console.error("Failed to logout:", e);
        showLogoutConfirmation.value = false;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('token');
        window.location.href = '/login';
    }
};

onMounted(() => {
    fetchUserProfile();
});


</script>
<template>
  <div v-bind="$attrs">
    <header
      class="navbar bg-white z-[50] border-b border-primary/10 top-0 z-10 shadow-sm px-4 lg:px-8 py-4 flex justify-between items-center"
    >
    <div class="flex items-center gap-4">
      <label for="my-drawer" class="btn btn-ghost lg:hidden p-0">
        <Icon icon="ic:baseline-menu" class="w-6 h-6 text-[#2E5077]" />
      </label>
      
      <!-- اسم الصفحة الحالية -->
      <div class="flex items-center gap-2">
        <!-- Icon Removed -->
        <h2 class="text-base md:text-lg font-bold text-[#2E5077]">{{ currentPageName }}</h2>
      </div>
    </div>

    <section class="flex gap-6 items-center">
      <div v-if="canShowNotifications" class="dropdown dropdown-end">
        <NotificationDropdown />
      </div>
      <div class="dropdown dropdown-end  ">
        <button tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
          <div class="w-10 rounded-full">
            <img src="/assets/OIP.webp" alt="user" class="h-10 w-10 object-cover" />
          </div>
        </button>

        <div
          tabindex="0"
          class="mt-5 z-100 shadow dropdown-content bg-white rounded-lg w-58 text-right p-0"
        >
          <div class="bg-gray-100 text-[#2E5077] p-4 rounded-t-lg border-b border-gray-200">
            <p class="text-base font-bold">{{ userName }}</p>
            <p class="text-sm text-gray-600">{{ userEmail }}</p> 
          </div>

          <ul
            class="space-y-0 text-sm font-semibold text-[#2E5077] divide-y divide-gray-100"
          >
            <li>
              <Link
                href="/profile" 
                class="flex items-center w-full p-3 hover:bg-gray-50 transition-colors"
              >
                <Icon icon="ph:user-circle" class="w-5 h-5 ml-3" />
                <span>الملف الشخصي</span>
              </Link>
            </li>

            <li>
              <a
                @click.prevent="openLogoutConfirmation"
                class="flex items-center w-full p-3 hover:bg-red-50 transition-colors text-red-600 hover:text-red-600 cursor-pointer"
              >
                <Icon icon="ph:sign-out" class="w-5 h-5 ml-3" />
                <span>تسجيل الخروج</span>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </section>
  </header>

  <!-- Logout Confirmation Modal -->
  <div v-if="showLogoutConfirmation" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="cancelLogout">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
      <div class="p-6 text-center space-y-4">
        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
          <Icon icon="ph:sign-out" class="w-8 h-8 text-red-500" />
        </div>
        <h3 class="text-xl font-bold text-[#2E5077]">تسجيل الخروج</h3>
        <p class="text-gray-500 leading-relaxed">
          هل أنت متأكد من رغبتك في تسجيل الخروج؟
          <br>
          <span class="text-sm text-red-500">سيتم إغلاق جلسة العمل الحالية</span>
        </p>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
        <button 
          @click="cancelLogout" 
          class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
        >
          إلغاء
        </button>
        <button 
          @click="confirmLogout" 
          class="flex-1 px-4 py-2.5 rounded-xl bg-red-500 text-white font-medium hover:bg-red-600 transition-colors duration-200 shadow-lg shadow-red-500/20"
        >
          تسجيل الخروج
        </button>
      </div>
    </div>
  </div>
  </div>
</template>
