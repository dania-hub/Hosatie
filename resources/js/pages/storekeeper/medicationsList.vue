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
// 5. منطق البحث والفرز
// ----------------------------------------------------
const searchTerm = ref("");
const sortKey = ref("quantity");
const sortOrder = ref("asc");

const sortDrugs = (key, order) => {
  sortKey.value = key;
  sortOrder.value = order;
};

const filteredDrugss = computed(() => {
  if (!drugsData.value.length) return [];

  // هنا يتم فقط الفرز على البيانات القادمة من الخادم
  let list = [...drugsData.value];

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
        const dateA = a.expiryDate ? new Date(a.expiryDate.replace(/\//g, "-")) : new Date();
        const dateB = b.expiryDate ? new Date(b.expiryDate.replace(/\//g, "-")) : new Date();
        comparison = dateA.getTime() - dateB.getTime();
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

    showSuccessAlert("✅ تم تحديث بيانات الدواء بنجاح");
  } catch (error) {
    showErrorAlert("❌ فشل في تحديث بيانات الدواء");
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

    showSuccessAlert("✅ تم حذف الدواء بنجاح");
  } catch (error) {
    showErrorAlert("❌ فشل في حذف الدواء");
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

    showSuccessAlert("✅ تم إضافة الدواء الجديد بنجاح");
  } catch (error) {
    showErrorAlert("❌ فشل في إضافة الدواء");
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

    showSuccessAlert(`✅ تم إرسال طلب التوريد رقم ${requestNumber} بنجاح`);
    
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
    
    showErrorAlert(`❌ فشل في إرسال طلب التوريد: ${errorMessage}`);
    throw error;
  }
};

// ----------------------------------------------------
// 7. دالة تحديد لون الصف والخط
// ----------------------------------------------------
const getRowColorClass = (quantity, neededQuantity) => {
  if (!quantity || !neededQuantity) return "bg-white border-gray-300 border";
  
  const dangerThreshold = neededQuantity * 0.25; 
  const warningThreshold = neededQuantity * 0.5;  

  if (quantity < dangerThreshold) {
    return " bg-red-50/70 border-r-4 border-red-500 ";
  } else if (quantity < warningThreshold) {
    return "bg-yellow-50/70 border-r-4 border-yellow-500";
  } else {
    return "bg-white border-gray-300 border";
  }
};

const getTextColorClass = (quantity, neededQuantity) => {
  if (!quantity || !neededQuantity) return "text-gray-800";
  
  const dangerThreshold = neededQuantity * 0.25;
  const warningThreshold = neededQuantity * 0.5;

  if (quantity < dangerThreshold) {
    return "text-red-700 font-semibold";
  } else if (quantity < warningThreshold) {
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
    showErrorAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
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
// 10. نظام التنبيهات
// ----------------------------------------------------
const isSuccessAlertVisible = ref(false);
const isErrorAlertVisible = ref(false);
const successMessage = ref("");
const errorMessage = ref("");
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

const showErrorAlert = (message) => {
  if (alertTimeout) {
    clearTimeout(alertTimeout);
  }

  errorMessage.value = message;
  isErrorAlertVisible.value = true;

  alertTimeout = setTimeout(() => {
    isErrorAlertVisible.value = false;
    errorMessage.value = "";
  }, 4000);
};

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
                                                        drug.neededQuantity
                                                    ),
                                                ]"
                                            >
                                               
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity
                                                        )
                                                    "
                                                >
                                                    {{ drug.drugName || drug.name }}
                                                </td>
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity
                                                        )
                                                    "
                                                >
                                                    {{ drug.genericName || '-' }}
                                                </td>
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity
                                                        )
                                                    "
                                                >
                                                    {{ drug.strength || '-' }}
                                                </td>
                                              
                                              
                                              
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity
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
                                                            drug.neededQuantity
                                                        )
                                                    "
                                                >
                                                    <span class="font-bold">{{
                                                        drug.neededQuantity || 0
                                                    }}</span>
                                                </td>
                                                <td
                                                    :class="
                                                        getTextColorClass(
                                                            drug.quantity,
                                                            drug.neededQuantity
                                                        )
                                                    "
                                                >
                                                    {{ drug.expiryDate || '-' }}
                                                </td>
                                                <td class="actions-col">
                                                    <div
                                                        class="flex gap-3 justify-center"
                                                    >
                                                        <button
                                                            @click="
                                                                showDrugDetails(drug)
                                                            "
                                                        >
                                                            <Icon
                                                                icon="tabler:eye-minus"
                                                                :class="[
                                                                    'w-5 h-5 cursor-pointer hover:scale-110 transition-transform text-green-700',
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
            @close="isDrugPreviewModalOpen = false"
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

        <!-- تنبيه النجاح -->
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

        <!-- تنبيه الخطأ -->
        <Transition
            enter-active-class="transition duration-300 ease-out transform"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in transform"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div
                v-if="isErrorAlertVisible"
                class="fixed top-4 right-55 z-[1000] p-4 text-right bg-red-500 text-white rounded-lg shadow-xl max-w-xs transition-all duration-300"
                dir="rtl"
            >
                {{ errorMessage }}
            </div>
        </Transition>
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