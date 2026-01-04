<template>
    <aside
        :class="[
            'bg-[#2E5077] text-white rounded-l-3xl h-full flex flex-col justify-start flex-shrink-0 transition-all duration-300 shadow-xl',
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
                <li v-for="(link, index) in currentLinks" :key="index">
                    <Link
                        role="menuitem"
                        :href="link.to"
                        :class="[
                            'flex items-center w-full text-right py-3 rounded-lg transition-all duration-300 group',
                            'hover:bg-white/15',
                            isCollapsed ? 'justify-center px-0' : 'px-3',
                            isCurrent(link.to)
                                ? 'bg-[#7093bb] shadow-md text-white'
                                : '', 
                        ]"
                    >
                        <Icon
                            :icon="link.icon"
                            :class="[
                                'w-6 h-6 text-[#ffffff] flex-shrink-0 transition-margin duration-300',
                                isCollapsed ? 'mx-auto' : '',
                                'group-hover:text-[#1cab8c]',
                                isCurrent(link.to) ? 'text-white' : '', 
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
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import { Link, usePage } from "@inertiajs/vue3"; 

const isCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
const page = usePage(); 
const userRole = ref('');

onMounted(() => {
    // Read user role from localStorage
    userRole.value = localStorage.getItem('user_role') || '';
});

const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('sidebar_collapsed', isCollapsed.value);
};

const isCurrent = (href) => {
    const currentPath = page.url.split('?')[0];
    if (href === '/') {
        return currentPath === '/'; 
    }
    return currentPath.startsWith(href);
};

// Define links for each role
const links = {
     super_admin: [
        { name: "الاحصائيات", icon: "material-symbols-light:bar-chart-4-bars", to: "/superAdmin/statistics" },
        { name: "قائمة المرضى", icon: "line-md:account", to: "/superAdmin/patients" },
        { name: "قائمة الأدوية", icon: "healthicons:medicines-outline", to: "/superAdmin/medications" },
        { name: "المدراء و الموردين", icon: "clarity:employee-group-solid", to: "/superAdmin/employees" },
         { name: "الموظفين", icon: "clarity:employee-group-solid", to: "/superAdmin/AllemployeesList" },
        { name: "سجل العمليات", icon: "line-md:document-report", to: "/superAdmin/operations" },
        { name: "سجل العمليات الشامل", icon: "mdi:home-report", to: "/superAdmin/all-operations" },
        { name: "طلبات التوريد الداخلي", icon: "carbon:request-quote", to: "/superAdmin/requests" },
        { name: "المستشفيات", icon: "fa:hospital-o", to: "/superAdmin/hospital" },
        { name: "شركات التوريد", icon: "fa:hospital-o", to: "/superAdmin/Supply" },
    ],


    hospital_admin: [
        { name: "الاحصائيات", icon: "material-symbols-light:bar-chart-4-bars", to: "/admin/statistics" },
        { name: "قائمة المرضى", icon: "line-md:account", to: "/admin/patients" },
         { name: "قائمة الأدوية", icon: "healthicons:medicines-outline", to: "/admin/medications" },
        { name: "سجل العمليات", icon: "line-md:document-report", to: "/admin/operations" },
        { name: "سجل العمليات الشامل", icon: "mdi:home-report", to: "/admin/all-operations" },
        { name: "الموظفين", icon: "clarity:employee-group-solid", to: "/admin/employees" },
        { name: "طلبات التوريد الداخلي", icon: "carbon:request-quote", to: "/admin/requests" },
        { name: "الاقسام", icon: "mingcute:department-fill", to: "/admin/departments" },
    
        { name: "طلبات النقل", icon: "mdi:transfer", to: "/admin/transfer-requests" },
        { name: "الشكاوي", icon: "fluent:person-feedback-24-regular", to: "/admin/complaints" },
    ],
    pharmacist: [
        { name: "الاحصائيات", icon: "material-symbols-light:bar-chart-4-bars", to: "/pharmacist/statistics" },
        { name: "قائمة الأدوية", icon: "healthicons:medicines-outline", to: "/pharmacist/medications" },
        { name: "سجل العمليات", icon: "line-md:document-report", to: "/pharmacist/operations" },
        { name: "قائمة المرضى", icon: "line-md:account", to: "/pharmacist/patients" },
        { name: "طلبات التوريد الداخلي", icon: "carbon:request-quote", to: "/pharmacist/requests" },
    ],
    doctor: [
        { name: "الاحصائيات", icon: "material-symbols-light:bar-chart-4-bars", to: "/doctor/statistics" },
        { name: "قائمة المرضى", icon: "line-md:account", to: "/doctor/patients" },
        { name: "سجل العمليات", icon: "line-md:document-report", to: "/doctor/operations" },
    ],
    data_entry: [
        { name: "الاحصائيات", icon: "material-symbols-light:bar-chart-4-bars", to: "/data-entry/statistics" },
        { name: "قائمة المرضى", icon: "line-md:account", to: "/data-entry/patients" },
        { name: "سجل العمليات", icon: "line-md:document-report", to: "/data-entry/operations" },
    ],
    department_head: [
        { name: "الاحصائيات", icon: "material-symbols-light:bar-chart-4-bars", to: "/department/statistics" },
        { name: "قائمة المرضى", icon: "line-md:account", to: "/department/patients" },
        { name: "سجل العمليات", icon: "line-md:document-report", to: "/department/operations" },
        { name: "طلبات التوريد الداخلي", icon: "carbon:request-quote", to: "/department/requests" },
    ],
    warehouse_manager: [
        { name: "الاحصائيات", icon: "material-symbols-light:bar-chart-4-bars", to: "/storekeeper/statistics" },
        { name: "قائمة الأدوية", icon: "healthicons:medicines-outline", to: "/storekeeper/medications" },
        { name: "سجل العمليات", icon: "line-md:document-report", to: "/storekeeper/operations" },
        { name: "طلبات التوريد الداخلي", icon: "carbon:request-quote", to: "/storekeeper/requests" },
        { name: "طلبات التوريد", icon: "icon-park-outline:buy", to: "/storekeeper/supply-requests" },
    ],
    supplier_admin:[
       { name: "الاحصائيات", icon: "material-symbols-light:bar-chart-4-bars", to: "/Supplier/statistics" },
        { name: "قائمة الأدوية", icon: "healthicons:medicines-outline", to: "/Supplier/medications" },
        { name: "سجل العمليات", icon: "line-md:document-report", to: "/Supplier/operations" },
        { name: "طلبات التوريد الداخلي", icon: "carbon:request-quote", to: "/Supplier/requests" },
        { name: "طلبات التوريد الخارجية", icon: "icon-park-outline:buy", to: "/Supplier/supply-requests" },
     
    ]
    
};

const currentLinks = computed(() => {
    return links[userRole.value] || [];
});

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
