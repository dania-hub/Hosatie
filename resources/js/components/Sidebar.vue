<template>
    
    <aside
        :class="[
            'bg-[#2E5077] text-white rounded-l-3xl min-h-screen flex flex-col justify-start flex-shrink-0 transition-all duration-300 shadow-xl',
            'overflow-y-auto custom-scrollbar-hide',
            isCollapsed ? 'w-20' : 'w-55',
        ]"
    >
        <div
            class="p-4 flex justify-end cursor-pointer sticky top-0 bg-[#2E5077] z-10"
            @click="toggleSidebar"
            :title="isCollapsed ? 'فتح الشريط الجانبي' : 'طي الشريط الجانبي'"
        >
            <Icon
                :icon="
                    isCollapsed
                        ? 'line-md:chevron-left'
                        : 'line-md:chevron-right'
                "
                class="w-6 h-6 text-white hover:text-[#1cab8c] transition-colors"
            />
        </div>

        <header
            :class="[
                'px-6 py-4 flex items-center transition-all duration-300 border-b border-white/10',
                isCollapsed ? 'justify-center px-0' : '',
            ]"
        >
            <div
                :class="[
                    'flex items-center transition-all duration-300',
                    isCollapsed
                        ? 'justify-center'
                        : 'space-x-3 rtl:space-x-reverse',
                ]"
            >
                <img
                    src="/assets/logo2.png"
                    alt="user"
                    class="h-15 w-15 object-contain flex-shrink-0"
                />

                <h1
                    :class="[
                        'text-3xl font-extrabold tracking-wider whitespace-nowrap transition-all duration-300',
                        isCollapsed ? 'w-0 opacity-0' : 'opacity-100',
                    ]"
                >
                    حُصتي
                </h1>
            </div>
        </header>

        <nav class="menu px-4 pt-6 pb-4 flex-grow" aria-label="Main navigation">
            <ul role="menu" class="space-y-2 text-base font-semibold">
                <li v-for="(link, index) in dataEntryLinks" :key="index">
                    <Link
                        role="menuitem"
                        :href="link.to"
                        :class="[
                            'flex items-center w-full text-right py-3 rounded-lg transition-all duration-300 group',
                            'hover:bg-white/15',
                            isCollapsed ? 'justify-center px-0' : 'px-3',
                            // سنستخدم `link.to` مع `route().current()` أو `page.url` لتحديد الفئة النشطة
                            // نظرًا لأنني لا أملك الوصول إلى `route()` أو `page`، سأستخدم فئة عامة (إذا كانت مدعومة)
                            // لكن لتبقى الفئة القديمة تعمل سنعدل قليلا.
                            // *يجب الانتباه إلى أن تحديد الفئة النشطة لـ Inertia يتطلب مقارنة URL الحالي*
                            isCurrent(link.to)
                                ? 'bg-[#7093bb] shadow-md text-white'
                                : '', // **التعديل الهام هنا**
                        ]"
                    >
                        <Icon
                            :icon="link.icon"
                            :class="[
                                'w-6 h-6 text-[#ffffff] flex-shrink-0 transition-margin duration-300',
                                isCollapsed ? 'mx-auto' : '',
                                'group-hover:text-[#1cab8c]',
                                isCurrent(link.to) ? 'text-white' : '', // **التعديل الهام هنا**
                            ]"
                        />
                        <span
                            :class="[
                                'whitespace-nowrap overflow-hidden transition-all duration-300',
                                isCollapsed
                                    ? 'w-0 ml-0 opacity-0'
                                    : 'ml-4 w-40 opacity-100',
                            ]"
                        >
                            {{ link.name }}
                        </span>
                    </Link>
                </li>
            </ul>
        </nav>

        <footer
            :class="[
                'px-3 py-4 text-xs font-light text-white/70 border-t border-white/10 text-center transition-opacity duration-300 flex-shrink-0',
                isCollapsed ? 'opacity-0 h-0 p-0' : 'opacity-100',
            ]"
        >
            © 2026 حُصتي. جميع الحقوق محفوظة
        </footer>
    </aside>
</template>

<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";
import { Link, usePage } from "@inertiajs/vue3"; // تم استيراد usePage

const isCollapsed = ref(false);
const page = usePage(); // الحصول على خصائص الصفحة الحالية من Inertia

const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
};

// **دالة جديدة لتحديد ما إذا كان الرابط نشطًا (Active) بناءً على URL الحالي**
const isCurrent = (href) => {
    // نأخذ الجزء الأول من الرابط قبل علامة الاستفهام (لتجاهل أي متغيرات استعلام)
    const currentPath = page.url.split('?')[0];

    // إذا كان الرابط هو المسار الجذري "/", يجب أن يتطابق تمامًا.
    if (href === '/') {
        return currentPath === '/'; // ✅ سيصبح نشطًا فقط إذا كان المسار هو "/"
    }

    // للمسارات الأخرى، نستخدم `startsWith`.
    return currentPath.startsWith(href);
};

// روابط خاصة بمدخل البيانات فقط
const dataEntryLinks = ref([
    {
        icon: "line-md:account",
        name: "قائمة المرضى",
        to: "/",
    },
    {
        name: "سجل العمليات ",
        icon: "line-md:document-report",
        to: "/OperationLog",
    },
    {
        name: "الاحصائيات",
        icon: "material-symbols-light:bar-chart-4-bars",

        to: "/Statistics",
    },

    { name: "طلبات التوريد",
        icon: "material-symbols-light:bar-chart-4-bars",

        to: "/SuRequests",
    },

    { name: "طلبات التوريدkk",
        icon: "material-symbols-light:bar-chart-4-bars",

        to: "/medicationsList",
    },
    { name: "الاقسام ",
        icon: "material-symbols-light:bar-chart-4-bars",

        to: "/Departments",
    },
     { name: "طلبات توريد الصادرة ",
        icon: "material-symbols-light:bar-chart-4-bars",

        to: "/a",
    },
     { name: "طلبات النقل ",
        icon: "material-symbols-light:bar-chart-4-bars",

        to: "/b",
    },
     { name: "الشكاوي ",
        icon: "material-symbols-light:bar-chart-4-bars",

        to: "/c",
    },
     { name: "سجل العمليات قي النظام ",
        icon: "material-symbols-light:bar-chart-4-bars",

        to: "/d",
    },
]);
</script>

<style scoped>
.custom-scrollbar-hide {
    -ms-overflow-style: none;
}

.custom-scrollbar-hide::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}

.custom-scrollbar-hide {
    scrollbar-width: none;
}
</style>
