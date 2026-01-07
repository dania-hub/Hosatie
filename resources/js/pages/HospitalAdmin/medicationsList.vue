<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import DrugPreviewModal from "@/components/forpharmacist/DrugPreviewModal.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. تهيئة axios مع base URL
// ----------------------------------------------------
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
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// إضافة interceptor لمعالجة الأخطاء
api.interceptors.response.use(
  response => response,
  error => {
    console.error("API Error:", error.response?.data || error.message);
    return Promise.reject(error);
  }
);

// ----------------------------------------------------
// 2. بيانات الأدوية (سيتم تحميلها من API)
// ----------------------------------------------------
const drugsData = ref([]);
const categories = ref([]);
const allDrugsData = ref([]);

// ----------------------------------------------------
// 3. حالة المكونات المنبثقة
// ----------------------------------------------------
const isDrugPreviewModalOpen = ref(false);
const isSupplyRequestModalOpen = ref(false);
const selectedDrug = ref({});

// ----------------------------------------------------
// 4. حالة التحميل والأخطاء
// ----------------------------------------------------
const isLoading = ref(true);
const error = ref(null);
const hasData = ref(false); // لتحديد ما إذا كان هناك بيانات أم لا

// ----------------------------------------------------
// 5. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);
const sortKey = ref("quantity");
const sortOrder = ref("asc");

const sortDrugs = (key, order) => {
  sortKey.value = key;
  sortOrder.value = order;
};

// دالة تحويل التاريخ من صيغة مختلفة إلى Date
const parseDate = (dateString) => {
  if (!dateString) return null;
  // محاولة تحويل من YYYY/MM/DD أو YYYY-MM-DD
  const normalizedDate = dateString.replace(/\//g, "-");
  const date = new Date(normalizedDate);
  return isNaN(date.getTime()) ? null : date;
};

// دالة لمسح فلتر التاريخ
const clearDateFilter = () => {
  dateFrom.value = "";
  dateTo.value = "";
};

const filteredDrugss = computed(() => {
  if (!drugsData.value.length) return [];

  // 1. التصفية حسب البحث - البحث في جميع الحقول
  let list = drugsData.value;
  if (searchTerm.value) {
    const search = searchTerm.value.toLowerCase();
    list = list.filter((drug) => {
      // إنشاء قائمة بجميع الحقول الممكنة للبحث
      const searchFields = [
        drug.drugCode?.toString(),
        drug.drugName,
        drug.genericName,
        drug.quantity?.toString(),
        drug.neededQuantity?.toString(),
        drug.expiryDate,
        // البحث في الحقول الإضافية إن وجدت
        drug.category,
        drug.manufacturer,
        drug.batchNumber,
      ];

      // البحث في جميع الحقول
      return searchFields.some(
        (field) => field && field.toString().toLowerCase().includes(search)
      );
    });
  }

  // 2. فلترة حسب التاريخ
  if (dateFrom.value || dateTo.value) {
    list = list.filter((drug) => {
      if (!drug.expiryDate) return false;

      const drugDate = parseDate(drug.expiryDate);
      if (!drugDate) return false;

      drugDate.setHours(0, 0, 0, 0); // إزالة الوقت للمقارنة

      let matchesFrom = true;
      let matchesTo = true;

      if (dateFrom.value) {
        const fromDate = new Date(dateFrom.value);
        fromDate.setHours(0, 0, 0, 0);
        matchesFrom = drugDate >= fromDate;
      }

      if (dateTo.value) {
        const toDate = new Date(dateTo.value);
        toDate.setHours(23, 59, 59, 999); // نهاية اليوم
        matchesTo = drugDate <= toDate;
      }

      return matchesFrom && matchesTo;
    });
  }

  // 3. الفرز
  if (sortKey.value) {
    list.sort((a, b) => {
      let comparison = 0;

      if (sortKey.value === "drugName") {
        comparison = (a.drugName || "").localeCompare(b.drugName || "", "ar");
      } else if (sortKey.value === "quantity") {
        comparison = (a.quantity || 0) - (b.quantity || 0);
      } else if (sortKey.value === "expiryDate") {
        const dateA = parseDate(a.expiryDate);
        const dateB = parseDate(b.expiryDate);
        const timeA = dateA ? dateA.getTime() : 0;
        const timeB = dateB ? dateB.getTime() : 0;
        comparison = timeA - timeB;
      }

      return sortOrder.value === "asc" ? comparison : -comparison;
    });
  }

  return list;
});

// ----------------------------------------------------
// 6. وظائف API
// ----------------------------------------------------

// جلب جميع الأدوية
const fetchDrugs = async () => {
  isLoading.value = true;
  error.value = null;
  
  try {
    const response = await api.get("/admin-hospital/drugs");
    
    // التحقق من بنية الاستجابة
    let data = [];
    if (response.data) {
      if (Array.isArray(response.data)) {
        data = response.data;
      } else if (response.data.data && Array.isArray(response.data.data)) {
        data = response.data.data;
      } else if (response.data.success && Array.isArray(response.data.data)) {
        data = response.data.data;
      }
    }
    
    drugsData.value = data;
    hasData.value = data.length > 0;
  } catch (err) {
    console.error("Error fetching drugs:", err);
    error.value = err.response?.data?.message || err.message || "فشل تحميل قائمة الأدوية";
    drugsData.value = [];
    hasData.value = false;
  } finally {
    isLoading.value = false;
  }
};

// جلب الفئات
const fetchCategories = async () => {
  try {
    const response = await api.get("/admin-hospital/categories");
    
    // التحقق من بنية الاستجابة
    let data = [];
    if (response.data) {
      if (Array.isArray(response.data)) {
        data = response.data;
      } else if (response.data.data && Array.isArray(response.data.data)) {
        data = response.data.data;
      } else if (response.data.success && Array.isArray(response.data.data)) {
        data = response.data.data;
      }
    }
    
    categories.value = data;
  } catch (error) {
    console.error("Error fetching categories:", error);
    console.warn("Warning: Could not fetch categories from API");
    categories.value = [];
  }
};

// جلب جميع بيانات الأدوية للبحث
const fetchAllDrugsData = async () => {
  try {
    const response = await api.get("/admin-hospital/drugs/all");
    
    // التحقق من بنية الاستجابة
    let data = [];
    if (response.data) {
      if (Array.isArray(response.data)) {
        data = response.data;
      } else if (response.data.data && Array.isArray(response.data.data)) {
        data = response.data.data;
      } else if (response.data.success && Array.isArray(response.data.data)) {
        data = response.data.data;
      }
    }
    
    allDrugsData.value = data;
  } catch (error) {
    console.error("Error fetching all drugs:", error);
    console.warn("Warning: Could not fetch all drugs data from API");
    allDrugsData.value = [];
  }
};



// ----------------------------------------------------
// 7. دالة تحديد لون الصف والخط
// ----------------------------------------------------
const getRowColorClass = (quantity, neededQuantity) => {
  // إرجاع class ثابت بدون تغيير لون الخلفية
  return "bg-white hover:bg-gray-50 border-gray-300 border";
};

const getTextColorClass = (quantity, neededQuantity) => {
  // تحويل القيم إلى أرقام والتأكد من وجودها
  const qty = Number(quantity);
  const neededQty = Number(neededQuantity);
  
  // إذا كانت القيم غير صالحة أو غير موجودة
  if (isNaN(qty) || isNaN(neededQty) || neededQty <= 0) {
    return "text-gray-800";
  }
  
  const dangerThreshold = neededQty * 0.5;
  const warningThreshold = neededQty * 0.75;

  if (qty < dangerThreshold) {
    return "text-red-700 font-semibold";
  } else if (qty < warningThreshold) {
    return "text-yellow-700 font-semibold";
  } else {
    return "text-gray-800";
  }
};

// ----------------------------------------------------
// 8. وظائف العرض
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

const handleSupplyConfirm = async (requestData) => {
  try {
    await submitSupplyRequest(requestData);
    closeSupplyRequestModal();
  } catch (error) {
    console.error("Error handling supply confirm:", error);
  }
};

// ----------------------------------------------------
// 9. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
  const resultsCount = filteredDrugss.value.length;

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
h1 { text-align: center; color: #2E5077; margin-bottom: 10px; }
.results-info { text-align: right; margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #4DA1A9; }
.no-data { text-align: center; padding: 40px; color: #666; font-style: italic; }
</style>

<h1>قائمة الأدوية (تقرير طباعة)</h1>
`;

  if (resultsCount > 0) {
    tableHtml += `
<p class="results-info">عدد النتائج التي ظهرت (عدد الصفوف): ${resultsCount}</p>

<table>
<thead>
 <tr>
 <th>رمز الدواء</th>
 <th>اسم الدواء</th>
 <th>الاسم العلمي</th>
 <th>الكمية المتوفرة</th>
 <th>الكمية المحتاجة</th>
 <th>تاريخ إنتهاء الصلاحية</th>
 </tr>
</thead>
<tbody>
`;

    filteredDrugss.value.forEach((drug) => {
      tableHtml += `
<tr>
 <td>${drug.drugCode || ''}</td>
 <td>${drug.drugName || ''}</td>
 <td>${drug.genericName || 'غير محدد'}</td>
 <td>${drug.quantity || 0}</td>
 <td>${drug.neededQuantity || 0}</td>
 <td>${drug.expiryDate || ''}</td>
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
  <p>لا توجد أدوية في المخزون حالياً</p>
</div>
`;
  }

  printWindow.document.write("<html><head><title>طباعة قائمة الأدوية</title>");
  printWindow.document.write("</head><body>");
  printWindow.document.write(tableHtml);
  printWindow.document.write("</body></html>");
  printWindow.document.close();

  printWindow.onload = () => {
    printWindow.focus();
    printWindow.print();
    if (resultsCount > 0) {
      showSuccessAlert("✅ تم تجهيز التقرير بنجاح للطباعة.");
    }
  };
};

// زر إعادة التحميل
const retryLoading = async () => {
  await Promise.all([
    fetchDrugs(),
    fetchCategories(),
    fetchAllDrugsData()
  ]);
};

// ----------------------------------------------------
// 10. نظام التنبيهات المطور (Toast System)
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
// 11. تهيئة البيانات عند تحميل المكون
// ----------------------------------------------------
onMounted(async () => {
  await Promise.all([
    fetchDrugs(),
    fetchCategories(),
    fetchAllDrugsData()
  ]);
});
</script>

<template>
    <div class="drawer lg:drawer-open h-screen overflow-hidden" dir="rtl">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" checked />

        <div class="drawer-content flex flex-col bg-gray-50 h-full overflow-hidden">
            <Navbar class="flex-shrink-0" />

            <main class="flex-1 p-4 sm:p-5 pt-3 overflow-y-auto">
            <!-- حالة التحميل -->
           

            <!-- حالة الخطأ -->

                <!-- المحتوى الرئيسي -->
                <div>
                    <div
                        class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0"
                    >
                        <div class="flex items-center gap-3 w-full sm:max-w-xl ">
                            
                                <search v-model="searchTerm" placeholder="ابحث برمز الدواء، الاسم، الكمية أو تاريخ الانتهاء" />
                           

                            <!-- زر إظهار/إخفاء فلتر التاريخ -->
                            <button
                                @click="showDateFilter = !showDateFilter"
                                class="h-11 w-11 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
                                :title="showDateFilter ? 'إخفاء فلتر التاريخ' : 'إظهار فلتر التاريخ'"
                            >
                                <Icon
                                    icon="solar:calendar-bold"
                                    class="w-5 h-5"
                                />
                            </button>

                            <!-- فلتر التاريخ -->
                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div v-if="showDateFilter" class="flex items-center gap-2">
                                    <div class="relative">
                                        <input
                                            type="date"
                                            v-model="dateFrom"
                                            class="h-11 px-3 pr-10 border-2 border-[#ffffff8d] rounded-[30px] bg-white text-gray-700 focus:outline-none focus:border-[#4DA1A9] text-sm cursor-pointer"
                                            placeholder="من تاريخ"
                                        />
                                        <Icon
                                            icon="solar:calendar-linear"
                                            class="w-5 h-5 text-[#4DA1A9] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                                        />
                                    </div>
                                    <span class="text-gray-600 font-medium">إلى</span>
                                    <div class="relative">
                                        <input
                                            type="date"
                                            v-model="dateTo"
                                            class="h-11 px-3 pr-10 border-2 border-[#ffffff8d] rounded-[30px] bg-white text-gray-700 focus:outline-none focus:border-[#4DA1A9] text-sm cursor-pointer"
                                            placeholder="إلى تاريخ"
                                        />
                                        <Icon
                                            icon="solar:calendar-linear"
                                            class="w-5 h-5 text-[#4DA1A9] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                                        />
                                    </div>
                                    <button
                                        v-if="dateFrom || dateTo"
                                        @click="clearDateFilter"
                                        class="h-11 px-3 border-2 border-red-300 rounded-[30px] bg-red-50 text-red-600 hover:bg-red-100 transition-colors flex items-center gap-1"
                                        title="مسح فلتر التاريخ"
                                    >
                                        <Icon icon="solar:close-circle-bold" class="w-4 h-4" />
                                        مسح
                                    </button>
                                </div>
                            </Transition>

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

                                    <li
                                        class="menu-title text-gray-700 font-bold text-sm mt-2"
                                    >
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
                                            الأقل كمية أولاً (تنبيه)
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

                                    <li
                                        class="menu-title text-gray-700 font-bold text-sm mt-2"
                                    >
                                        حسب تاريخ الإنتهاء:
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
                                </ul>
                            </div>
                            <p
                                class="text-sm font-semibold text-gray-600 self-end sm:self-center"
                            >
                                عدد النتائج :
                                <span class="text-[#4DA1A9] text-lg font-bold">{{
                                    filteredDrugss.length
                                }}</span>
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-5 w-full sm:w-auto justify-end"
                        >
                           
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
                                    class="table w-full text-right min-w-[700px] border-collapse"
                                >
                                    <thead
                                        class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                    >
                                        <tr>
                                            <th class="drug-code-col">
                                                رمز الدواء
                                            </th>
                                            <th class="drug-name-col">
                                                اسم الدواء
                                            </th>
                                            <th class="scientific-name-col">
                                                الاسم العلمي
                                            </th>
                                            <th class="quantity-col" colspan="2">
                                                الكمية المتوفرة
                                            </th>
                                            <th class="needed-quantity-col" colspan="2">
                                                الكمية المحتاجة
                                            </th>
                                            <th class="expiry-date-col">
                                                تاريخ إنتهاء الصلاحية
                                            </th>
                                            <th class="actions-col">الإجراءات</th>
                                        </tr>
                                        <tr>
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                            <th class="quantity-col bg-[#8abcc0]">
                                                الصيدلية
                                            </th>
                                            <th class="quantity-col bg-[#8abcc0]">
                                                المستودع
                                            </th>
                                            <th class="needed-quantity-col bg-[#8abcc0]">
                                                الصيدلية
                                            </th>
                                            <th class="needed-quantity-col bg-[#8abcc0]">
                                                المستودع
                                            </th>
                                            <th></th>
                                            <th></th>
                                        </tr>
                                    </thead>

                                    <tbody class="text-gray-800">
                                        <tr v-if="isLoading">
                                            <td colspan="9" class="p-4">
                                                <TableSkeleton :rows="5" />
                                            </td>
                                        </tr>
                                        <tr v-else-if="error">
                                            <td colspan="9" class="py-12">
                                                <ErrorState :message="error" :retry="fetchDrugs" />
                                            </td>
                                        </tr>
                                        <template v-else>
                                            <tr
                                                v-for="(drug, index) in filteredDrugss"
                                                :key="drug.id || index"
                                                class="border-b border-gray-200 bg-white hover:bg-gray-50"
                                            >
                                                <td :class="getTextColorClass(drug.quantity, drug.neededQuantity)">
                                                    {{ drug.drugCode }}
                                                </td>
                                                <td :class="getTextColorClass(drug.quantity, drug.neededQuantity)">
                                                    {{ drug.drugName }}
                                                </td>
                                                <td :class="getTextColorClass(drug.quantity, drug.neededQuantity)">
                                                    {{ drug.genericName || 'غير محدد' }}
                                                </td>
                                                <td :class="getTextColorClass(drug.pharmacyQuantity || 0, drug.pharmacyNeededQuantity || 0)">
                                                    <span class="font-bold">{{ drug.pharmacyQuantity || 0 }}</span>
                                                </td>
                                                <td :class="getTextColorClass(drug.warehouseQuantity || 0, drug.warehouseNeededQuantity || 0)">
                                                    <span class="font-bold">{{ drug.warehouseQuantity || 0 }}</span>
                                                </td>
                                                <td :class="getTextColorClass(drug.pharmacyQuantity || 0, drug.pharmacyNeededQuantity || 0)">
                                                    <span class="font-bold">{{ drug.pharmacyNeededQuantity || 0 }}</span>
                                                </td>
                                                <td :class="getTextColorClass(drug.warehouseQuantity || 0, drug.warehouseNeededQuantity || 0)">
                                                    <span class="font-bold">{{ drug.warehouseNeededQuantity || 0 }}</span>
                                                </td>
                                                <td :class="getTextColorClass(drug.quantity, drug.neededQuantity)">
                                                    {{ drug.expiryDate }}
                                                </td>
                                                <td class="actions-col">
                                                    <div class="flex gap-3 justify-center">
                                                        <button
                                                            @click="showDrugDetails(drug)"
                                                            class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                            data-tip="عرض التفاصيل"
                                                        >
                                                            <Icon
                                                                icon="tabler:eye"
                                                                class="w-4 h-4 text-green-600 cursor-pointer hover:scale-110 transition-transform"
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="filteredDrugss.length === 0">
                                                <td colspan="9" class="py-12">
                                                    <EmptyState message="لا توجد أدوية في المخزون" />
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

        <div class="drawer-side h-full overflow-hidden">
            <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <Sidebar />
        </div>

        <DrugPreviewModal 
            :is-open="isDrugPreviewModalOpen"
            :drug="selectedDrug"
            @close="isDrugPreviewModalOpen = false"
            @update-drug="updateDrug"
            @delete-drug="deleteDrug"
        />

       

        <Toast
            :show="isAlertVisible"
            :message="alertMessage"
            :type="alertType"
            @close="isAlertVisible = false"
        />
    </div>
</template>

<style>
/* نفس الأنماط السابقة */
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

.actions-col {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
    text-align: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
.drug-code-col {
    width: 120px;
    min-width: 120px;
}
.quantity-col {
    width: 120px; 
    min-width: 120px;
}
.needed-quantity-col {
    width: 150px; 
    min-width: 150px;
}
.expiry-date-col {
    width: 150px;
    min-width: 150px;
}
.drug-name-col {
    width: auto;
    min-width: 170px;
}
.scientific-name-col {
    width: auto;
    min-width: 150px;
}
.min-w-\[700px\] {
    min-width: 700px;
}
</style>