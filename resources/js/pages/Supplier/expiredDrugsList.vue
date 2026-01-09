<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. تهيئة axios مع base URL الخاص بالمورد
// ----------------------------------------------------
const api = axios.create({
  baseURL: "/api/supplier",
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json"
  }
});

// إضافة interceptor لإضافة التوكن تلقائياً
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("token") || localStorage.getItem("auth_token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// إضافة interceptor لمعالجة الأخطاء
api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error("API Error:", error.response?.data || error.message);
    return Promise.reject(error);
  }
);

// ----------------------------------------------------
// 2. بيانات الأدوية المُصفرة
// ----------------------------------------------------
const expiredDrugs = ref([]);
const searchTerm = ref("");
const sortKey = ref("zeroedDate");
const sortOrder = ref("desc");
const isLoading = ref(true);
const hasData = ref(false);
const error = ref(null);

// ----------------------------------------------------
// 3. دالة البحث والفرز
// ----------------------------------------------------
const sortDrugs = (key, order) => {
  sortKey.value = key;
  sortOrder.value = order;
};

const filteredDrugs = computed(() => {
  if (!expiredDrugs.value.length) return [];

  let list = [...expiredDrugs.value];

  // البحث
  if (searchTerm.value) {
    const search = searchTerm.value.toLowerCase().trim();
    list = list.filter(drug => {
      const drugName = (drug.drugName || '').toLowerCase();
      const strength = (drug.strength || '').toString().toLowerCase();
      const expiryDate = (drug.expiryDate || '').toString();
      const zeroedDate = (drug.zeroedDate || '').toString();
      const quantity = (drug.quantity || 0).toString();
      
      return drugName.includes(search) ||
             strength.includes(search) ||
             expiryDate.includes(search) ||
             zeroedDate.includes(search) ||
             quantity.includes(search);
    });
  }

  // الفرز
  if (sortKey.value) {
    list.sort((a, b) => {
      let comparison = 0;

      if (sortKey.value === "drugName") {
        comparison = (a.drugName || "").localeCompare(b.drugName || "", "ar");
      } else if (sortKey.value === "strength") {
        comparison = (a.strength || "").localeCompare(b.strength || "", "ar");
      } else if (sortKey.value === "quantity") {
        comparison = (a.quantity || 0) - (b.quantity || 0);
      } else if (sortKey.value === "expiryDate") {
        const dateA = a.expiryDate ? new Date(a.expiryDate.replace(/\//g, "-")) : new Date();
        const dateB = b.expiryDate ? new Date(b.expiryDate.replace(/\//g, "-")) : new Date();
        comparison = dateA.getTime() - dateB.getTime();
      } else if (sortKey.value === "zeroedDate") {
        const dateA = a.zeroedDate ? new Date(a.zeroedDate.replace(/\//g, "-")) : new Date();
        const dateB = b.zeroedDate ? new Date(b.zeroedDate.replace(/\//g, "-")) : new Date();
        comparison = dateA.getTime() - dateB.getTime();
      }

      return sortOrder.value === "asc" ? comparison : -comparison;
    });
  }

  return list;
});

// ----------------------------------------------------
// 4. وظائف API
// ----------------------------------------------------
const fetchExpiredDrugs = async () => {
  isLoading.value = true;
  error.value = null;

  try {
    const response = await api.get("/drugs/expired");
    // BaseApiController يُرجع البيانات بداخل data
    const data = response.data?.data ?? response.data;

    expiredDrugs.value = Array.isArray(data) ? data : [];
    hasData.value = expiredDrugs.value.length > 0;
  } catch (err) {
    console.error("Error fetching expired drugs:", err);
    expiredDrugs.value = [];
    hasData.value = false;
    error.value = err.response?.data?.message || "تعذر تحميل قائمة الأدوية المُصفرة.";
  } finally {
    isLoading.value = false;
  }
};

// ----------------------------------------------------
// 5. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
  const resultsCount = filteredDrugs.value.length;

  const printWindow = window.open("", "_blank", "height=600,width=800");

  if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
    showErrorAlert(" فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
    return;
  }

  let tableHtml = `
<style>
body { font-family: 'Arial', sans-serif; direction: rtl; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: right; }
th { background-color: #f2f2f2; font-weight: bold; }
h1 { text-align: center; color: #dc2626; margin-bottom: 10px; }
.results-info { text-align: right; margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #dc2626; }
.no-data { text-align: center; padding: 40px; color: #666; font-style: italic; }
</style>

<h1>الأدوية التي انتهت صلاحيتها </h1>
`;

  if (resultsCount > 0) {
    tableHtml += `
<p class="results-info">عدد النتائج : ${resultsCount}</p>

<table>
<thead>
 <tr>
 <th>اسم الدواء</th>
 <th>التركيز</th>
 <th>الكمية المنتهية</th>
 <th>تاريخ انتهاء الصلاحية</th>
 <th>تاريخ المعالجة</th>
 </tr>
</thead>
<tbody>
`;

    filteredDrugs.value.forEach((drug) => {
      tableHtml += `
<tr>
 <td>${drug.drugName || ''}</td>
 <td>${drug.strength || '-'}</td>
 <td>${drug.quantity || 0}</td>
 <td>${drug.expiryDate || '-'}</td>
 <td>${drug.zeroedDate || '-'}</td>
</tr>
`;
    });

    tableHtml += `
</tbody>
</table>
`;
  } else {
    tableHtml += `
<div class="no-data">
  <p>لا توجد أدوية منهية حالياً</p>
</div>
`;
  }

  printWindow.document.write("<html><head><title>طباعة الأدوية المنتهية</title>");
  printWindow.document.write("</head><body>");
  printWindow.document.write(tableHtml);
  printWindow.document.write("</body></html>");
  printWindow.document.close();

  printWindow.onload = () => {
    printWindow.focus();
    printWindow.print();
    if (resultsCount > 0) {
      showSuccessAlert(" تم تجهيز التقرير بنجاح للطباعة.");
    }
  };
};

// زر إعادة التحميل
const retryLoading = async () => {
  await fetchExpiredDrugs();
};

// ----------------------------------------------------
// 6. نظام التنبيهات (Toast System)
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
// 7. تهيئة البيانات عند تحميل المكون
// ----------------------------------------------------
onMounted(async () => {
  await fetchExpiredDrugs();
});
</script>

<template>
    <div class="drawer lg:drawer-open" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />

            <main class="flex-1 p-4 sm:p-5 pt-3">
                <!-- المحتوى الرئيسي -->
                <div>
                    <div
                        class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0"
                    >
                        <div class="flex items-center gap-3 w-full sm:max-w-xl">
                            <search v-model="searchTerm" placeholder="ابحث في الأدوية المُصفرة (اسم الدواء، الكمية، التاريخ...)" />
                           
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
                                    <li class="menu-title text-gray-700 font-bold text-sm">
                                        حسب اسم الدواء:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('drugName', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'drugName' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            الاسم (أ - ي)
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('drugName', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'drugName' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            الاسم (ي - أ)
                                        </a>
                                    </li>

                                    <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                        حسب التركيز:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('strength', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'strength' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            التركيز (أ - ي)
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('strength', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'strength' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            التركيز (ي - أ)
                                        </a>
                                    </li>

                                    <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                        حسب الكمية:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('quantity', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'quantity' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            الأقل كمية أولاً
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('quantity', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'quantity' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            الأعلى كمية أولاً
                                        </a>
                                    </li>

                                    <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                        حسب تاريخ الانتهاء:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('expiryDate', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'expiryDate' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            الأقرب إنتهاءً
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('expiryDate', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'expiryDate' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            الأبعد إنتهاءً
                                        </a>
                                    </li>

                                    <li class="menu-title text-gray-700 font-bold text-sm mt-2">
                                        حسب تاريخ التصفير:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('zeroedDate', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'zeroedDate' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            الأقدم أولاً
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('zeroedDate', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'zeroedDate' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            الأحدث أولاً
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <p
                                class="text-sm font-semibold text-gray-600 self-end sm:self-center"
                            >
                                عدد النتائج :
                                <span class="text-[#4DA1A9] text-lg font-bold">{{
                                    filteredDrugs.length
                                }}</span>
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-5 w-full sm:w-auto justify-end"
                        >
                            <button
                                class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                                @click="router.visit('/Supplier/medications')"
                            >
                                <Icon icon="solar:arrow-right-bold" class="w-5 h-5 ml-2" />
                                العودة للأدوية
                            </button>
                            <btnprint @click="printTable" />
                        </div>
                    </div>

                    <!-- العنوان -->
                    <div class="mb-4 bg-red-50 rounded-2xl shadow border-2 border-red-200 p-4">
                        <div class="flex items-center gap-3">
                            <Icon icon="solar:danger-triangle-bold" class="w-8 h-8 text-red-600" />
                            <div>
                                <h2 class="text-xl font-bold text-red-800">الأدوية التي انتهت صلاحيتها </h2>
                              
                            </div>
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
                                    class="table w-full text-right min-w-[100px] border-collapse"
                                >
                                    <thead
                                        class="bg-red-100 text-red-900 sticky top-0 z-10 border-b border-red-300"
                                    >
                                        <tr>
                                            <th class="px-4 py-3">اسم الدواء</th>
                                            <th class="px-4 py-3">التركيز</th>
                                            <th class="px-4 py-3">الكمية المنتهية</th>
                                            <th class="px-4 py-3">تاريخ انتهاء الصلاحية</th>
                                            <th class="px-4 py-3">تاريخ المعالجة</th>
                                        </tr>
                                    </thead>

                                    <tbody class="text-gray-800">
                                        <tr v-if="isLoading">
                                            <td colspan="5" class="p-4">
                                                <TableSkeleton :rows="10" />
                                            </td>
                                        </tr>
                                        <tr v-else-if="error">
                                            <td colspan="5" class="py-12">
                                                <ErrorState :message="error" :retry="retryLoading" />
                                            </td>
                                        </tr>
                                        <template v-else>
                                            <tr
                                                v-for="(drug, index) in filteredDrugs"
                                                :key="index"
                                                class="hover:bg-red-50 border-b border-red-100"
                                            >
                                                <td class="px-4 py-3 font-semibold text-red-900">
                                                    {{ drug.drugName }}
                                                </td>
                                                <td class="px-4 py-3 text-red-700">
                                                    {{ drug.strength || '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-red-700 font-bold">
                                                    {{ drug.quantity }}
                                                </td>
                                                <td class="px-4 py-3 text-red-700">
                                                    {{ drug.expiryDate || '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-red-700">
                                                    {{ drug.zeroedDate || '-' }}
                                                </td>
                                            </tr>
                                            <tr v-if="filteredDrugs.length === 0">
                                                <td colspan="5" class="py-12">
                                                    <EmptyState message="لا توجد أدوية مُصفرة لعرضها" />
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
        </div>

        <Sidebar />

        <Toast
            :show="isAlertVisible"
            :message="alertMessage"
            :type="alertType"
            @close="isAlertVisible = false"
        />
    </div>
</template>

<style>
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background-color: #dc2626;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background-color: #b91c1c;
}
</style>

