<script setup>
import { ref, onMounted, computed } from "vue";
import { Icon } from "@iconify/vue";
import { Link, usePage } from "@inertiajs/vue3"; // 👈 تم استيراد Link و usePage
import axios from 'axios'; 

// ⚠️ عدل هذا الثابت ليطابق نقطة النهاية (Endpoint) الخاصة بك
const NOTIFICATIONS_ENDPOINT = "/api/v1/user/notifications"; 
// 🆕 نقطة نهاية تسجيل الخروج - يجب تعديلها حسب نظامك
const LOGOUT_ENDPOINT = "/logout"; 

// حالات المكون
const notifications = ref([]);
const unreadCount = ref(0);
const loading = ref(true);
const error = ref(null);

// 🆕 استخدام usePage لجلب بيانات المستخدم
const page = usePage();

// 🆕 خاصية محوسبة لبريد المستخدم واسمه
const userEmail = computed(() => page.props.auth?.user?.email || 'user@example.com');
const userName = computed(() => page.props.auth?.user?.name || 'اسم المستخدم');


/**
 * 🛠️ دالة لجلب الإشعارات باستخدام Axios (لم تتغير)
 */
const fetchNotifications = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await axios.get(NOTIFICATIONS_ENDPOINT); 
        const data = response.data;
        
        notifications.value = data.notifications || data; 
        unreadCount.value = notifications.value.filter(n => !n.read).length;

    } catch (e) {
        console.error("Failed to fetch notifications:", e);
        error.value = "تعذر جلب البيانات. (تأكد من عمل الـ API)";
        notifications.value = [];
        unreadCount.value = 0;
    } finally {
        loading.value = false;
    }
};

/**
 * دالة لتحديد الإشعار كمقروء وإرسال تحديث للسيرفر باستخدام Axios (لم تتغير)
 */
const markAsRead = async (notification) => {
    if (notification.read) return;

    // 1. تحديث الواجهة أولاً
    notification.read = true;
    unreadCount.value = notifications.value.filter(n => !n.read).length;
    
    // 2. إرسال طلب تحديث للسيرفر
    try {
        await axios.patch(`${NOTIFICATIONS_ENDPOINT}/${notification.id}/read`); 
        
    } catch (e) {
        console.error("Failed to mark notification as read:", e);
        // يمكنك هنا اختيار إضافة منطق إعادة الحالة في حالة الفشل
    }
};

/**
 * 🆕 دالة لتسجيل الخروج عبر طلب POST باستخدام Axios
 */
const logout = async () => {
    try {
        // يمكنك استخدام axios.post إذا كان نقطة النهاية لا تتطلب csrf_token 
        // أو إذا كان لديك middleware يقوم بإضافة الرمز تلقائيًا.
        // إذا كنت تستخدم Laravel/Sanctum، غالبًا ما تحتاج إلى طلب POST هنا.
        await axios.post(LOGOUT_ENDPOINT);

        // إعادة تحميل الصفحة بعد تسجيل الخروج أو التوجيه لصفحة تسجيل الدخول
        window.location.reload(); 

    } catch (e) {
        console.error("Failed to logout:", e);
        alert("فشل تسجيل الخروج. يرجى المحاولة مرة أخرى.");
    }
};

// 🚀 جلب البيانات عند تحميل المكون
onMounted(() => {
    fetchNotifications();
});
</script>
<template>
  <header
    class="navbar bg-white border-b border-primary/10 top-0 z-10 shadow-sm px-4 lg:px-8 py-4 flex justify-between items-center"
  >
    <div class="flex items-center">
      <label for="my-drawer" class="btn btn-ghost lg:hidden p-0 mr-4">
        <Icon icon="ic:baseline-menu" class="w-6 h-6 text-[#2E5077]" />
      </label>
    </div>

    <section class="flex gap-6 items-center">
      <div class="dropdown dropdown-end">
        <button tabindex="0" role="button" class="btn btn-ghost btn-circle relative">
          <Icon icon="ic:round-notifications" class="w-7 h-7 text-[#2E5077]" />
          <div
            v-if="unreadCount > 0 && !loading"
            class="absolute top-1 right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center border-2 border-white"
          >
            {{ unreadCount }}
          </div>
        </button>

        <div
          tabindex="0"
          class="mt-5 z-[1] shadow dropdown-content bg-white rounded-lg w-80 text-right p-0"
        >
          <div class="bg-[#2E5077] text-white p-4 rounded-t-lg">
            <h3 class="text-lg font-bold">الإشعارات</h3>
            <span class="text-4xl font-extrabold">{{ unreadCount }}</span>
          </div>

          <ul
            class="max-h-96 overflow-y-auto space-y-0 text-sm text-[#2E5077] divide-y divide-gray-100"
          >
            <li v-if="loading" class="p-4 text-center text-gray-500">
              جاري تحميل الإشعارات...
            </li>

            <li v-else-if="error" class="p-4 text-center text-red-500">
              {{ error }}
            </li>

            <li v-else v-for="(notification, index) in notifications" :key="index">
              <a
                class="flex flex-col items-start p-3 hover:bg-gray-50 transition-colors"
                @click="markAsRead(notification)"
              >
                <div class="flex items-center w-full mb-1">
                  <Icon
                    :icon="notification.icon || 'ph:info'"
                    class="w-5 h-5 ml-3 text-[#7093bb] flex-shrink-0"
                  />
                  <p class="font-medium text-sm text-gray-800">
                    {{ notification.message }}
                  </p>
                </div>
                <p class="text-xs text-gray-500 mt-1 mr-8">
                  {{ notification.date }}
                </p>
              </a>
            </li>

            <li
              v-if="!loading && !error && notifications.length === 0"
              class="p-4 text-center text-gray-500"
            >
              لا توجد إشعارات جديدة.
            </li>
          </ul>
        </div>
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
                @click.prevent="logout"
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
</template>
