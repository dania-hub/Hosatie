<script setup>
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import DrugPreviewModal from "@/components/forsuperadmin/DrugPreviewModal.vue";
import AddDrugModal from "@/components/forsuperadmin/AddDrugModal.vue";
import EditDrugModal from "@/components/forsuperadmin/EditDrugModal.vue";

// ----------------------------------------------------
// 0. نظام التنبيهات - يجب تعريفه قبل الاستخدام
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
// 1. تهيئة axios مع base URL
// ----------------------------------------------------
const api = axios.create({
  baseURL: "/api",
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json"
  }
});

// إضافة interceptor لإضافة التوكن تلقائياً
api.interceptors.request.use(
  (config) => {
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

api.interceptors.response.use(
  response => response,
  error => {
    console.error("API Error:", error.response?.data || error.message);
    if (error.response?.status === 401) {
      showErrorAlert("❌ انتهت جلسة العمل. يرجى تسجيل الدخول مرة أخرى.");
    } else if (error.response?.status === 403) {
      showErrorAlert("❌ ليس لديك الصلاحية للقيام بهذا الإجراء.");
    } else if (!error.response) {
      showErrorAlert("❌ فشل في الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.");
    }
    return Promise.reject(error);
  }
);

// ----------------------------------------------------
// 2. بيانات الأدوية والفئات
// ----------------------------------------------------
const drugsData = ref([]);

const categories = ref([]);

const allDrugsData = ref([]); // سيتم ملؤه لاحقاً للبحث
// قوائم الاختيار
const pharmaceuticalForms = ref([]);

const countries = ref([]);

// ----------------------------------------------------
// 3. حالة المكونات المنبثقة
// ----------------------------------------------------
const isDrugPreviewModalOpen = ref(false);
const isAddDrugModalOpen = ref(false);
const isEditDrugModalOpen = ref(false);
const isSupplyRequestModalOpen = ref(false);
const selectedDrug = ref({});
const selectedDrugForEdit = ref({});

// ----------------------------------------------------
// 4. حالة التحميل والأخطاء
// ----------------------------------------------------
const isLoading = ref(false);
const error = ref(null);
const hasData = ref(false);

// ----------------------------------------------------
// 5. منطق البحث والفرز (مُعدل)
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

  // 1. التصفية - البحث في جميع الحقول
  let list = drugsData.value;
  if (searchTerm.value) {
    const search = searchTerm.value.toLowerCase().trim();
    list = list.filter((drug) => {
      // فحص جميع الحقول النصية في الكائن
      for (const key in drug) {
        // تخطي الحقول غير النصية مثل id، dates
        if (key === 'id' || key.includes('Date') || key === 'quantity') continue;
        
        const value = drug[key];
        if (value && typeof value === 'string' && value.toLowerCase().includes(search)) {
          return true;
        }
        
        // أيضًا التحقق من الحقول الرقمية إذا تم تحويلها إلى نص
        if (value && typeof value === 'number' && value.toString().includes(search)) {
          return true;
        }
      }
      return false;
    });
  }
  // 2. الفرز
  if (sortKey.value) {
    list.sort((a, b) => {
      let comparison = 0;

      if (sortKey.value === "drugName") {
        comparison = (a.drugName || a.name || "").localeCompare(b.drugName || b.name || "", "ar");
      } else if (sortKey.value === "quantity") {
        comparison = (a.quantity || 0) - (b.quantity || 0);
      } else if (sortKey.value === "expiryDate") {
        const dateA = a.expiryDate ? new Date(a.expiryDate) : new Date(0);
        const dateB = b.expiryDate ? new Date(b.expiryDate) : new Date(0);
        comparison = dateA - dateB;
      } else if (sortKey.value === "manufacturer") {
        comparison = (a.manufacturer || "").localeCompare(b.manufacturer || "", "ar");
      }

      return sortOrder.value === "asc" ? comparison : -comparison;
    });
  }

  return list;
});

// ----------------------------------------------------
// 6. وظائف API
// ----------------------------------------------------

// دالة مساعدة لتحويل بيانات الدواء من API إلى التنسيق المستخدم في الجدول
const transformDrugData = (drug) => {
  return {
    id: drug.id,
    drugCode: drug.id?.toString() || '', // استخدام ID كرمز الدواء
    drugName: drug.name || '',
    scientificName: drug.genericName || drug.generic_name || '',
    therapeuticClass: drug.category || '',
    expiryDate: drug.expiryDate || drug.expiry_date || '',
    manufacturer: drug.manufacturer || '',
    country: drug.country || '',
    form: drug.form || '',
    strength: drug.strength || '',
    unit: drug.unit || '',
    status: drug.status || '',
    quantity: 0, // غير متوفر في API
    neededQuantity: 0, // غير متوفر في API
    is_discontinued: drug.status === 'غير متوفر' || drug.is_discontinued === true,
    // حفظ البيانات الأصلية للاستخدام في النماذج
    ...drug
  };
};

// جلب جميع الأدوية
const fetchDrugs = async () => {
  isLoading.value = true;
  error.value = null;
  
  try {
    const response = await api.get("/super-admin/drugs");
    const rawData = response.data.data || response.data;
    
    // تحويل البيانات لتطابق الأسماء المستخدمة في الجدول
    drugsData.value = rawData.map(drug => transformDrugData(drug));
    
    hasData.value = drugsData.value.length > 0;
    if (drugsData.value.length > 0) {
      showSuccessAlert("✅ تم تحميل قائمة الأدوية بنجاح");
    }
  } catch (err) {
    console.error("Error fetching drugs:", err);
    error.value = err.response?.data?.message || err.message || "فشل في تحميل قائمة الأدوية";
    hasData.value = false;
    showErrorAlert(`❌ ${error.value}`);
  } finally {
    isLoading.value = false;
  }
};

// جلب الفئات
const fetchCategories = async () => {
  try {
    const response = await api.get("/categories");
    categories.value = response.data;
  } catch (error) {
    console.warn("Warning: Could not fetch categories from API");
    
  }
};
// أضف هذه الوظائف بعد الوظائف الحالية
const fetchPharmaceuticalForms = async () => {
  try {
    const response = await api.get("/pharmaceutical-forms");
    pharmaceuticalForms.value = response.data;
  } catch (error) {
    console.warn("Warning: Could not fetch pharmaceutical forms from API");
    // يمكنك تعيين قيم افتراضية هنا إذا لزم الأمر
  }
};

const fetchCountries = async () => {
  try {
    const response = await api.get("/countries");
    countries.value = response.data;
  } catch (error) {
    console.warn("Warning: Could not fetch countries from API");
    // يمكنك تعيين قيم افتراضية هنا إذا لزم الأمر
  }
};

// جلب جميع بيانات الأدوية للبحث
const fetchAllDrugsData = async () => {
  try {
    const response = await api.get("/super-admin/drugs");
    const rawData = response.data.data || response.data;
    allDrugsData.value = rawData.map(drug => transformDrugData(drug));
  } catch (error) {
    console.warn("Warning: Could not fetch all drugs data from API");
  }
};

// ----------------------------------------------------
// 7. وظائف فتح وإغلاق النماذج
// ----------------------------------------------------
const showDrugDetails = (drug) => {
  selectedDrug.value = drug;
  isDrugPreviewModalOpen.value = true;
};

const openAddDrugModal = () => {
  isAddDrugModalOpen.value = true;
};

const openEditDrugModal = (drug) => {
  selectedDrugForEdit.value = { ...drug };
  isEditDrugModalOpen.value = true;
};

const openSupplyRequestModal = () => {
  isSupplyRequestModalOpen.value = true;
};

// ----------------------------------------------------
// 8. حالة modal تأكيد الحذف
// ----------------------------------------------------
const isDeleteConfirmationModalOpen = ref(false);
const drugToDelete = ref(null);

// ----------------------------------------------------
// 9. وظائف CRUD
// ----------------------------------------------------

// إضافة دواء جديد
const addNewDrug = async (drugData) => {
  isLoading.value = true;
  
  try {
    const response = await api.post("/super-admin/drugs", drugData);
    const rawDrug = response.data.data || response.data;
    const newDrug = transformDrugData(rawDrug);
    
    // تحديث القائمة المحلية
    drugsData.value.unshift(newDrug);
    showSuccessAlert("✅ تم إضافة الدواء بنجاح");
    
    // تحديث البيانات المحلية للبحث
    allDrugsData.value.unshift(newDrug);
    
    // إغلاق النافذة
    isAddDrugModalOpen.value = false;
  } catch (error) {
    console.error("Error adding drug:", error);
    const errorMsg = error.response?.data?.message || "❌ فشل في إضافة الدواء";
    showErrorAlert(errorMsg);
  } finally {
    isLoading.value = false;
  }
};

// تحديث بيانات دواء
const updateDrug = async (updatedDrug) => {
  isLoading.value = true;
  
  try {
    const response = await api.put(`/super-admin/drugs/${updatedDrug.id}`, updatedDrug);
    const rawUpdated = response.data.data || response.data;
    const updated = transformDrugData(rawUpdated);
    
    // تحديث القائمة المحلية
    const index = drugsData.value.findIndex(drug => drug.id === updatedDrug.id);
    if (index !== -1) {
      drugsData.value[index] = updated;
    }
    
    // تحديث بيانات البحث
    const allIndex = allDrugsData.value.findIndex(drug => drug.id === updatedDrug.id);
    if (allIndex !== -1) {
      allDrugsData.value[allIndex] = updated;
    }
    
    showSuccessAlert("✅ تم تحديث بيانات الدواء بنجاح");
    
    // إغلاق النافذة
    isEditDrugModalOpen.value = false;
    isDrugPreviewModalOpen.value = false;
  } catch (error) {
    console.error("Error updating drug:", error);
    const errorMsg = error.response?.data?.message || "❌ فشل في تحديث بيانات الدواء";
    showErrorAlert(errorMsg);
  } finally {
    isLoading.value = false;
  }
};

// تأكيد الحذف
const confirmDeleteDrug = (drugId) => {
  drugToDelete.value = drugId;
  isDeleteConfirmationModalOpen.value = true;
};

// إيقاف دواء (discontinue)
const discontinueDrug = async () => {
  if (!drugToDelete.value) return;
  
  const drugId = drugToDelete.value;
  isLoading.value = true;
  
  try {
    const response = await api.patch(`/super-admin/drugs/${drugId}/discontinue`);
    const rawUpdated = response.data.data || response.data;
    const updated = transformDrugData(rawUpdated);
    
    // تحديث القائمة المحلية
    const index = drugsData.value.findIndex(drug => drug.id === drugId);
    if (index !== -1) {
      drugsData.value[index] = updated;
    }
    
    // تحديث بيانات البحث
    const allIndex = allDrugsData.value.findIndex(drug => drug.id === drugId);
    if (allIndex !== -1) {
      allDrugsData.value[allIndex] = updated;
    }
    
    showSuccessAlert("✅ تم إيقاف الدواء بنجاح");
    
    // إغلاق نافذة المعاينة إذا كانت مفتوحة
    if (isDrugPreviewModalOpen.value && selectedDrug.value.id === drugId) {
      isDrugPreviewModalOpen.value = false;
    }
  } catch (error) {
    console.error("Error discontinuing drug:", error);
    const errorMsg = error.response?.data?.message || "❌ فشل في إيقاف الدواء";
    showErrorAlert(errorMsg);
  } finally {
    isLoading.value = false;
    closeDeleteConfirmationModal();
  }
};

// إعادة تفعيل دواء (reactivate)
const reactivateDrug = async (drugId) => {
  isLoading.value = true;
  
  try {
    const response = await api.patch(`/super-admin/drugs/${drugId}/reactivate`);
    const rawUpdated = response.data.data || response.data;
    const updated = transformDrugData(rawUpdated);
    
    // تحديث القائمة المحلية
    const index = drugsData.value.findIndex(drug => drug.id === drugId);
    if (index !== -1) {
      drugsData.value[index] = updated;
    }
    
    // تحديث بيانات البحث
    const allIndex = allDrugsData.value.findIndex(drug => drug.id === drugId);
    if (allIndex !== -1) {
      allDrugsData.value[allIndex] = updated;
    }
    
    showSuccessAlert("✅ تم إعادة تفعيل الدواء بنجاح");
  } catch (error) {
    console.error("Error reactivating drug:", error);
    const errorMsg = error.response?.data?.message || "❌ فشل في إعادة تفعيل الدواء";
    showErrorAlert(errorMsg);
  } finally {
    isLoading.value = false;
  }
};

// إغلاق modal تأكيد الحذف
const closeDeleteConfirmationModal = () => {
  isDeleteConfirmationModalOpen.value = false;
  drugToDelete.value = null;
};

// ----------------------------------------------------
// 10. منطق الطباعة
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
.print-date { text-align: left; margin-bottom: 10px; font-size: 12px; color: #666; }
</style>

<h1>قائمة الأدوية (تقرير طباعة)</h1>
<p class="print-date">تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')}</p>
`;

  if (resultsCount > 0) {
    tableHtml += `
<p class="results-info">عدد النتائج: ${resultsCount}</p>

<table>
<thead>
 <tr>
 <th>#</th>
 <th>رمز الدواء</th>
 <th>اسم الدواء</th>
 <th>الاسم العلمي</th>
 <th>الفئة العلاجية</th>

 <th>تاريخ الانتهاء</th>
 <th>الشركة المصنعة</th>
 </tr>
</thead>
<tbody>
`;

    filteredDrugss.value.forEach((drug, index) => {
      tableHtml += `
<tr>
 <td>${index + 1}</td>
 <td>${drug.drugCode || ''}</td>
 <td>${drug.drugName || ''}</td>
 <td>${drug.scientificName || ''}</td>
 <td>${drug.therapeuticClass || ''}</td>

 <td>${drug.expiryDate || ''}</td>
 <td>${drug.manufacturer || ''}</td>
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
  <p>لا توجد أدوية</p>
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

// ----------------------------------------------------
// 11. وظائف المساعدة
// ----------------------------------------------------

// تحديد لون الصف حسب الكمية
const getRowColorClass = (quantity, neededQuantity) => {
  const qty = parseInt(quantity) || 0;
  const neededQty = parseInt(neededQuantity) || 0;
  
  if (qty === 0) return 'bg-red-50';
  if (qty < neededQty) return 'bg-yellow-50';
  if (qty >= neededQty * 2) return 'bg-green-50';
  return '';
};

// تحديد لون النص حسب الكمية
const getTextColorClass = (quantity, neededQuantity) => {
  return 'text-gray-800';
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
// 12. تهيئة البيانات عند تحميل المكون
// ----------------------------------------------------
onMounted(async () => {
  await Promise.all([
    fetchDrugs(),
    fetchCategories(),
    fetchAllDrugsData(),
    fetchPharmaceuticalForms(),    // ← أضف هذه
    fetchCountries()               // ← وهذه
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
                                            الأقل كمية
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
                                            الأكثر كمية
                                        </a>
                                    </li>

                                    <li
                                        class="menu-title text-gray-700 font-bold text-sm mt-2"
                                    >
                                        حسب الشركة المصنعة:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('manufacturer', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'manufacturer' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            الشركة (أ - ي)
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('manufacturer', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'manufacturer' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            الشركة (ي - أ)
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
                                class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-32 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                                @click="openAddDrugModal"
                            >
                                <Icon icon="tabler:plus" class="w-5 h-5 ml-2" />
                                إضافة دواء
                            </button>
                           
                            <btnprint @click="printTable" />
                        </div>
                    </div>

                    <div
                        class=" rounded-2xl shadow h-107 overflow-hidden flex flex-col"
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
                                        <th class="drug-code-col">رمز الدواء</th>
                                        <th class="drug-name-col">اسم الدواء</th>
                                        <th class="scientific-name-col">الاسم العلمي</th>
                                        <th class="category-col">الفئة العلاجية</th>
                                        <th class="expiry-date-col">تاريخ الانتهاء</th>
                                        <th class="actions-col">الإجراءات</th>
                                    </tr>
                                    </thead>

                                <tbody class="text-gray-800 ">
                                    <tr v-if="isLoading">
                                        <td colspan="6" class="p-4">
                                            <TableSkeleton :rows="10" />
                                        </td>
                                    </tr>
                                    <tr v-else-if="error">
                                        <td colspan="6" class="py-12">
                                            <ErrorState :message="error" :retry="retryLoading" />
                                        </td>
                                    </tr>
                                    <template v-else>
                                        <tr
                                            v-for="(drug, index) in filteredDrugss"
                                            :key="drug.id || index"
                                            :class="[
                                            
                                                'hover:bg-gray-100 bg-white',
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
                                                {{ drug.drugCode }}
                                            </td>
                                            <td
                                                :class="
                                                    getTextColorClass(
                                                        drug.quantity,
                                                        drug.neededQuantity
                                                    )
                                                "
                                            >
                                                {{ drug.drugName }}
                                            </td>
                                            <td
                                                :class="
                                                    getTextColorClass(
                                                        drug.quantity,
                                                        drug.neededQuantity
                                                    )
                                                "
                                            >
                                                {{ drug.scientificName }}
                                            </td>
                                            <td
                                                :class="
                                                    getTextColorClass(
                                                        drug.quantity,
                                                        drug.neededQuantity
                                                    )
                                                "
                                            >
                                                {{ drug.therapeuticClass }}
                                            </td>
                                            
                                            <td
                                                :class="
                                                    getTextColorClass(
                                                        drug.quantity,
                                                        drug.neededQuantity
                                                    )
                                                "
                                            >
                                                {{ drug.expiryDate }}
                                            </td>
                                           
                                            <td class="actions-col">
                                                <div
                                                    class="flex gap-3 justify-center"
                                                >
                                                    <button
                                                        @click="
                                                            showDrugDetails(drug)
                                                        "
                                                        class="p-1 hover:bg-green-50 rounded-lg transition-colors"
                                                        title="معاينة"
                                                    >
                                                        <Icon
                                                            icon="tabler:eye-minus"
                                                            class="w-5 h-5 cursor-pointer hover:scale-110 transition-transform text-green-700"
                                                        />
                                                    </button>
                                                    <button
                                                        @click="
                                                            openEditDrugModal(drug)
                                                        "
                                                        class="p-1 hover:bg-blue-50 rounded-lg transition-colors"
                                                        title="تعديل"
                                                    >
                                                        <Icon
                                                            icon="tabler:edit"
                                                            class="w-5 h-5 cursor-pointer hover:scale-110 transition-transform text-blue-600"
                                                        />
                                                    </button>
                                                    <button
                                                        @click="
                                                            confirmDeleteDrug(drug.id)
                                                        "
                                                        class="p-1 hover:bg-red-50 rounded-lg transition-colors"
                                                        title="إيقاف"
                                                    >
                                                        <Icon
                                                            icon="tabler:ban"
                                                            class="w-5 h-5 cursor-pointer hover:scale-110 transition-transform text-red-600"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

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

        <!-- نماذج الإدارة -->
        <DrugPreviewModal 
            :is-open="isDrugPreviewModalOpen"
            :drug="selectedDrug"
            @close="isDrugPreviewModalOpen = false"
            @update-drug="updateDrug"
            @delete-drug="confirmDeleteDrug"
        />

        <AddDrugModal 
            :is-open="isAddDrugModalOpen"
            @close="isAddDrugModalOpen = false"
            @add-drug="addNewDrug"
        />

        <EditDrugModal 
            :is-open="isEditDrugModalOpen"
            :drug="selectedDrugForEdit"
            @close="isEditDrugModalOpen = false"
            @update-drug="updateDrug"
        />

        <!-- Modal تأكيد الحذف -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="isDeleteConfirmationModalOpen" 
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" 
                @click.self="closeDeleteConfirmationModal"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
                    <div class="p-6 text-center space-y-4">
                        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <Icon icon="solar:trash-bin-trash-bold-duotone" class="w-10 h-10 text-red-500" />
                        </div>
                        <h3 class="text-xl font-bold text-[#2E5077]">تأكيد إيقاف الدواء</h3>
                        <p class="text-gray-500 leading-relaxed">
                            هل أنت متأكد من رغبتك في إيقاف هذا الدواء؟
                            <br>
                            <span class="text-sm text-orange-500">يمكنك إعادة تفعيل الدواء لاحقاً</span>
                        </p>
                    </div>
                    <div class="flex justify-center bg-gray-50 px-6 py-4 gap-3 border-t border-gray-100">
                        <button 
                            @click="closeDeleteConfirmationModal" 
                            class="px-6 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200 border border-gray-300"
                        >
                            إلغاء
                        </button>
                        <button 
                            @click="discontinueDrug" 
                            class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-red-500/20 flex items-center gap-2 transition-all duration-200 bg-gradient-to-r from-red-500 to-red-600 hover:shadow-xl hover:-translate-y-0.5"
                        >
                            <Icon icon="tabler:ban" class="w-5 h-5" />
                            إيقاف الدواء
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
       
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
                <div class="flex items-center gap-2">
                    <Icon icon="tabler:circle-check" class="w-5 h-5" />
                    {{ successMessage }}
                </div>
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
                <div class="flex items-center gap-2">
                    <Icon icon="tabler:alert-circle" class="w-5 h-5" />
                    {{ errorMessage }}
                </div>
            </div>
        </Transition>
    </div>
</template>

<style>
/* تحسينات التمرير */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
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

/* تنسيق أعمدة الجدول */
.actions-col {
    width: 150px;
    min-width: 150px;
    max-width: 150px;
    text-align: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
.drug-code-col {
    width: 120px;
    min-width: 120px;
}
.quantity-col {
    width: 100px; 
    min-width: 100px;
}
.expiry-date-col {
    width: 130px;
    min-width: 130px;
}
.drug-name-col {
    width: auto;
    min-width: 150px;
}
.min-w-\[700px\] {
    min-width: 700px;
}

/* تحسينات عامة */
.table th {
    font-weight: 700;
    font-size: 14px;
}
.table td {
    font-size: 14px;
    vertical-align: middle;
}

/* تأثيرات الانتقال */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

/* تحسينات للأزرار */
button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* تحسينات للأجهزة المحمولة */
@media (max-width: 768px) {
    .table {
        font-size: 12px;
    }
    .table th, .table td {
        padding: 8px 6px;
    }
}
input[type="search"]::placeholder {
    color: #666;
    font-size: 0.9em;
}

</style>