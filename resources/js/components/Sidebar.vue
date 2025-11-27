<template>
  <aside
    :class="[
      // الأساسيات: اللون والخلفية والتحولات
      'bg-[#2E5077] text-white rounded-l-3xl min-h-screen flex flex-col justify-start flex-shrink-0 transition-all duration-300 shadow-xl',
      // إخفاء شريط التمرير مع السماح بالتمرير الفعلي
      'overflow-y-auto custom-scrollbar-hide',
      // التحكم في العرض حسب حالة الطي
      isCollapsed ? 'w-20' : 'w-55',
    ]"
  >
    <div
      class="p-4 flex justify-end cursor-pointer sticky top-0 bg-[#2E5077] z-10"
      @click="toggleSidebar"
      :title="isCollapsed ? 'فتح الشريط الجانبي' : 'طي الشريط الجانبي'"
    >
      <Icon
        :icon="isCollapsed ? 'line-md:chevron-left' : 'line-md:chevron-right'"
        class="w-6 h-6 text-white hover:text-[#1cab8c] transition-colors"
      />
    </div>

    <header
      :class="[
        // خصائص ثابتة للـ header
        'px-6 py-4 flex items-center transition-all duration-300 border-b border-white/10',
        // تعديل التباعد والمحاذاة عند الطي لمركزة الشعار
        isCollapsed ? 'justify-center px-0' : '',
      ]"
    >
      <div
        :class="[
          'flex items-center transition-all duration-300',
          // عند الطي، اجعل الشعار في المنتصف
          isCollapsed ? 'justify-center' : 'space-x-3 rtl:space-x-reverse',
        ]"
      >
        <img src="/assets/logo2.png" alt="user" class="h-15 w-15 object-contain flex-shrink-0" />

        <h1
          :class="[
            'text-3xl font-extrabold tracking-wider whitespace-nowrap transition-all duration-300',
            isCollapsed ? 'w-0 opacity-0' : 'opacity-100', // إخفاء النص بشكل سلس
          ]"
        >
          حُصتي
        </h1>
      </div>
    </header>

    <nav class="menu px-4 pt-6 pb-4 flex-grow" aria-label="Main navigation">
      <ul role="menu" class="space-y-2 text-base font-semibold">
        <li v-for="(link, index) in links" :key="index">
          <router-link
            role="menuitem"
            :to="link.to"
            :class="[
              // المظهر الأساسي للرابط
              'flex items-center w-full text-right py-3 rounded-lg transition-all duration-300 group',
              // تأثير التمرير
              'hover:bg-white/15',
              // التحكم في المحاذاة والتباعد
              isCollapsed ? 'justify-center px-0' : 'px-3',
              // فئة الرابط النشط: تطبيق لون التفاعل (الأخضر) كخلفية
              'router-link-active:bg-[#1cab8c] router-link-active:shadow-md router-link-active:text-white',
            ]"
          >
            <Icon
              :icon="link.icon"
              :class="[
                'w-6 h-6 text-[#ffffff] flex-shrink-0 transition-margin duration-300',
                isCollapsed ? 'mx-auto' : '',
                // تلوين الأيقونة بلون التفاعل عند التمرير
                'group-hover:text-[#1cab8c]',
                // جعل الأيقونة بيضاء عند التنشيط
                'router-link-active:text-white',
              ]"
            />
            <span
              :class="[
                'whitespace-nowrap overflow-hidden transition-all duration-300',
                isCollapsed ? 'w-0 ml-0 opacity-0' : 'ml-4 w-40 opacity-100',
              ]"
            >
              {{ link.name }}
            </span>
          </router-link>
        </li>
      </ul>
    </nav>

    <footer
      :class="[
        // الفوتر يختفي عند الطي
        'px-3 py-4 text-xs font-light text-white/70 border-t border-white/10 text-center transition-opacity duration-300 flex-shrink-0',
        isCollapsed ? 'opacity-0 h-0 p-0' : 'opacity-100',
      ]"
    >
      © 2026 حُصتي. جميع الحقوق محفوظة
    </footer>
  </aside>
</template>

<script setup>
import { ref } from "vue";
// تأكد من استيراد RouterLink إذا كنت تستخدم <router-link> مباشرة في المكون
// import { RouterLink } from 'vue-router'
import { Icon } from "@iconify/vue";

// الحالة التي تحدد ما إذا كان الشريط مطويًا (true) أو مفتوحًا (false)
const isCollapsed = ref(false);

// الدالة التي تعكس حالة الشريط عند النقر على الزر
const toggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value;
};

// تعريف الروابط مع خاصية 'to' للتوجيه
const links = ref([
  { icon: "line-md:account", name: "قائمة المرضى", to: "/patients" },
  { name: "سجل العمليات", icon: "line-md:document-report", to: "/history" },
  {
    name: "الإحصائيات",
    icon: "material-symbols-light:bar-chart-4-bars",
    to: "/analytics",
  },

]);
</script>

<style scoped>
/* 🎯 CSS لإخفاء شريط التمرير في المتصفحات المختلفة */
.custom-scrollbar-hide {
  /* لمتصفحات Chrome, Safari, Opera, Edge (Webkit) */
  -ms-overflow-style: none; /* IE and Edge */
}

.custom-scrollbar-hide::-webkit-scrollbar {
  /* لمتصفحات Webkit */
  display: none;
  width: 0;
  height: 0;
}

/* لمتصفحات Firefox */
.custom-scrollbar-hide {
    scrollbar-width: none;
}
</style>