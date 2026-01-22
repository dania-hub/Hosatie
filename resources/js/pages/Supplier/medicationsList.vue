<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";

import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import Toast from "@/components/Shared/Toast.vue";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import DrugPreviewModal from "@/components/forpharmacist/DrugPreviewModal.vue";
import SupplyRequestModal from "@/components/forpharmacist/SupplyRequestModal.vue";
import RegistrationModal from "@/components/forSu/registration.vue";

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

// إضافة interceptor لإضافة التوكن تلقائيًا
api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  error => {
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
const categories = ref([]
);
const allDrugsData = ref([]);

// ----------------------------------------------------
const isRegistrationModalOpen = ref(false);
const openRegistrationModal = () => {
  isRegistrationModalOpen.value = true;
};

const closeRegistrationModal = () => {
  isRegistrationModalOpen.value = false;
};

const handleRegistrationConfirm = async (registrationData) => {
  try {
    // إرسال البيانات إلى API لتسجيل الاستلام
    const response = await api.post("/supplier/drugs/register", {
      items: registrationData.items.map(item => ({
        drugId: item.drugId,
        quantity: item.quantity,
        batch_number: item.batchNumber,
        expiry_date: item.expiryDate
      }))
    });
    
    // BaseApiController يُرجع البيانات بداخل data
    const responseData = response.data?.data ?? response.data;
    
    showSuccessAlert(` تم تسجيل الاستلام بنجاح (${responseData?.length || 0} دواء)`);
    
    // إغلاق النافذة
    closeRegistrationModal();
    
    // تحديث البيانات
    await fetchDrugs();
    
  } catch (error) {
    const errorMessage = error.response?.data?.message || 'فشل في تسجيل الاستلام';
    showErrorAlert(` ${errorMessage}`);
    console.error("Error handling registration:", error);
  }
};
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
// المنطق الخاص بتوسيع الصفوف (Expand Row)
// ----------------------------------------------------
const expandedReviewId = ref(null);
const toggleReview = (id) => {
    expandedReviewId.value = expandedReviewId.value === id ? null : id;
};

// ----------------------------------------------------

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

  // 1. التصفية
  let list = drugsData.value;
  if (searchTerm.value) {
    const search = searchTerm.value.toLowerCase();
    list = list.filter(
      (drug) =>
        (drug.drugName && drug.drugName.toLowerCase().includes(search)) ||
        (drug.genericName && drug.genericName.toLowerCase().includes(search)) ||
        (drug.strength && drug.strength.toLowerCase().includes(search))
    );
  }

  // 2. الفرز
  if (sortKey.value) {
    list.sort((a, b) => {
      let comparison = 0;

      if (sortKey.value === "drugName") {
        comparison = (a.drugName || "").localeCompare(b.drugName || "", "ar");
      } else if (sortKey.value === "quantity") {
        comparison = (a.quantity || 0) - (b.quantity || 0);
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

// جلب جميع الأدوية
const fetchDrugs = async () => {
  isLoading.value = true;
  error.value = null;
  
  try {
    const response = await api.get("/supplier/drugs");
    // BaseApiController يُرجع البيانات بداخل data
    const responseData = response.data?.data ?? response.data;
    drugsData.value = responseData || [];
    hasData.value = (responseData || []).length > 0;
    if (responseData && responseData.length > 0) {
      showSuccessAlert(" تم تحميل قائمة الأدوية بنجاح");
    }
  } catch (err) {
    console.warn("Warning: Could not fetch drugs data from API", err);
    error.value = "فشل في تحميل قائمة الأدوية. يرجى المحاولة مرة أخرى.";
    hasData.value = false;
  } finally {
    isLoading.value = false;
  }
};

// جلب الفئات
const fetchCategories = async () => {
  try {
    const response = await api.get("/supplier/categories");
    // BaseApiController يُرجع البيانات بداخل data
    const responseData = response.data?.data ?? response.data;
    categories.value = responseData || [];
  } catch (error) {
    console.warn("Warning: Could not fetch categories from API", error);
  }
};

// جلب جميع بيانات الأدوية للبحث
const fetchAllDrugsData = async () => {
  try {
    const response = await api.get("/supplier/drugs/all");
    // BaseApiController يُرجع البيانات بداخل data
    const responseData = response.data?.data ?? response.data;
    allDrugsData.value = responseData || [];
  } catch (error) {
    console.warn("Warning: Could not fetch all drugs data from API", error);
  }
};

// تحديث دواء
const updateDrug = async (drugId, updatedData) => {
  try {
    const response = await api.put(`/supplier/drugs/${drugId}`, updatedData);
    // BaseApiController يُرجع البيانات بداخل data
    const responseData = response.data?.data ?? response.data;
    const index = drugsData.value.findIndex(drug => drug.id === drugId);
    if (index !== -1) {
      drugsData.value[index] = { ...drugsData.value[index], ...responseData };
    }
    showSuccessAlert(" تم تحديث بيانات الدواء بنجاح");
    return responseData;
  } catch (error) {
    showErrorAlert(" فشل في تحديث بيانات الدواء");
    throw error;
  }
};

// حذف دواء
const deleteDrug = async (drugId) => {
  try {
    await api.delete(`/supplier/drugs/${drugId}`);
    drugsData.value = drugsData.value.filter(drug => drug.id !== drugId);
    hasData.value = drugsData.value.length > 0;
    showSuccessAlert(" تم حذف الدواء بنجاح");
  } catch (error) {
    showErrorAlert(" فشل في حذف الدواء");
    throw error;
  }
};

// إضافة دواء جديد
const addDrug = async (newDrug) => {
  try {
    const response = await api.post("/supplier/drugs", newDrug);
    // BaseApiController يُرجع البيانات بداخل data
    const responseData = response.data?.data ?? response.data;
    drugsData.value.push(responseData);
    hasData.value = true;
    showSuccessAlert(" تم إضافة الدواء الجديد بنجاح");
    return responseData;
  } catch (error) {
    showErrorAlert(" فشل في إضافة الدواء");
    throw error;
  }
};

// إرسال طلب توريد
const submitSupplyRequest = async (requestData) => {
  try {
    const response = await api.post("/supplier/supply-requests", requestData);
    // BaseApiController يُرجع البيانات بداخل data
    const responseData = response.data?.data ?? response.data;
    showSuccessAlert(" تم إرسال طلب التوريد بنجاح");
    
    // تحديث كميات الأدوية بعد الطلب
    await fetchDrugs();
    
    return responseData;
  } catch (error) {
    showErrorAlert(" فشل في إرسال طلب التوريد");
    throw error;
  }
};

// ----------------------------------------------------
// 7. دالة تحديد لون الصف والخط
// ----------------------------------------------------
const getRowColorClass = (quantity, neededQuantity, isUnregistered) => {
    if (isUnregistered) return 'bg-blue-50/70 border-r-4 border-blue-400';
    if (Number(quantity) <= 0) return 'bg-red-50/70 border-r-4 border-red-500';
    if (Number(quantity) < Number(neededQuantity)) return 'bg-orange-50/70 border-r-4 border-orange-400';
    return 'bg-white border-gray-100';
};

const getTextColorClass = (quantity, neededQuantity, isUnregistered) => {
    if (isUnregistered) return 'text-blue-600 font-bold';
    if (Number(quantity) <= 0) return 'text-red-600 font-bold';
    if (Number(quantity) < Number(neededQuantity)) return 'text-orange-600 font-bold text-lg';
    return '';
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
    const response = await api.get(`/supplier/drugs/${drug.id}`);
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

const closeDrugPreviewModal = () => {
  isDrugPreviewModalOpen.value = false;
  selectedDrug.value = {};
  isDrugDetailsLoading.value = false;
};

const openSupplyRequestModal = () => {
  isSupplyRequestModalOpen.value = true;
};

const closeSupplyRequestModal = () => {
  isSupplyRequestModalOpen.value = false;
};

const getQuantityDisplay = (drug) => {
    if (!drug) return '0';
    const unit = drug.unit || 'قرص';
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';
    const unitsPerBox = Number(drug.units_per_box || 1);
    const quantity = Number(drug.quantity || 0);

    if (unitsPerBox > 1) {
        const boxes = Math.floor(quantity / unitsPerBox);
        const remainder = quantity % unitsPerBox;
        
        if (boxes === 0 && quantity > 0) {
            return `${quantity} ${unit}`;
        }
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += `<br> و ${remainder} ${unit}`;
        }
        return display;
    } else {
        return `${quantity} ${unit}`;
    }
};

const getBatchQuantityDisplay = (batch, drug) => {
    if (!drug || !batch) return '0';
    const unit = drug.unit || 'قرص';
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';
    const unitsPerBox = Number(drug.units_per_box || 1);
    const quantity = Number(batch.quantity || 0);

    if (unitsPerBox > 1) {
        const boxes = Math.floor(quantity / unitsPerBox);
        const remainder = quantity % unitsPerBox;
        
        if (boxes === 0 && quantity > 0) {
            return `${quantity} ${unit}`;
        }
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += `<br> و ${remainder} ${unit}`;
        }
        return display;
    } else {
        return `${quantity} ${unit}`;
    }
};

const getNeededQuantityDisplay = (drug) => {
    if (!drug) return '0';
    const unit = drug.unit || 'قرص';
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';
    const unitsPerBox = Number(drug.units_per_box || 1);
    const neededQuantity = Number(drug.neededQuantity || 0);

    if (unitsPerBox > 1) {
        const boxes = Math.floor(neededQuantity / unitsPerBox);
        const remainder = neededQuantity % unitsPerBox;
        
        if (boxes === 0 && neededQuantity > 0) {
            return `${neededQuantity} ${unit}`;
        }
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += `<br> و ${remainder} ${unit}`;
        }
        return display;
    } else {
        return `${neededQuantity} ${unit}`;
    }
};

const handleSupplyConfirm = async (data) => {
  try {
    // جلب hospitals أولاً للحصول على hospital_id (نفس منطق SuRequests.vue)
    const hospitalsResponse = await api.get("/supplier/hospitals");
    const hospitalsData = hospitalsResponse.data?.data ?? hospitalsResponse.data ?? [];
    
    // استخدام أول مستشفى كافتراضي
    const hospitalId = hospitalsData.length > 0 ? hospitalsData[0].id : null;
    
    if (!hospitalId) {
        showErrorAlert(" لا توجد مستشفيات متاحة لإرسال الطلب إليها.");
        return;
    }
    
    const requestData = {
        hospital_id: hospitalId,
        items: data.items.map(item => ({
            drug_id: item.drugId || item.id || null,
            quantity: item.quantity
        })),
        notes: data.notes || '',
        priority: data.priority || 'normal'
    };
    
    const response = await api.post("/supplier/supply-requests", requestData);
    showSuccessAlert(" تم إرسال طلب التوريد بنجاح");
    
    closeSupplyRequestModal();
    
    // تحديث كميات الأدوية بعد الطلب
    await fetchDrugs();
    
  } catch (error) {
    const errorMessage = error.response?.data?.message || error.message || 'فشل في إرسال طلب التوريد';
    showErrorAlert(` ${errorMessage}`);
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

<h1>قائمة الأدوية </h1>
`;

  if (resultsCount > 0) {
    tableHtml += `
<p class="results-info">عدد النتائج : ${resultsCount}</p>

<table>
<thead>
 <tr>
 <th>اسم الدواء</th>
 <th>الاسم العلمي</th>
 <th>التركيز</th>
 <th>الكمية المتوفرة</th>
 <th>الكمية المحتاجة</th>
 </tr>
</thead>
<tbody>
`;

    filteredDrugss.value.forEach((drug) => {
      const quantityDisplay = getQuantityDisplay(drug);
      const neededQuantityDisplay = getNeededQuantityDisplay(drug);

      tableHtml += `
<tr>
 <td>${drug.drugName || ''}</td>
 <td>${drug.genericName || 'غير محدد'}</td>
 <td>${drug.strength || 'غير محدد'}</td>
 <td>${quantityDisplay}</td>
 <td>${neededQuantityDisplay}</td>
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
// 10. نظام التنبيهات
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
                <div >
                    <div
                        class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0"
                    >
                        <div class="flex items-center gap-3 w-full sm:max-w-xl">
                            <div class="relative w-full sm:max-w-xs">
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
                            class="flex items-center gap-1 w-full sm:w-auto justify-end"
                        >
                             <button
                                class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-auto rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-red-600 hover:border hover:border-[#a8a8a8] hover:bg-red-700"
                                @click="router.visit('/Supplier/expired-drugs')"
                                 title="الأدوية منتهية الصلاحية"
                            >
                                <Icon icon="solar:danger-triangle-bold" class="w-5 h-5 " />
                               
                            </button>
                         <button
                                class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-29 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                                 @click="openRegistrationModal"
                               >
                              تسجيل استلام
                            </button>
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
                                    class="table-fixed w-full text-right min-w-[1000px] border-collapse"
                                >
                                    <thead
                                        class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                    >
                                        <tr>
                                            <th class="drug-name-col px-4 py-3 text-sm font-bold">اسم الدواء</th>
                                            <th class="scientific-name-col px-4 py-3 text-sm font-bold">الاسم العلمي</th>
                                            <th class="strength-col px-4 py-3 text-sm font-bold text-center">التركيز</th>
                                            <th class="quantity-col px-4 py-3 text-sm font-bold text-center">الكمية المتوفرة</th>
                                            <th class="needed-quantity-col px-4 py-3 text-sm font-bold text-center">الكمية المحتاجة</th>
                                            <th class="actions-col px-4 py-3 text-sm font-bold text-center">الإجراءات</th>
                                        </tr>
                                    </thead>

                                    <tbody class="text-gray-800">
                                        <tr v-if="isLoading">
                                            <td colspan="6" class="p-4">
                                                <TableSkeleton :rows="10" />
                                            </td>
                                        </tr>
                                        <tr v-else-if="error">
                                            <td colspan="6" class="py-12">
                                                <ErrorState :message="error" :retry="fetchDrugs" />
                                            </td>
                                        </tr>
                                        <template v-else>
                                            <template
                                                v-for="(drug, index) in filteredDrugss"
                                                :key="drug.id || index"
                                            >
                                                <tr
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
                                                        class="drug-name-col px-4 py-3"
                                                        :class="
                                                            getTextColorClass(
                                                                drug.quantity,
                                                                drug.neededQuantity,
                                                                drug.isUnregistered
                                                            )
                                                        "
                                                    >
                                                        <div class="flex items-center gap-2">
                                                            <span class="truncate" :title="drug.drugName">{{ drug.drugName }}</span>
                                                            <span v-if="drug.isUnregistered" 
                                                                class="shrink-0 px-2 py-0.5 text-[10px] font-bold bg-blue-200 text-blue-800 rounded-full"
                                                                title="دواء غير مسجل في مخزون المورد ولكن مطلوب في الطلبات المستقبلة">
                                                                غير مسجل
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="scientific-name-col px-4 py-3"
                                                        :class="
                                                            getTextColorClass(
                                                                drug.quantity,
                                                                drug.neededQuantity,
                                                                drug.isUnregistered
                                                            )
                                                        "
                                                    >
                                                        <div class="truncate" :title="drug.genericName">{{ drug.genericName || 'غير محدد' }}</div>
                                                    </td>
                                                    <td
                                                        class="strength-col px-4 py-3 text-center"
                                                        :class="
                                                            getTextColorClass(
                                                                drug.quantity,
                                                                drug.neededQuantity,
                                                                drug.isUnregistered
                                                            )
                                                        "
                                                    >
                                                        {{ drug.strength || 'غير محدد' }}
                                                    </td>
                                                    <td
                                                        class="quantity-col px-4 py-3 text-center"
                                                        :class="
                                                            getTextColorClass(
                                                                drug.quantity,
                                                                drug.neededQuantity,
                                                                drug.isUnregistered
                                                            )
                                                        "
                                                        v-html="getQuantityDisplay(drug)"
                                                    >
                                                    </td>
                                                    <td
                                                        class="needed-quantity-col px-4 py-3 text-center"
                                                        :class="
                                                            getTextColorClass(
                                                                drug.quantity,
                                                                drug.neededQuantity,
                                                                drug.isUnregistered
                                                            )
                                                        "
                                                        v-html="getNeededQuantityDisplay(drug)"
                                                    >
                                                    </td>
                                                    <td class="actions-col">
                                                        <div
                                                            class="flex gap-3 justify-center"
                                                        >
                                                            <button
                                                                v-if="!drug.isUnregistered && drug.batches && drug.batches.length > 0"
                                                                class="p-2 rounded-lg hover:bg-slate-100 border border-slate-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                                :class="expandedReviewId === drug.id ? 'bg-slate-200 border-slate-300' : 'bg-slate-50'"
                                                                title="عرض التفاصيل والدفعات"
                                                                @click="toggleReview(drug.id)"
                                                            >
                                                                <Icon
                                                                    :icon="expandedReviewId === drug.id ? 'solar:alt-arrow-up-bold' : 'solar:alt-arrow-down-bold'"
                                                                    class="w-4 h-4 text-slate-600 transition-transform duration-300"
                                                                />
                                                            </button>
                                                            <button
                                                            class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                            title=" معاينة الدواء"
                                                                @click="
                                                                    showDrugDetails(drug)
                                                                "
                                                            >
                                                                <Icon
                                                                    icon="tabler:eye-minus"
                                                                     class="w-4 h-4 text-green-600"
                                                                />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <!-- Expanded Row for Batches -->
                                                <tr v-if="expandedReviewId === drug.id" class="bg-gray-50/50">
                                                    <td colspan="6" class="p-4 relative">
                                                        <div class="absolute right-8 top-0 w-0.5 h-full bg-[#4DA1A9]/20"></div>
                                                        <div class="pr-8">
                                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                                                <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                                                                    <h4 class="font-bold text-[#2E5077] text-sm flex items-center gap-2">
                                                                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                                                        تفاصيل الدفعات 
                                                                    </h4>
                                                                    <span class="text-xs font-bold bg-[#4DA1A9]/10 text-[#4DA1A9] px-2 py-1 rounded-lg">
                                                                        إجمالي الكمية: 
                                                                        <span class="block mt-1" v-html="getQuantityDisplay(drug)"></span>
                                                                    </span>
                                                                </div>
                                                                <table class="w-full text-right text-sm">
                                                                    <thead class="bg-gray-50/50 text-gray-500 font-medium">
                                                                        <tr>
                                                                            <th class="px-4 py-2 font-bold w-1/3">رقم الشحنة / الدفعة</th>
                                                                            <th class="px-4 py-2 font-bold w-1/3">تاريخ الإنتهاء</th>
                                                                            <th class="px-4 py-2 font-bold w-1/3">الكمية</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="divide-y divide-gray-50">
                                                                        <tr v-for="(batch, bIndex) in drug.batches" :key="bIndex" class="hover:bg-gray-50 transition-colors">
                                                                            <td class="px-4 py-2.5 text-gray-700 font-bold font-mono">{{ batch.batchNumber || '---' }}</td>
                                                                            <td class="px-4 py-2.5 text-gray-700 font-mono tracking-wide" dir="ltr" style="text-align: right;">{{ batch.expiryDate }}</td>
                                                                            <td class="px-4 py-2.5 font-bold text-[#4DA1A9]" v-html="getBatchQuantityDisplay(batch, drug)"></td>
                                                                        </tr>
                                                                         <tr v-if="!drug.batches || drug.batches.length === 0">
                                                                            <td colspan="3" class="px-4 py-4 text-center text-gray-400 italic">
                                                                                لا توجد تفاصيل متاحة للدفعات
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>

                                            <tr v-if="filteredDrugss.length === 0">
                                                <td colspan="6" class="py-12">
                                                    <EmptyState message="لا توجد أدوية في المخزون حالياً" />
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
<RegistrationModal
    :is-open="isRegistrationModalOpen"
    :categories="categories"
    :all-drugs-data="allDrugsData"
    :drugs-data="drugsData"
    @close="closeRegistrationModal"
    @confirm="handleRegistrationConfirm"
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
    width: 12%;
}
.strength-col {
    width: 12%;
}
.quantity-col {
    width: 13%; 
}
.needed-quantity-col {
    width: 13%; 
}
.drug-name-col {
    width: 25%;
}
.scientific-name-col {
    width: 25%;
}
</style>