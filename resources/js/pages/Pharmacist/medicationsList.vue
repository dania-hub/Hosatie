<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import DrugPreviewModal from "@/components/forpharmacist/DrugPreviewModal.vue";
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";
import SupplyRequestModal from "@/components/forpharmacist/SupplyRequestModal.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 1. تهيئة axios مع base URL الخاص بالصيدلي
// ----------------------------------------------------
const api = axios.create({
  baseURL: "/api/pharmacist",
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
const expiredDrugs = ref([]); // قائمة الأدوية المُصفرة (مخزنة في audit_log)

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
const hasData = ref(false);
const error = ref(null);
const expandedReviewId = ref(null); // للتحكم في الصف الموسع لعرض الدفعات

const toggleReview = (id) => {
    if (expandedReviewId.value === id) {
        expandedReviewId.value = null;
    } else {
        expandedReviewId.value = id;
    }
};

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

// دالة حساب نقاط الصلة للبحث الذكي
const calculateRelevanceScore = (drug, searchTerms) => {
  let score = 0;
  const drugName = (drug.drugName || drug.name || '').toLowerCase();
  const genericName = (drug.genericName || '').toLowerCase();
  const strength = (drug.strength || '').toLowerCase();
  const category = (drug.category || '').toLowerCase();
  const manufacturer = (drug.manufacturer || '').toLowerCase();
  
  searchTerms.forEach(term => {
    const termLower = term.toLowerCase();
    
    // مطابقة كاملة في اسم الدواء (أعلى نقاط)
    if (drugName === termLower) {
      score += 100;
    } else if (drugName.startsWith(termLower)) {
      score += 80;
    } else if (drugName.includes(termLower)) {
      score += 50;
    }
    
    // مطابقة في الاسم العلمي
    if (genericName === termLower) {
      score += 40;
    } else if (genericName.includes(termLower)) {
      score += 30;
    }
    
    // مطابقة في التركيز
    if (strength.includes(termLower)) {
      score += 20;
    }
    
    // مطابقة في الفئة
    if (category.includes(termLower)) {
      score += 15;
    }
    
    // مطابقة في الشركة المصنعة
    if (manufacturer.includes(termLower)) {
      score += 10;
    }
  });
  
  return score;
};

const filteredDrugss = computed(() => {
  if (!drugsData.value.length) return [];

  let list = [...drugsData.value];
  const search = searchTerm.value ? searchTerm.value.trim() : '';

  // 1. البحث الذكي في جميع الحقول
  if (search) {
    // تقسيم نص البحث إلى كلمات (يدعم البحث بعدة كلمات)
    const searchTerms = search.split(/\s+/).filter(term => term.length > 0);
    
    list = list.filter(drug => {
      // إنشاء نص شامل يحتوي على جميع المعلومات
      const drugNameStr = (drug.drugName || drug.name || '').toLowerCase();
      const genericNameStr = (drug.genericName || '').toLowerCase();
      const strengthStr = (drug.strength || '').toLowerCase();
      const quantityStr = (drug.quantity || 0).toString();
      const neededQuantityStr = (drug.neededQuantity || 0).toString();
      const expiryDateStr = (drug.expiryDate || '').toString();
      const categoryStr = (drug.category || '').toLowerCase();
      const statusStr = (drug.status || '').toLowerCase();
      const manufacturerStr = (drug.manufacturer || '').toLowerCase();
      const countryStr = (drug.country || '').toLowerCase();
      const unitStr = (drug.unit || '').toLowerCase();
      
      // البحث في جميع الحقول
      const fullText = `${drugNameStr} ${genericNameStr} ${strengthStr} ${quantityStr} ${neededQuantityStr} ${expiryDateStr} ${categoryStr} ${statusStr} ${manufacturerStr} ${countryStr} ${unitStr}`.trim();
      
      // البحث: يجب أن تطابق جميع الكلمات (AND logic)
      const allTermsMatch = searchTerms.every(term => 
        fullText.includes(term.toLowerCase())
      );
      
      if (allTermsMatch) {
        // حساب نقاط الصلة للترتيب
        drug._relevanceScore = calculateRelevanceScore(drug, searchTerms);
        return true;
      }
      
      return false;
    });
  }

  // 2. فلترة حسب التاريخ (تاريخ انتهاء الصلاحية)
  if (dateFrom.value || dateTo.value) {
    list = list.filter((drug) => {
      const expiryDate = drug.expiryDate;
      if (!expiryDate) return false;

      const expiryDateObj = parseDate(expiryDate);
      if (!expiryDateObj) return false;

      expiryDateObj.setHours(0, 0, 0, 0);

      let matchesFrom = true;
      let matchesTo = true;

      if (dateFrom.value) {
        const fromDate = new Date(dateFrom.value);
        fromDate.setHours(0, 0, 0, 0);
        matchesFrom = expiryDateObj >= fromDate;
      }

      if (dateTo.value) {
        const toDate = new Date(dateTo.value);
        toDate.setHours(23, 59, 59, 999);
        matchesTo = expiryDateObj <= toDate;
      }

      return matchesFrom && matchesTo;
    });
  }

  // 3. الفرز
  // إذا كان هناك بحث، يتم الترتيب حسب الصلة أولاً، ثم حسب الفرز المحدد
  // إذا لم يكن هناك بحث، يتم الفرز حسب الخيار المحدد فقط
  if (sortKey.value) {
    list.sort((a, b) => {
      // إذا كان هناك بحث ونقاط صلة، نستخدمها كأولوية
      if (search && (a._relevanceScore !== undefined || b._relevanceScore !== undefined)) {
        const scoreA = a._relevanceScore || 0;
        const scoreB = b._relevanceScore || 0;
        if (scoreB !== scoreA) {
          return scoreB - scoreA; // الأكثر صلة أولاً
        }
        // إذا كانت النقاط متساوية، نكمل بالفرز المحدد
      }
      
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

// استدعاء البحث من الـ API عند تغيير نص البحث (مع debounce)
let searchTimeout = null;
watch(
  () => searchTerm.value,
  async (val) => {
    // إلغاء البحث السابق إذا كان موجوداً
    if (searchTimeout) {
      clearTimeout(searchTimeout);
    }

    // إذا كان مربع البحث فارغاً، نرجع لقائمة كاملة من /drugs
    if (!val || val.trim() === '') {
      await fetchDrugs();
      return;
    }

    // استخدام debounce لتقليل عدد الطلبات (انتظار 300ms بعد توقف المستخدم عن الكتابة)
    searchTimeout = setTimeout(async () => {
      // البحث المحلي يتم في computed filteredDrugss وهو أسرع
      // يمكن استخدام API للبحث إذا كانت البيانات كبيرة جداً
      // حالياً نعتمد على البحث المحلي لأنه أسرع وأكثر كفاءة
      
      // إذا أردت استخدام API للبحث، يمكنك إلغاء التعليق من الكود التالي:
      /*
      try {
        // GET /api/pharmacist/drugs/search?search=...
        const response = await api.get("/drugs/search", {
          params: { search: val.trim() }
        });
        const data = response.data?.data ?? response.data;
        if (Array.isArray(data) && data.length > 0) {
          drugsData.value = data;
          hasData.value = true;
        }
      } catch (error) {
        // في حالة الخطأ، نستخدم البحث المحلي
        console.warn("Warning: Could not search drugs via API, using local search", error);
      }
      */
    }, 300); // انتظار 300ms قبل تنفيذ البحث
  }
);

// ----------------------------------------------------
// 6. وظائف API
// ----------------------------------------------------

// جلب جميع الأدوية من مخزون صيدلية الصيدلي
const fetchDrugs = async () => {
  isLoading.value = true;
  error.value = null;

  try {
    // GET /api/pharmacist/drugs  -> DrugPharmacistController@index
    const response = await api.get("/drugs");
    const data = response.data?.data ?? response.data;

    // التحقق من أن البيانات تحتوي على قائمة الأدوية والأدوية المُصفرة
    if (data && typeof data === 'object' && 'drugs' in data) {
      drugsData.value = (Array.isArray(data.drugs) ? data.drugs : []).map(drug => ({
        ...drug,
        units_per_box: Number(drug.units_per_box || drug.unitsPerBox || 1)
      }));
      expiredDrugs.value = Array.isArray(data.expiredDrugs) ? data.expiredDrugs : [];
    } else {
      drugsData.value = (Array.isArray(data) ? data : []).map(drug => ({
        ...drug,
        units_per_box: Number(drug.units_per_box || drug.unitsPerBox || 1)
      }));
      expiredDrugs.value = [];
    }
    
    hasData.value = drugsData.value.length > 0;

  } catch (err) {
    console.warn("Warning: Could not fetch drugs data from API", err);
    drugsData.value = [];
    hasData.value = false;
    error.value = "تعذر تحميل قائمة الأدوية.";
    
    // في حالة الخطأ، لا يوجد أدوية مُصفرة
    expiredDrugs.value = [];
  } finally {
    isLoading.value = false;
  }
};

// جلب الفئات (تصنيفات الأدوية)
const fetchCategories = async () => {
  try {
    // GET /api/pharmacist/categories  -> CategoryPharmacistController@index
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
    // GET /api/pharmacist/drugs/all  -> DrugPharmacistController@searchAll
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
      type: drug.form || 'Tablet',
      unitsLevel: drug.units_per_box || drug.unitsPerBox || 1,
      units_per_box: drug.units_per_box || drug.unitsPerBox || 1
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
    // PUT /api/pharmacist/drugs/{id}  -> تحديث سجل المخزون
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
    // DELETE /api/pharmacist/drugs/{id}
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
    // POST /api/pharmacist/drugs
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
    
    // تجهيز الحمولة لتتوافق مع SupplyRequestPharmacistController@store
    const payload = {
      items: (requestData.items || []).map(item => ({
        drugId: item.drugId || item.id,
        quantity: item.quantity
      })),
      notes: requestData.notes || null
    };

    console.log('Payload:', payload); // للتصحيح

    // POST /api/pharmacist/supply-requests
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
    const errorMessage = error.response?.data?.message || error.message || 'حدث خطأ غير متوقع';
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
  
  // التحقق من أن القيم موجودة (وليس فقط falsy) - 0 هو قيمة صالحة
  const qty = Number(quantity);
  const neededQty = Number(neededQuantity);
  
  if (isNaN(qty) || isNaN(neededQty) || neededQty <= 0) {
    return "bg-white border-gray-300 border";
  }
  
  const dangerThreshold = neededQty * 0.5; 
  const warningThreshold = neededQty * 0.55;  

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
 <th>الفئة</th>
 
 <th>الكمية المتوفرة</th>
 <th>الكمية المحتاجة</th>
 <th>تاريخ إنتهاء الصلاحية</th>
 <th>الحالة</th>
 </tr>
</thead>
<tbody>
`;

    filteredDrugss.value.forEach((drug) => {
      const statusText = drug.isUnregistered ? 'غير مسجل (موصوف للمرضى)' : 'مسجل';
      const statusStyle = drug.isUnregistered ? 'color: #2563eb; font-weight: bold;' : 'color: #059669;';
      
      const quantityDisplay = getQuantityDisplay(drug);
      const neededQuantityDisplay = getNeededQuantityDisplay(drug);
      
      tableHtml += `
<tr>

 <td>${drug.drugName || drug.name || ''}</td>
 <td>${drug.genericName || '-'}</td>
 <td>${drug.strength || '-'}</td>

 <td>${drug.category || '-'}</td>


 <td>${quantityDisplay}</td>
 <td>${neededQuantityDisplay}</td>
 <td>${drug.expiryDate || '-'}</td>
 <td style="${statusStyle}">${statusText}</td>
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
                        <div class="flex items-center gap-3 w-full sm:max-w-xl">
                            
                                <search v-model="searchTerm" placeholder="ابحث في جميع الحقول (اسم الدواء، الاسم العلمي، التركيز، الكمية، تاريخ الانتهاء...)" />
                           
                            
                            <!-- زر إظهار/إخفاء فلتر التاريخ -->
                            <button
                                @click="showDateFilter = !showDateFilter"
                                class="h-11 w-12 flex items-center justify-center border-2 border-[#ffffff8d] rounded-[30px] bg-[#4DA1A9] text-white hover:bg-[#5e8c90f9] hover:border-[#a8a8a8] transition-all duration-200"
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
                                class="tooltip inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-red-600 hover:border hover:border-[#a8a8a8] hover:bg-red-700"
                                @click="router.visit('/pharmacist/expired-drugs')"
                                title="الأدوية منتهية الصلاحية">
                            
                                <Icon icon="solar:danger-triangle-bold" class="w-5 h-5 " />
                              
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
                                    class="table-fixed w-full text-right min-w-[1200px] border-collapse"
                                >
                                    <thead
                                        class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                    >
                                        <tr>
                                            <th class="name-col px-4 py-3 text-sm">اسم الدواء</th>
                                            <th class="generic-name-col px-4 py-3 text-sm">الاسم العلمي</th>
                                            <th class="strength-col px-4 py-3 text-sm">التركيز</th>
                                            <th class="quantity-col px-4 py-3 text-sm">الكمية المتوفرة</th>
                                            <th class="needed-quantity-col px-4 py-3 text-sm">الكمية المحتاجة</th>
                                            <th class="actions-col px-4 py-3 text-sm text-center">الإجراءات</th>
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
                                                <ErrorState :message="error" :retry="retryLoading" />
                                            </td>
                                        </tr>
                                        <template v-else>
                                            <template v-for="(drug, index) in filteredDrugss" :key="drug.id || index">
                                                <tr
                                                    :class="[
                                                        'hover:bg-gray-100',
                                                        getRowColorClass(drug.quantity, drug.neededQuantity, drug.isUnregistered),
                                                    ]"
                                                >
                                                    <td class="name-col px-4 py-3" :class="getTextColorClass(drug.quantity, drug.neededQuantity, drug.isUnregistered)">
                                                        <div class="flex items-center gap-2">
                                                            <span class="truncate" :title="drug.drugName || drug.name">{{ drug.drugName || drug.name }}</span>
                                                            <span v-if="drug.isUnregistered" 
                                                                class="shrink-0 px-2 py-0.5 text-[10px] bg-blue-200 text-blue-800 rounded-full">
                                                                غير مسجل
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="generic-name-col px-4 py-3" :class="getTextColorClass(drug.quantity, drug.neededQuantity, drug.isUnregistered)">
                                                        <div class="truncate" :title="drug.genericName">{{ drug.genericName || '-' }}</div>
                                                    </td>
                                                    <td class="strength-col px-4 py-3 text-center" :class="getTextColorClass(drug.quantity, drug.neededQuantity, drug.isUnregistered)">
                                                        {{ drug.strength || '-' }}
                                                    </td>
                                                    <td class="quantity-col px-4 py-3 text-center" :class="getTextColorClass(drug.quantity, drug.neededQuantity, drug.isUnregistered)">
                                                        <div v-html="getQuantityDisplay(drug)"></div>
                                                    </td>
                                                    <td class="needed-quantity-col px-4 py-3 text-center" :class="getTextColorClass(drug.quantity, drug.neededQuantity, drug.isUnregistered)">
                                                        <div v-html="getNeededQuantityDisplay(drug)"></div>
                                                    </td>
                                                    <td class="actions-col">
                                                        <div class="flex gap-3 justify-center">
                                                            <!-- زر التوسيع لإظهار الدفعات -->
                                                            <button
                                                                v-if="!drug.isUnregistered && drug.batches && drug.batches.length > 0"
                                                                class="p-2 rounded-lg hover:bg-slate-100 border border-slate-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                                :class="expandedReviewId === (drug.id || index) ? 'bg-slate-200 border-slate-300' : 'bg-slate-50'"
                                                                title="عرض التفاصيل والدفعات"
                                                                @click="toggleReview(drug.id || index)"
                                                            >
                                                                <Icon
                                                                    :icon="expandedReviewId === (drug.id || index) ? 'solar:alt-arrow-up-bold' : 'solar:alt-arrow-down-bold'"
                                                                    class="w-4 h-4 text-slate-600 transition-transform duration-300"
                                                                />
                                                            </button>

                                                            <button
                                                                class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                                title="معاينة الدواء"
                                                                @click="showDrugDetails(drug)"
                                                            >
                                                                <Icon icon="tabler:eye" class="w-4 h-4 text-green-600" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <!-- الصف الموسع لعرض تفاصيل الدفعات -->
                                                <tr v-if="expandedReviewId === (drug.id || index)" class="bg-gray-50/50">
                                                    <td colspan="7" class="p-4 relative">
                                                        <div class="absolute right-8 top-0 w-0.5 h-full bg-[#4DA1A9]/20"></div>
                                                        <div class="pr-8">
                                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                                                <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                                                                    <h4 class="text-[#2E5077] text-sm flex items-center gap-2">
                                                                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                                                        تفاصيل الدفعات 
                                                                    </h4>
                                                                    <span class="text-xs bg-[#4DA1A9]/10 text-[#4DA1A9] px-2 py-1 rounded-lg">
                                                                        إجمالي الكمية: 
                                                                        <span class="block mt-1" v-html="getQuantityDisplay(drug)"></span>
                                                                    </span>
                                                                </div>
                                                                <table class="w-full text-right text-sm table-fixed">
                                                                    <thead class="bg-gray-50/50 text-gray-500 font-medium">
                                                                        <tr>
                                                                            <th class="px-4 py-2 w-[30%] text-sm font-normal">رقم الشحنة</th>
                                                                            <th class="px-4 py-2 w-[40%] text-sm font-normal text-center">تاريخ انتهاء الصلاحية</th>
                                                                            <th class="px-4 py-2 w-[30%] text-sm font-normal text-center">الكمية</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="divide-y divide-gray-50">
                                                                        <tr v-for="(batch, bIndex) in drug.batches" :key="bIndex" class="hover:bg-gray-50 transition-colors">
                                                                            <td class="px-4 py-2.5 text-gray-700 font-mono text-xs">{{ batch.batchNumber || '---' }}</td>
                                                                            <td class="px-4 py-2.5 text-gray-700 font-mono tracking-wide text-center text-xs" dir="ltr">{{ batch.expiryDate }}</td>
                                                                            <td class="px-4 py-2.5 text-[#4DA1A9] text-center font-medium" v-html="getBatchQuantityDisplay(batch, drug)"></td>
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
                                                <td colspan="7" class="py-12">
                                                    <EmptyState message="لا توجد أدوية لعرضها" />
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

        <Toast
            :show="isAlertVisible"
            :message="alertMessage"
            :type="alertType"
            @close="isAlertVisible = false"
        />
    </div>
</template>

<style>
/* .نفس الأنماط السابقة */
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

.name-col {
    width: 30%;
}
.generic-name-col {
    width: 25%;
}
.strength-col {
    width: 12%;
}
.quantity-col {
    width: 11%; 
}
.needed-quantity-col {
    width: 12%; 
}
.actions-col {
    width: 10%;
}
</style>