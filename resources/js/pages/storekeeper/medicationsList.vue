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
const expandedReviewId = ref(null);
const toggleReview = (id) => {
    expandedReviewId.value = expandedReviewId.value === id ? null : id;
};

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

  // 1. التصفية
  let list = [...drugsData.value];
  if (searchTerm.value) {
    const search = searchTerm.value.toLowerCase();
    list = list.filter(
      (drug) =>
        (drug.drugName && drug.drugName.toLowerCase().includes(search)) ||
        (drug.name && drug.name.toLowerCase().includes(search)) ||
        (drug.genericName && drug.genericName.toLowerCase().includes(search)) ||
        (drug.strength && drug.strength.toLowerCase().includes(search))
    );
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
      type: drug.form || 'Tablet',
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

// -----------------------------------------------------
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
    return "text-blue-700";
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
    return "text-red-700";
  } else if (qty < warningThreshold) {
    return "text-yellow-700";
  } else {
    return "text-gray-800";
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
            display += ` و ${remainder} ${unit}`;
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
            display += ` و ${remainder} ${unit}`;
        }
        return display;
    } else {
        return `${neededQuantity} ${unit}`;
    }
};

const getBatchQuantityDisplay = (batch, drug) => {
    if (!batch || !drug) return '0';
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
            display += ` و ${remainder} ${unit}`;
        }
        return display;
    } else {
        return `${quantity} ${unit}`;
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
// ----------------------------------------------------
// 9. منطق الطباعة
// ----------------------------------------------------
const printTable = () => {
  const resultsCount = filteredDrugss.value.length;

  const printWindow = window.open("", "_blank", "height=800,width=1000");

  if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
    showErrorAlert(" فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
    return;
  }

  const currentDate = new Date().toLocaleDateString('ar-EG', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    numberingSystem: 'latn'
  });

  let tableHtml = `
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <style>
        @media print {
            @page { margin: 1cm; }
            body { -webkit-print-color-adjust: exact; }
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            direction: rtl; 
            padding: 20px; 
            background-color: #fff;
            color: #333;
            line-height: 1.5;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #2E5077;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .header h1 { 
            margin: 0; 
            color: #2E5077; 
            font-size: 24px;
            letter-spacing: -0.5px;
        }
        
        .meta-info {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #666;
            margin-top: 10px;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 5px;
        }
        
        th { 
            background-color: #2E5077; 
            color: white;
            font-weight: 600;
            padding: 12px 10px;
            text-align: right;
            font-size: 13px;
            border: 1px solid #2E5077;
        }
        
        td { 
            padding: 10px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
            font-size: 12px;
        }
        
        .drug-title {
            font-weight: bold;
            color: #1a202c;
            font-size: 14px;
            margin-bottom: 6px;
        }

        .drug-subtitle {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .batch-table {
            width: 100%;
            font-size: 11px;
            border: none !important;
            background-color: #f1f5f9;
            border-radius: 4px;
        }
        
        .batch-table td {
            border: none;
            padding: 4px 8px;
            color: #475569;
        }
        
        .batch-table tr:not(:last-child) {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .status-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status-reg { background-color: #dcfce7; color: #166534; }
        .status-unreg { background-color: #dbeafe; color: #1e40af; }
        
        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-around;
        }
        
        .sig-box {
            text-align: center;
            width: 200px;
        }
        
        .sig-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: bold;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير مخزون المستودع الرئيسي</h1>
        <div class="meta-info">
            <span>التاريخ: ${currentDate}</span>
            <span>عدد الأصناف: ${resultsCount}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 35%;">معلومات الدواء والشحنات</th>
                <th style="width: 20%;">التفاصيل العلمية</th>
                <th style="width: 15%;">الكمية الحالية</th>
                <th style="width: 15%;">الكمية المحتاجة</th>
                <th style="width: 15%;">الحالة</th>
            </tr>
        </thead>
        <tbody>
    `;

  if (resultsCount > 0) {
    filteredDrugss.value.forEach((drug) => {
      const isUnreg = !!drug.isUnregistered;
      
      let batchRows = '';
      if (!isUnreg && drug.batches && drug.batches.length > 0) {
        batchRows = `
            <table class="batch-table">
                ${drug.batches.map(batch => `
                    <tr>
                        <td style="width: 35%">شحنة: <b>${batch.batchNumber || '---'}</b></td>
                        <td style="width: 35%">انتهاء: <b>${batch.expiryDate || '---'}</b></td>
                        <td style="width: 30%; text-align: left;">الكمية: <b>${getBatchQuantityDisplay(batch, drug).replace(/<br>/g, ' ')}</b></td>
                    </tr>
                `).join('')}
            </table>
        `;
      }

      tableHtml += `
        <tr>
            <td>
                <div class="drug-title">${drug.drugName || drug.name || '---'}</div>
                <div class="drug-subtitle">الفئة: ${drug.category || '---'}</div>
                ${batchRows}
            </td>
            <td>
                <div><b>الاسم العلمي:</b> ${drug.genericName || '---'}</div>
                <div style="margin-top: 5px;"><b>التركيز:</b> ${drug.strength || '---'}</div>
            </td>
            <td style="text-align: center; font-weight: bold; font-size: 14px;">
                ${getQuantityDisplay(drug).replace(/<br>/g, ' ')}
            </td>
            <td style="text-align: center; color: #64748b;">
                ${getNeededQuantityDisplay(drug).replace(/<br>/g, ' ')}
            </td>
            <td style="text-align: center;">
                <span class="status-tag ${isUnreg ? 'status-unreg' : 'status-reg'}">
                    ${isUnreg ? 'غير مسجل' : 'مسجل'}
                </span>
            </td>
        </tr>
      `;
    });
  } else {
    tableHtml += '<tr><td colspan="5" style="text-align: center; padding: 30px;">لا توجد بيانات متاحة</td></tr>';
  }

  tableHtml += `
        </tbody>
    </table>

    <div class="footer">
        <div class="sig-box">
            <div class="sig-line">توقيع أمين المخزن المسئول</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">اعتماد مدير المستشفى</div>
        </div>
    </div>
</body>
</html>
`;

  printWindow.document.write(tableHtml);
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
                            <div class="relative w-full sm:max-w-xs">
                                <search v-model="searchTerm" placeholder="ابحث بالاسم، الاسم العلمي، التركيز أو الفئة" />
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
                                <span class="text-[#4DA1A9] text-lg">{{
                                    filteredDrugss.length
                                }}</span>
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-5 w-full sm:w-auto justify-end"
                        >
                       
                            <button
                                class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-red-600 hover:border hover:border-[#a8a8a8] hover:bg-red-700"
                                @click="router.visit('/storekeeper/expired-drugs')"
                             title="الأدوية منتهية الصلاحية">
                                <Icon icon="solar:danger-triangle-bold" class="w-5 h-5" />
                               
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
                                    class="table w-full text-right min-w-[1000px] border-collapse"
                                >
                                    <thead
                                        class="bg-[#9aced2] text-black sticky top-0 z-10 border-b border-gray-300"
                                    >
                                        <tr>
                                            <th class="drug-name-col">
                                                اسم الدواء
                                            </th>
                                            <th class="scientific-name-col">
                                                الاسم العلمي
                                            </th>
                                            <th class="strength-col">
                                                التركيز
                                            </th>
                                            <th class="quantity-col">
                                                الكمية المتوفرة
                                            </th>
                                            <th class="needed-quantity-col">
                                                الكمية المحتاجة
                                            </th>
                                            <th class="actions-col">الإجراءات</th>
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
                                                                class="px-2 py-0.5 text-xs bg-blue-200 text-blue-800 rounded-full"
                                                                title="دواء غير مسجل في مخزون المورد ولكن مطلوب في الطلبات المستقبلة">
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
                                                        {{ drug.genericName || 'غير محدد' }}
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
                                                        {{ drug.strength || 'غير محدد' }}
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
                                                        <div v-html="getQuantityDisplay(drug)"></div>
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
                                                        <div v-html="getNeededQuantityDisplay(drug)"></div>
                                                        <span v-if="drug.isUnregistered" class="text-xs text-blue-600 block mt-1">
                                                            (من الطلبات)
                                                        </span>
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
                                                                    <h4 class="text-[#2E5077] text-sm flex items-center gap-2">
                                                                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                                                        تفاصيل الدفعات 
                                                                    </h4>
                                                                    <span class="text-xs bg-[#4DA1A9]/10 text-[#4DA1A9] px-2 py-1 rounded-lg">
                                                                        إجمالي الكمية: <span v-html="getQuantityDisplay(drug)"></span>
                                                                    </span>
                                                                </div>
                                                                <table class="w-full text-right text-sm">
                                                                    <thead class="bg-gray-50/50 text-gray-500 font-medium">
                                                                        <tr>
                                                                            <th class="px-4 py-2 w-1/3">رقم الشحنة </th>
                                                                            <th class="px-4 py-2 w-1/3">تاريخ الإنتهاء</th>
                                                                            <th class="px-4 py-2 w-1/3">الكمية</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="divide-y divide-gray-50">
                                                                        <tr v-for="(batch, bIndex) in drug.batches" :key="bIndex" class="hover:bg-gray-50 transition-colors">
                                                                            <td class="px-4 py-2.5 text-gray-700 font-mono">{{ batch.batchNumber || '---' }}</td>
                                                                            <td class="px-4 py-2.5 text-gray-700 font-mono tracking-wide" dir="ltr" style="text-align: right;">{{ batch.expiryDate }}</td>
                                                                            <td class="px-4 py-2.5 text-[#4DA1A9]" v-html="getBatchQuantityDisplay(batch, drug)"></td>
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


.drug-name-col {
    min-width: 150px;
}

.scientific-name-col {
    min-width: 150px;
}

.actions-col {
    width: 120px;
    min-width: 120px;
    text-align: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.strength-col {
    width: 100px;
    min-width: 100px;
}

.quantity-col {
    width: 100px; 
    min-width: 100px;
}
.needed-quantity-col {
    width: 100px; 
    min-width: 100px;
}
</style>