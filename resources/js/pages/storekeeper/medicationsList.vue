<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import DrugPreviewModal from "@/components/forpharmacist/DrugPreviewModal.vue";
import SupplyRequestModal from "@/components/forpharmacist/SupplyRequestModal.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. تهيئة axios مع base URL الخاص بمدير المخزن
// ----------------------------------------------------
const api = axios.create({
  baseURL: "/api/storekeeper",
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

// إضافة interceptor لمعالجة الأخطاء (بدون إظهار تنبيهات تلقائية)
api.interceptors.response.use(
  (response) => response,
  (error) => {
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
// ----------------------------------------------------
// 4. حالة التحميل والأخطاء
// ----------------------------------------------------
const isLoading = ref(true);
const error = ref(null);
const hasData = ref(false); // لتحديد ما إذا كان هناك بيانات أم لا

// ----------------------------------------------------
// 5. منطق البحث والفرز والفلترة
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("quantity");
const sortOrder = ref("asc");
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);

// دالة تحويل التاريخ من صيغة (yyyy/mm/dd) إلى كائن Date للمقارنة
const parseDate = (dateString) => {
  if (!dateString) return null;
  try {
    // محاولة تحويل الصيغة Y/m/d إلى Date
    if (dateString.includes('/')) {
      const parts = dateString.split('/');
      if (parts.length === 3) {
        return new Date(parts[0], parts[1] - 1, parts[2]);
      }
    }
    const date = new Date(dateString);
    return isNaN(date.getTime()) ? null : date;
  } catch {
    return null;
  }
};

// دالة لمسح فلتر التاريخ
const clearDateFilter = () => {
  dateFrom.value = "";
  dateTo.value = "";
};

const sortDrugs = (key, order) => {
  sortKey.value = key;
  sortOrder.value = order;
};

const filteredDrugss = computed(() => {
  if (!drugsData.value.length) return [];

  let list = [...drugsData.value];
  const search = searchTerm.value ? searchTerm.value.toLowerCase().trim() : '';

  // 1. تطبيق البحث
  if (search) {
    list = list.filter(drug => {
      const drugName = (drug.drugName || drug.name || "").toLowerCase();
      const genericName = (drug.genericName || "").toLowerCase();
      const strength = (drug.strength || "").toLowerCase();
      const category = (drug.category || "").toLowerCase();
      
      return drugName.includes(search) ||
             genericName.includes(search) ||
             strength.includes(search) ||
             category.includes(search);
    });
  }

  // 2. فلترة حسب تاريخ انتهاء الصلاحية
  if (dateFrom.value || dateTo.value) {
    list = list.filter((drug) => {
      const expiryDate = drug.expiryDate;
      if (!expiryDate) return false;

      const expiryDateObj = parseDate(expiryDate);
      if (!expiryDateObj) return false;

      expiryDateObj.setHours(0, 0, 0, 0); // إزالة الوقت للمقارنة

      let matchesFrom = true;
      let matchesTo = true;

      if (dateFrom.value) {
        const fromDate = new Date(dateFrom.value);
        fromDate.setHours(0, 0, 0, 0);
        matchesFrom = expiryDateObj >= fromDate;
      }

      if (dateTo.value) {
        const toDate = new Date(dateTo.value);
        toDate.setHours(23, 59, 59, 999); // نهاية اليوم
        matchesTo = expiryDateObj <= toDate;
      }

      return matchesFrom && matchesTo;
    });
  }

  // 3. الفرز
  if (sortKey.value) {
    list.sort((a, b) => {
      let comparison = 0;

      if (sortKey.value === "drugName") {
        comparison = (a.drugName || a.name || "").localeCompare(b.drugName || b.name || "", "ar");
      } else if (sortKey.value === "quantity") {
        comparison = (a.quantity || 0) - (b.quantity || 0);
      } else if (sortKey.value === "category") {
        comparison = (a.category || "").localeCompare(b.category || "", "ar");
      } else if (sortKey.value === "status") {
        comparison = (a.status || "").localeCompare(b.status || "", "ar");
      } else if (sortKey.value === "expiryDate") {
        const dateA = parseDate(a.expiryDate);
        const dateB = parseDate(b.expiryDate);
        comparison = (dateA?.getTime() || 0) - (dateB?.getTime() || 0);
      }

      return sortOrder.value === "asc" ? comparison : -comparison;
    });
  }

  return list;
});

// ----------------------------------------------------
// 6. وظائف API
// ----------------------------------------------------

// جلب جميع الأدوية من مخزون المستودع
// جلب جميع الأدوية من مخزون المستودع
const fetchDrugs = async () => {
  isLoading.value = true;
  error.value = null;

  try {
    // GET /api/storekeeper/drugs  -> WarehouseInventoryController@index
    const response = await api.get("/drugs");
    const data = response.data?.data ?? response.data;

    drugsData.value = Array.isArray(data) ? data : [];
    hasData.value = drugsData.value.length > 0;

  } catch (err) {
    console.warn("Warning: Could not fetch drugs data from API", err);
    error.value = err.response?.data?.message || err.message || "حدث خطأ أثناء تحميل البيانات";
    drugsData.value = [];
    hasData.value = false;
  } finally {
    isLoading.value = false;
  }
};

// جلب الفئات (تصنيفات الأدوية)
const fetchCategories = async () => {
  try {
    // GET /api/storekeeper/categories  -> CategoryStoreKeeperController@index
    const response = await api.get("/categories");
    const data = response.data?.data ?? response.data;
    categories.value = Array.isArray(data) ? data : [];
  } catch (error) {
    console.warn("Warning: Could not fetch categories from API");
    categories.value = [];
  }
};

// جلب جميع بيانات الأدوية للبحث
const fetchAllDrugsData = async () => {
  try {
    // GET /api/storekeeper/drugs/all  -> WarehouseInventoryController@allDrugs
    const response = await api.get("/drugs/all");
    const data = response.data?.data ?? response.data;
    const drugs = Array.isArray(data) ? data : [];
    
    // التأكد من أن كل دواء له id
    allDrugsData.value = drugs.map(drug => ({
      id: drug.id,
      drugId: drug.id,
      name: drug.drugName || drug.name,
      drugName: drug.drugName || drug.name,
      genericName: drug.genericName,
      strength: drug.strength,
      form: drug.form,
      category: drug.category,
      categoryId: drug.category,
      unit: drug.unit,
      maxMonthlyDose: drug.maxMonthlyDose,
      status: drug.status,
      manufacturer: drug.manufacturer,
      country: drug.country,
      utilizationType: drug.utilizationType,
      type: drug.form || 'Tablet'
    }));
    
    console.log('All Drugs Data loaded:', allDrugsData.value.length, 'drugs');
  } catch (error) {
    console.error("Error fetching all drugs data from API:", error);
    allDrugsData.value = [];
  }
};

// تحديث دواء
const updateDrug = async (drugId, updatedData) => {
  try {
    // PUT /api/storekeeper/drugs/{id}  -> تحديث سجل المخزون
    await api.put(`/drugs/${drugId}`, updatedData);

    // بعد التحديث، نعيد تحميل القائمة لضمان تزامن البيانات
    await fetchDrugs();

    showSuccessAlert(" تم تحديث بيانات الدواء بنجاح");
  } catch (error) {
    showErrorAlert(" فشل في تحديث بيانات الدواء");
    throw error;
  }
};

// حذف دواء
const deleteDrug = async (drugId) => {
  try {
    // DELETE /api/storekeeper/drugs/{id}
    await api.delete(`/drugs/${drugId}`);

    // إعادة تحميل القائمة بعد الحذف
    await fetchDrugs();

    showSuccessAlert(" تم حذف الدواء بنجاح");
  } catch (error) {
    showErrorAlert(" فشل في حذف الدواء");
    throw error;
  }
};

// إضافة دواء جديد
const addDrug = async (newDrug) => {
  try {
    // POST /api/storekeeper/drugs
    await api.post("/drugs", newDrug);

    // إعادة تحميل الجدول لعرض الدواء المضاف
    await fetchDrugs();
    hasData.value = drugsData.value.length > 0;

    showSuccessAlert(" تم إضافة الدواء الجديد بنجاح");
  } catch (error) {
    showErrorAlert(" فشل في إضافة الدواء");
    throw error;
  }
};

// إرسال طلب توريد
const submitSupplyRequest = async (requestData) => {
  try {
    console.log('Request Data:', requestData); // للتصحيح
    
    // تجهيز الحمولة لتتوافق مع ExternalSupplyRequestController@store
    const payload = {
      items: (requestData.items || []).map(item => ({
        drug_id: item.drugId || item.id,
        requested_qty: item.quantity
      })),
      notes: requestData.notes || null
    };

    console.log('Payload:', payload); // للتصحيح

    // POST /api/storekeeper/supply-requests
    const response = await api.post("/supply-requests", payload);
    
    console.log('Response:', response); // للتصحيح

    // Laravel Resources wrap collections in a 'data' property
    const responseData = response.data?.data ?? response.data;
    const requestNumber = responseData?.requestNumber || 'N/A';

    showSuccessAlert(` تم إرسال طلب التوريد رقم ${requestNumber} بنجاح`);
    
    // تحديث كميات الأدوية بعد الطلب
    await fetchDrugs();
    
    return response.data;
  } catch (error) {
    console.error("Error submitting supply request:", error);
    console.error("Error response:", error.response?.data);
    
    // عرض رسالة الخطأ التفصيلية
    let errorMessage = 'حدث خطأ غير متوقع';
    if (error.response?.data) {
      if (error.response.data.error) {
        errorMessage = error.response.data.error;
      } else if (error.response.data.message) {
        errorMessage = error.response.data.message;
      } else if (error.response.data.errors) {
        // معالجة أخطاء التحقق
        const errors = Object.values(error.response.data.errors).flat();
        errorMessage = errors.join(', ');
      }
    } else if (error.message) {
      errorMessage = error.message;
    }
    
    showErrorAlert(` فشل في إرسال طلب التوريد: ${errorMessage}`);
    throw error;
  }
};

// ----------------------------------------------------
// 7. دالة تحديد لون الصف والخط
// ----------------------------------------------------
const getRowColorClass = (quantity, neededQuantity, isUnregistered) => {
  // إذا كان الدواء غير مسجل، نعرضه بخلفية زرقاء فاتحة
  if (isUnregistered) {
    return "bg-blue-50/70 border-r-4 border-blue-400";
  }
  
  
  const qty = Number(quantity);
  const neededQty = Number(neededQuantity);
  
  if (isNaN(qty) || isNaN(neededQty) || neededQty <= 0) {
    return "bg-white border-gray-300 border";
  }
  
  const dangerThreshold = neededQty * 0.5; 
  const warningThreshold = neededQty * 0.75;  

  if (qty < dangerThreshold) {
    return " bg-red-50/70 border-r-4 border-red-500 ";
  } else if (qty < warningThreshold) {
    return "bg-yellow-50/70 border-r-4 border-yellow-500";
  } else {
    return "bg-white border-gray-300 border";
  }
};

const getTextColorClass = (quantity, neededQuantity, isUnregistered) => {
  // إذا كان الدواء غير مسجل، نعرضه بلون أزرق
  if (isUnregistered) {
    return "text-blue-700 font-semibold";
  }
  
  // التحقق من أن القيم موجودة (وليس فقط falsy) - 0 هو قيمة صالحة
  const qty = Number(quantity);
  const neededQty = Number(neededQuantity);
  
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
const isDrugDetailsLoading = ref(false);
const showDrugDetails = async (drug) => {
  isDrugDetailsLoading.value = true;
  isDrugPreviewModalOpen.value = true;
  
  try {
    // جلب تفاصيل الدواء الكاملة من قاعدة البيانات
    const response = await api.get(`/drugs/${drug.id}`);
    const data = response.data?.data ?? response.data;
    selectedDrug.value = data;
  } catch (error) {
    console.error("Error fetching drug details:", error);
    // في حالة الخطأ، نستخدم البيانات المتوفرة من القائمة
    selectedDrug.value = drug;
    showErrorAlert("فشل في تحميل تفاصيل الدواء الكاملة");
  } finally {
    isDrugDetailsLoading.value = false;
  }
};

const openSupplyRequestModal = () => {
  isSupplyRequestModalOpen.value = true;
};

const closeSupplyRequestModal = () => {
  isSupplyRequestModalOpen.value = false;
};

const closeDrugPreviewModal = () => {
  isDrugPreviewModalOpen.value = false;
  selectedDrug.value = {};
  isDrugDetailsLoading.value = false;
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

 <th>اسم الدواء</th>
 <th>الاسم العلمي</th>
 <th>التركيز</th>
 <th>الفئة</th>
 
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

 <td>${drug.drugName || drug.name || ''}</td>
 <td>${drug.genericName || '-'}</td>
 <td>${drug.strength || '-'}</td>

 <td>${drug.category || '-'}</td>


 <td>${drug.quantity || 0}</td>
 <td>${drug.neededQuantity || 0}</td>
 <td>${drug.expiryDate || '-'}</td>
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
      showSuccessAlert(" تم تجهيز التقرير بنجاح للطباعة.");
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
                        <div class="flex items-center gap-3 w-full sm:max-w-xl flex-wrap">
    
                                <search v-model="searchTerm" placeholder="ابحث بالاسم، الاسم العلمي، التركيز أو الفئة" />
                          

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
                                        حسب الفئة:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('category', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'category' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            الفئة (أ - ي)
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('category', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'category' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            الفئة (ي - أ)
                                        </a>
                                    </li>

                                    <li
                                        class="menu-title text-gray-700 font-bold text-sm mt-2"
                                    >
                                        حسب الحالة:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('status', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'status' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            الحالة (أ - ي)
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('status', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'status' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            الحالة (ي - أ)
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
                                    class="table w-full text-right min-w-[1400px] border-collapse"
                                >
                                    <thead
                                        class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                    >
                                        <tr>
                                          
                                            <th class="name-col">اسم الدواء</th>
                                            <th class="generic-name-col">الاسم العلمي</th>
                                            <th class="strength-col">التركيز</th>
                                            <th class="quantity-col">الكمية المتوفرة</th>
                                            <th class="needed-quantity-col">الكمية المحتاجة</th>
                                            <th class="expiry-date-col">تاريخ إنتهاء الصلاحية</th>
                                            <th class="actions-col">الإجراءات</th>
                                        </tr>
                                    </thead>

                                    <tbody class="text-gray-800">
                                        <tr v-if="isLoading">
                                            <td colspan="7" class="p-4">
                                                <TableSkeleton :rows="5" />
                                            </td>
                                        </tr>
                                        <tr v-else-if="error">
                                            <td colspan="7" class="py-12">
                                                <ErrorState :message="error" :retry="retryLoading" />
                                            </td>
                                        </tr>
                                        <template v-else>
                                            <tr
                                                v-for="(drug, index) in filteredDrugss"
                                                :key="drug.id || index"
                                                :class="[
                                                    'hover:bg-gray-100',
                                                    getRowColorClass(
                                                        drug.quantity,
                                                        drug.neededQuantity,
                                                        drug.isUnregistered
                                                    ),
                                                ]"
                                            >
                                               
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity,
                                                            drug.isUnregistered
                                                        )
                                                    "
                                                >
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ drug.drugName || drug.name }}</span>
                                                        <span v-if="drug.isUnregistered" 
                                                            class="px-2 py-0.5 text-xs font-bold bg-blue-200 text-blue-800 rounded-full"
                                                            title="دواء غير مسجل في المستودع ولكن مطلوب في طلبات التوريد">
                                                            غير مسجل
                                                        </span>
                                                    </div>
                                                </td>
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity,
                                                            drug.isUnregistered
                                                        )
                                                    "
                                                >
                                                    {{ drug.genericName || '-' }}
                                                </td>
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity,
                                                            drug.isUnregistered
                                                        )
                                                    "
                                                >
                                                    {{ drug.strength || '-' }}
                                                </td>
                                              
                                              
                                              
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity,
                                                            drug.isUnregistered
                                                        )
                                                    "
                                                >
                                                    <span class="font-bold">{{
                                                        drug.quantity || 0
                                                    }}</span>
                                                </td>
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity,
                                                            drug.isUnregistered
                                                        )
                                                    "
                                                >
                                                    <span class="font-bold">{{
                                                        drug.neededQuantity || 0
                                                    }}</span>
                                                    <span v-if="drug.isUnregistered" class="text-xs text-blue-600 block mt-1">
                                                        (من الطلبات)
                                                    </span>
                                                </td>
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity,
                                                            drug.isUnregistered
                                                        )
                                                    "
                                                >
                                                    {{ drug.expiryDate || '-' }}
                                                </td>
                                                <td class="actions-col">
                                                    <div
                                                        class="flex gap-3 justify-center"
                                                    >
                                                        <button class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                            @click="
                                                                showDrugDetails(drug)
                                                            "
                                                        >
                                                            <Icon
                                                                icon="tabler:eye-minus"
                                                                :class="[
                                                                    'w-4 h-4 cursor-pointer hover:scale-110 transition-transform text-green-600',
                                                                ]"
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="filteredDrugss.length === 0">
                                                <td colspan="7" class="py-12">
                                                    <EmptyState message="لا توجد أدوية في المستودع حالياً" />
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                
                                <!-- -رسالة عند عدم وجود بيلانات -->

                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <Sidebar />

        <DrugPreviewModal 
            :is-open="isDrugPreviewModalOpen"
            :drug="selectedDrug"
            :is-loading="isDrugDetailsLoading"
            @close="closeDrugPreviewModal"
            @update-drug="updateDrug"
            @delete-drug="deleteDrug"
        />

        <SupplyRequestModal
            :is-open="isSupplyRequestModalOpen"
            :categories="categories"
            :all-drugs-data="allDrugsData"
            :drugs-data="drugsData"
            @close="closeSupplyRequestModal"
            @confirm="handleSupplyConfirm"
            @show-alert="showSuccessAlert"
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
    width: 70px;
    min-width: 70px;
    max-width: 70px;
    text-align: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.name-col {
    width: 80px;
    min-width: 80px;
}
.generic-name-col {
    width: 120px;
    min-width: 120px;
}
.strength-col {
    width: 100px;
    min-width: 100px;
}

.quantity-col {
    width: 70px; 
    min-width: 70px;
}
.needed-quantity-col {
    width: 70px; 
    min-width: 70px;
}
.expiry-date-col {
    width: 80px;
    min-width: 80px;
}
.min-w-\[1400px\] {
    min-width: 100px;
}
</style>