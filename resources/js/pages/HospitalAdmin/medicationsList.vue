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
    return "text-red-700";
  } else if (qty < warningThreshold) {
    return "text-yellow-700";
  } else {
    return "text-gray-800";
  }
};

const getGenericQuantityDisplay = (quantity, drug) => {
    if (!drug) return quantity || 0;
    const unit = drug.unit || 'قرص';
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';
    const unitsPerBox = Number(drug.units_per_box || 1);
    const qty = Number(quantity || 0);

    if (unitsPerBox > 1) {
        const boxes = Math.floor(qty / unitsPerBox);
        const remainder = qty % unitsPerBox;
        
        if (boxes === 0 && qty > 0) {
            return `${qty} ${unit}`;
        }
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += ` و ${remainder} ${unit}`;
        }
        return display;
    } else {
        return `${qty} ${unit}`;
    }
};

const getBatchQuantityDisplay = (batch, drug) => {
    return getGenericQuantityDisplay(batch.quantity, drug);
};

// ----------------------------------------------------
// 8. وظائف العرض
// ----------------------------------------------------
const isDrugDetailsLoading = ref(false);
const showDrugDetails = async (drug) => {
  isDrugDetailsLoading.value = true;
  isDrugPreviewModalOpen.value = true;
  try {
    // نستخدم drug.drugCode كونه يمثل معرف الدواء الحقيقي
    const drugId = drug.drugCode || drug.id;
    const response = await api.get(`/admin-hospital/drugs/${drugId}`);
    const data = response.data?.data ?? response.data;
    selectedDrug.value = data || drug;
  } catch (e) {
    console.error('Error fetching drug details:', e);
    selectedDrug.value = drug; // fallback
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
            padding-bottom: 20px;
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
            font-size: 10px;
            border: none !important;
            background-color: #f8fafc;
            border-radius: 4px;
            margin-top: 5px;
        }
        
        .batch-table td {
            border: none;
            padding: 4px 6px;
            color: #475569;
        }
        
        .batch-table tr:not(:last-child) {
            border-bottom: 1px solid #e2e8f0;
        }

        .location-tag {
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
            color: #4b5563;
        }
        
        .footer {
            margin-top: 50px;
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
        <h1>تقرير جرد مخزون المستشفى الشامل</h1>
        <div class="meta-info">
            <span>التاريخ: ${currentDate}</span>
            <span>إجمالي الأصناف: ${resultsCount}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40%;">الدواء وتفاصيل المواقع / الدفعات</th>
                <th style="width: 25%;">المعلومات المسجلة</th>
                <th style="width: 17%;">الكمية الكلية</th>
                <th style="width: 18%;">الاحتياج الكلي</th>
            </tr>
        </thead>
        <tbody>
    `;

  if (resultsCount > 0) {
    filteredDrugss.value.forEach((drug) => {
      let batchRows = '';
      if (drug.batches && drug.batches.length > 0) {
        batchRows = `
            <table class="batch-table">
                ${drug.batches.map(batch => `
                    <tr>
                        <td style="width: 30%">
                            <span class="location-tag">
                                [${batch.location}]
                            </span>
                        </td>
                        <td style="width: 25%">دُفعة: <b>${batch.batchNumber || '---'}</b></td>
                        <td style="width: 25%">انتهاء: <b>${batch.expiryDate || '---'}</b></td>
                        <td style="width: 20%; text-align: left;">الكمية: <b>${getBatchQuantityDisplay(batch, drug).replace(/<br>/g, ' ')}</b></td>
                    </tr>
                `).join('')}
            </table>
        `;
      }

      tableHtml += `
        <tr>
            <td>
                <div class="drug-title">${drug.drugName || '---'} <small style="color:#666; font-weight:normal;">(#${drug.drugCode || '---'})</small></div>
                <div class="drug-subtitle">الفئة: ${drug.category || '---'}</div>
                ${batchRows}
            </td>
            <td>
                <div><b>الاسم العلمي:</b> ${drug.genericName || '---'}</div>
                <div style="margin-top: 5px;"><b>تاريخ الصلاحية:</b> ${drug.expiryDate || '---'}</div>
            </td>
            <td style="text-align: center; font-weight: bold; font-size: 14px; color: #2E5077;">
                ${getGenericQuantityDisplay(drug.quantity, drug).replace(/<br>/g, ' ')}
            </td>
            <td style="text-align: center; color: #64748b;">
                ${getGenericQuantityDisplay(drug.neededQuantity, drug).replace(/<br>/g, ' ')}
            </td>
        </tr>
      `;
    });
  } else {
    tableHtml += '<tr><td colspan="4" style="text-align: center; padding: 40px;">لا توجد أدوية متوفرة للجرد حالياً</td></tr>';
  }

  tableHtml += `
        </tbody>
    </table>

    <div class="footer">
        <div class="sig-box">
            <div class="sig-line">توقيع لجنة الجرد والرقابة</div>
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
                                            <th class="drug-code-col px-4 py-3 text-sm font-normal">رمز الدواء</th>
                                            <th class="drug-name-col px-4 py-3 text-sm font-normal">اسم الدواء</th>
                                            <th class="scientific-name-col px-4 py-3 text-sm font-normal">الاسم العلمي</th>
                                            <th class="quantity-col px-4 py-3 text-sm font-normal" colspan="3">الكمية المتوفرة</th>
                                            <th class="needed-quantity-col px-4 py-3 text-sm font-normal" colspan="3">الكمية المحتاجة</th>
                                            <th class="actions-col px-4 py-3 text-sm font-normal">الإجراءات</th>
                                        </tr>
                                        <tr>
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                            <th class="quantity-col bg-[#8abcc0]">الإجمالي</th>
                                            <th class="quantity-col bg-[#8abcc0]">الصيدلية</th>
                                            <th class="quantity-col bg-[#8abcc0]">المستودع</th>
                                            <th class="needed-quantity-col bg-[#8abcc0]">الإجمالي</th>
                                            <th class="needed-quantity-col bg-[#8abcc0]">الصيدلية</th>
                                            <th class="needed-quantity-col bg-[#8abcc0]">المستودع</th>
                                            <th></th>
                                        </tr>
                                    </thead>

                                    <tbody class="text-gray-800">
                                        <tr v-if="isLoading">
                                            <td colspan="10" class="p-4">
                                                <TableSkeleton :rows="5" />
                                            </td>
                                        </tr>
                                        <tr v-else-if="error">
                                            <td colspan="10" class="py-12">
                                                <ErrorState :message="error" :retry="fetchDrugs" />
                                            </td>
                                        </tr>
                                        <template v-else>
                                            <template
                                                v-for="(drug, index) in filteredDrugss"
                                                :key="drug.id || index"
                                            >
                                                <tr
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
                                                    <td :class="getTextColorClass(drug.quantity, drug.neededQuantity)">
                                                        <div v-if="drug.units_per_box > 1">
                                                            <span>{{ drug.quantity_boxes }}</span> {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                        </div>
                                                        <div v-else>
                                                            <span>{{ drug.quantity || 0 }}</span> {{ drug.unit }}
                                                        </div>
                                                    </td>
                                                    <td :class="getTextColorClass(drug.pharmacyQuantity || 0, drug.pharmacyNeededQuantity || 0)">
                                                        <div v-if="drug.units_per_box > 1">
                                                            <span>{{ drug.pharmacy_quantity_boxes }}</span> {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                        </div>
                                                        <div v-else>
                                                            <span>{{ drug.pharmacyQuantity || 0 }}</span> {{ drug.unit }}
                                                        </div>
                                                    </td>
                                                    <td :class="getTextColorClass(drug.warehouseQuantity || 0, drug.warehouseNeededQuantity || 0)">
                                                        <div v-if="drug.units_per_box > 1">
                                                            <span>{{ drug.warehouse_quantity_boxes }}</span> {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                        </div>
                                                        <div v-else>
                                                            <span>{{ drug.warehouseQuantity || 0 }}</span> {{ drug.unit }}
                                                        </div>
                                                    </td>
                                                    <td :class="getTextColorClass(drug.quantity, drug.neededQuantity)">
                                                        <span>{{ drug.needed_quantity_boxes || 0 }}</span> {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                    </td>
                                                    <td :class="getTextColorClass(drug.pharmacyQuantity || 0, drug.pharmacyNeededQuantity || 0)">
                                                        <span>{{ drug.pharmacy_needed_quantity_boxes || 0 }}</span> {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                    </td>
                                                    <td :class="getTextColorClass(drug.warehouseQuantity || 0, drug.warehouseNeededQuantity || 0)">
                                                        <span>{{ drug.warehouse_needed_quantity_boxes || 0 }}</span> {{ drug.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                    </td>
                                                    <td class="actions-col">
                                                        <div class="flex gap-3 justify-center">
                                                            <!-- زر التوسيع لإظهار الدفعات -->
                                                            <button
                                                                v-if="drug.batches && drug.batches.length > 0"
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
                                                                @click="showDrugDetails(drug)"
                                                                class="tooltip p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                                data-tip="عرض التفاصيل"
                                                            >
                                                                <Icon
                                                                    icon="tabler:eye"
                                                                    class="w-4 h-4 text-green-600"
                                                                />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <!-- الصف الموسع لعرض تفاصيل الدفعات -->
                                                <tr v-if="expandedReviewId === (drug.id || index)" class="bg-gray-50/50">
                                                    <td colspan="10" class="p-4 relative">
                                                        <div class="absolute right-8 top-0 w-0.5 h-full bg-[#4DA1A9]/20"></div>
                                                        <div class="pr-8">
                                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                                                <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                                                                    <h4 class="text-[#2E5077] text-sm flex items-center gap-2">
                                                                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                                                        تفاصيل الدفعات والمواقع
                                                                    </h4>
                                                                    <span class="text-xs bg-[#4DA1A9]/10 text-[#4DA1A9] px-2 py-1 rounded-lg">
                                                                        إجمالي الكمية للمستشفى: <span v-html="getGenericQuantityDisplay(drug.quantity, drug)"></span>
                                                                    </span>
                                                                </div>
                                                                    <table class="w-full text-right text-sm table-fixed">
                                                                    <thead class="bg-gray-50/50 text-gray-500 font-medium">
                                                                        <tr>
                                                                            <th class="px-4 py-2 w-[30%] font-normal">الموقع</th>
                                                                            <th class="px-4 py-2 w-[15%] font-normal">رقم الشحنة</th>
                                                                            <th class="px-4 py-2 w-[30%] font-normal text-center">تاريخ الإنتهاء</th>
                                                                            <th class="px-4 py-2 w-[25%] font-normal text-center">الكمية</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="divide-y divide-gray-50">
                                                                        <tr v-for="(batch, bIndex) in drug.batches" :key="bIndex" class="hover:bg-gray-50 transition-colors">
                                                                             <td class="px-4 py-2.5">
                                                                                 <span :class="[
                                                                                     'px-2 py-0.5 rounded-full text-[10px] whitespace-nowrap',
                                                                                     batch.location_type === 'pharmacy' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-amber-100 text-amber-700 border border-amber-200'
                                                                                 ]">
                                                                                     {{ batch.location }}
                                                                                 </span>
                                                                             </td>
                                                                             <td class="px-4 py-2.5 text-gray-700 font-mono text-xs">{{ batch.batchNumber || '---' }}</td>
                                                                             <td class="px-4 py-2.5 text-gray-700 font-mono tracking-wide text-center text-xs" dir="ltr">{{ batch.expiryDate }}</td>
                                                                             <td class="px-4 py-2.5 text-[#4DA1A9] text-center" v-html="getBatchQuantityDisplay(batch, drug)"></td>
                                                                        </tr>
                                                                        <tr v-if="!drug.batches || drug.batches.length === 0">
                                                                            <td colspan="4" class="px-4 py-4 text-center text-gray-400 italic">
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
                                                <td colspan="10" class="py-12">
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

        <div class="drawer-side h-full overflow-hidden">
            <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <Sidebar />
        </div>

        <DrugPreviewModal 
            :is-open="isDrugPreviewModalOpen"
            :drug="selectedDrug"
            :is-loading="isDrugDetailsLoading"
            @close="closeDrugPreviewModal"
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

.drug-code-col {
    width: 10%;
}
.drug-name-col {
    width: 25%;
}
.scientific-name-col {
    width: 20%;
}
.quantity-col {
    width: 15%; 
}
.needed-quantity-col {
    width: 20%; 
}
.actions-col {
    width: 10%;
    text-align: center;
}
.min-w-\[700px\] {
    min-width: 700px;
}
</style>