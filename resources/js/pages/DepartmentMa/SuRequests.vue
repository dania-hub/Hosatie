<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";

// ----------------------------------------------------
// 1. بيانات الشحنات/الطلبات (تم حذف "requestedBy")
// ----------------------------------------------------
const shipmentsData = ref([
    {
        shipmentNumber: "S-509",
        requestDate: "2025/12/03",
        requestStatus: "قيد المراجعة",
        previewed: true, // معاينة: (أيقونة خضراء)
        received: false, // الاستلام: (أيقونة حمراء)
    },
    {
        shipmentNumber: "S-508",
        requestDate: "2025/11/16",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
    {
        shipmentNumber: "S-507",
        requestDate: "2025/11/03",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
    {
        shipmentNumber: "S-506",
        requestDate: "2025/10/28",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
    {
        shipmentNumber: "S-505",
        requestDate: "2025/10/07",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
    {
        shipmentNumber: "S-504",
        requestDate: "2025/09/28",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
    {
        shipmentNumber: "S-503",
        requestDate: "2025/09/03",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
    {
        shipmentNumber: "S-502",
        requestDate: "2025/08/28",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
    {
        shipmentNumber: "S-501",
        requestDate: "2025/07/29",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
    {
        shipmentNumber: "S-500",
        requestDate: "2025/7/10",
        requestStatus: "تم الإستلام",
        previewed: true,
        received: true,
    },
]);

// ----------------------------------------------------
// 2. حالة المكونات المنبثقة
// ----------------------------------------------------
const isDrugPreviewModalOpen = ref(false);
const isSupplyRequestModalOpen = ref(false);
const selectedDrug = ref({});

// ----------------------------------------------------
// 3. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("requestDate"); // مفتاح الفرز الافتراضي: تاريخ الطلب
const sortOrder = ref("desc"); // ترتيب الفرز الافتراضي: تنازلي (الأحدث أولاً)

const sortShipments = (key, order) => {
    sortKey.value = key;
    sortOrder.value = order;
};

const filteredShipments = computed(() => {
    // 1. التصفية
    let list = shipmentsData.value;
    if (searchTerm.value) {
        const search = searchTerm.value.toLowerCase();
        list = list.filter(
            (shipment) =>
                shipment.shipmentNumber.toLowerCase().includes(search) ||
                shipment.requestStatus.includes(search)
        );
    }

    // 2. الفرز
    if (sortKey.value) {
        list.sort((a, b) => {
            let comparison = 0;

            if (sortKey.value === "shipmentNumber") {
                // الفرز حسب رقم الشحنة (أبجدياً/عددياً)
                comparison = a.shipmentNumber.localeCompare(
                    b.shipmentNumber
                );
            } else if (sortKey.value === "requestDate") {
                // الفرز حسب تاريخ الطلب
                const dateA = new Date(a.requestDate.replace(/\//g, "-"));
                const dateB = new Date(b.requestDate.replace(/\//g, "-"));
                comparison = dateA.getTime() - dateB.getTime();
            } else if (sortKey.value === "requestStatus") {
                // الفرز حسب حالة الطلب (أبجدياً)
                comparison = a.requestStatus.localeCompare(
                    b.requestStatus,
                    "ar"
                );
            }

            return sortOrder.value === "asc" ? comparison : -comparison;
        });
    }

    return list;
});

// ----------------------------------------------------
// 4. وظائف الأيقونات (تم الإبقاء عليها ولكنها لن تستخدم في الجدول الجديد)
// ----------------------------------------------------
const getIcon = (isTrue) => {
    return isTrue ? "tabler:circle-check" : "tabler:circle-x";
};

const getIconColor = (isTrue) => {
    return isTrue ? "text-green-600" : "text-red-500";
};

// ----------------------------------------------------
// 5. وظائف العرض والتحكم بالإجراءات
// ----------------------------------------------------
const showDrugDetails = (drug) => {
    selectedDrug.value = drug;
    isDrugPreviewModalOpen.value = true;
};

const openSupplyRequestModal = () => {
    isSupplyRequestModalOpen.value = true;
};

const closeSupplyRequestModal = () => {
    isSupplyRequestModalOpen.value = false;
};

const handleSupplyConfirm = (dosageList) => {
    showSuccessAlert(`✅ تم تأكيد إسناد ${dosageList.length} دواء بنجاح!`);
    console.log("تم تأكيد إضافة الجرعات اليومية:", dosageList);
};


// ----------------------------------------------------
// 6. منطق الطباعة (تم تحديث جدول الطباعة)
// ----------------------------------------------------
const printTable = () => {
    const resultsCount = filteredShipments.value.length;

    const printWindow = window.open("", "_blank", "height=600,width=800");

    if (
        !printWindow ||
        printWindow.closed ||
        typeof printWindow.closed === "undefined"
    ) {
        showSuccessAlert(
            "❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع."
        );
        return;
    }

    let tableHtml = `
<style>
body { font-family: 'Arial', sans-serif; direction: rtl; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
th { background-color: #f2f2f2; font-weight: bold; }
h1 { text-align: center; color: #2E5077; margin-bottom: 10px; }
.results-info { text-align: right; margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #4DA1A9; }
.center-icon { text-align: center; }
</style>

<h1>قائمة طلبات التوريد (تقرير طباعة)</h1>

<p class="results-info">عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}</p>

<table>
<thead>
    <tr>
    <th>رقم الشحنة</th>
    <th>تاريخ الطلب</th>
    <th>حالة الطلب</th>
    <th class="center-icon">الإستلام</th> </tr>
</thead>
<tbody>
`;

    filteredShipments.value.forEach((shipment) => {
        const receivedIcon = shipment.received ? '✅' : '❌';
        // تم حذف معاينة
        tableHtml += `
<tr>
    <td>${shipment.shipmentNumber}</td>
    <td>${shipment.requestDate}</td>
    <td>${shipment.requestStatus}</td>
    <td class="center-icon">${receivedIcon}</td>
</tr>
`;
    });

    tableHtml += `
</tbody>
</table>
`;

    printWindow.document.write(
        "<html><head><title>طباعة قائمة طلبات التوريد</title>"
    );
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
// 7. نظام التنبيهات
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
</script>

<template>
    <div class="drawer lg:drawer-open" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />

            <main class="flex-1 p-4 sm:p-5 pt-3">
                <div
                    class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0"
                >
                    <div class="flex items-center gap-3 w-full sm:max-w-xl">
                        <div class="relative w-full sm:max-w-sm">
                            <search v-model="searchTerm" />
                        </div>

                        <div class="dropdown dropdown-start">
                            <div
                                tabindex="0"
                                role="button"
                                class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                            >
                                <Icon
                                    icon="lucide:arrow-down-up"
                                    class="w-5 h-5 ml-2"
                                />
                                فرز
                            </div>
                            <ul
                                tabindex="0"
                                class="dropdown-content z-[50] menu p-2 shadow-lg bg-white border-2 hover:border hover:border-[#a8a8a8] border-[#ffffff8d] rounded-[35px] w-52 text-right"
                            >
                                <li
                                    class="menu-title text-gray-700 font-bold text-sm"
                                >
                                    حسب رقم الشحنة:
                                </li>
                                <li>
                                    <a
                                        @click="sortShipments('shipmentNumber', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'shipmentNumber' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الأصغر أولاً
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortShipments('shipmentNumber', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'shipmentNumber' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الأكبر أولاً
                                    </a>
                                </li>

                                <li
                                    class="menu-title text-gray-700 font-bold text-sm mt-2"
                                >
                                    حسب تاريخ الطلب:
                                </li>
                                <li>
                                    <a
                                        @click="sortShipments('requestDate', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'requestDate' &&
                                                sortOrder === 'asc',
                                        }"
                                    >
                                        الأقدم أولاً
                                    </a>
                                </li>
                                <li>
                                    <a
                                        @click="sortShipments('requestDate', 'desc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'requestDate' &&
                                                sortOrder === 'desc',
                                        }"
                                    >
                                        الأحدث أولاً
                                    </a>
                                </li>
                                <li
                                    class="menu-title text-gray-700 font-bold text-sm mt-2"
                                >
                                    حسب حالة الطلب:
                                </li>
                                <li>
                                    <a
                                        @click="sortShipments('requestStatus', 'asc')"
                                        :class="{
                                            'font-bold text-[#4DA1A9]':
                                                sortKey === 'requestStatus',
                                        }"
                                    >
                                        حسب الأبجدية
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <p
                            class="text-sm font-semibold text-gray-600 self-end sm:self-center"
                        >
                            عدد النتائج :
                            <span class="text-[#4DA1A9] text-lg font-bold">{{
                                filteredShipments.length
                            }}</span>
                        </p>
                    </div>

                    <div
                        class="flex items-center gap-5 w-full sm:w-auto justify-end"
                    >
                        <button
                            class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-29 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                            @click="openSupplyRequestModal"
                        >
                            طلب التوريد
                        </button>
                        <btnprint @click="printTable" />
                    </div>
                </div>

                <div
                    class="bg-white rounded-2xl shadow h-107 overflow-hidden flex flex-col"
                >
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
                                class="table w-full text-right min-w-[600px] border-collapse"
                            >
                                <thead
                                    class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                >
                                    <tr>
                                        <th class="shipment-number-col">
                                            رقم الشحنة
                                        </th>
                                        <th class="request-date-col">
                                            تاريخ طلب
                                        </th>
                                        <th class="status-col">حالة الطلب</th>
                                        <th class="actions-col text-center">
                                            الإجراءات
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="text-gray-800">
                                    <tr
                                        v-for="(shipment, index) in filteredShipments"
                                        :key="index"
                                        class="hover:bg-gray-100 bg-white border-b border-gray-200"
                                    >
                                        <td class="font-semibold text-gray-700">
                                            {{ shipment.shipmentNumber }}
                                        </td>
                                        <td>
                                            {{ shipment.requestDate }}
                                        </td>
                                        <td
                                            :class="{
                                                'text-red-600 font-semibold':
                                                    shipment.requestStatus ===
                                                    'قيد المراجعة',
                                                'text-green-600 font-semibold':
                                                    shipment.requestStatus ===
                                                    'تم الإستلام',
                                                'text-yellow-600 font-semibold':
                                                    shipment.requestStatus ===
                                                    'قيد التجهيز',
                                            }"
                                        >
                                            {{ shipment.requestStatus }}
                                        </td>
                                        <td class="actions-col">
                                            <div class="flex gap-3 justify-center">
                                                <button @click="showDrugDetails(shipment)">
                                                    <Icon
                                                        icon="famicons:open-outline"
                                                        class="w-5 h-5 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                        title="معاينة تفاصيل الشحنة"
                                                    />
                                                </button>
                                                <button v-if="!shipment.received" 
                                                    class="tooltip"
                                                    data-tip="تسجيل الإستلام"
                                                    @click="showSuccessAlert(`تم تأكيد استلام الشحنة رقم ${shipment.shipmentNumber}`)">
                                                    <Icon 
                                                        icon="tabler:truck-delivery" 
                                                        class="w-5 h-5 text-red-500 cursor-pointer hover:scale-110 transition-transform"
                                                    />
                                                </button>
                                                <button v-else 
                                                    class="tooltip"
                                                    data-tip="تم الإستلام">
                                                    <Icon 
                                                        icon="tabler:circle-check" 
                                                        class="w-5 h-5 text-green-600"
                                                    />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <Sidebar />

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
                class="fixed top-4 right-55 z-[1000] p-4 text-right bg-green-500 text-white rounded-lg shadow-xl max-w-xs transition-all duration-300"
                dir="rtl"
            >
                {{ successMessage }}
            </div>
        </Transition>
    </div>
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

/* تنسيقات عرض أعمدة الجدول الجديدة */
.shipment-number-col {
    width: 120px;
    min-width: 120px;
}
.request-date-col {
    width: 140px;
    min-width: 140px;
}
.status-col {
    width: 150px;
    min-width: 150px;
}
.actions-col {
    width: 150px; /* تم زيادة العرض قليلاً لتكون الأيقونات مرتاحة */
    min-width: 150px;
    text-align: center;
}
</style>