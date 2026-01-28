<script setup>
import TableSkeleton from "@/components/Shared/TableSkeleton.vue";
import ErrorState from "@/components/Shared/ErrorState.vue";
import EmptyState from "@/components/Shared/EmptyState.vue";

import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import DefaultLayout from "@/components/DefaultLayout.vue";
import search from "@/components/search.vue";
import btnprint from "@/components/btnprint.vue";
import DrugPreviewModal from "@/components/forsuperadmin/DrugPreviewModal.vue";
import AddDrugModal from "@/components/forsuperadmin/AddDrugModal.vue";
import EditDrugModal from "@/components/forsuperadmin/EditDrugModal.vue";
import Toast from "@/components/Shared/Toast.vue";

// ----------------------------------------------------
// 0. نظام التنبيهات - يجب تعريفه قبل الاستخدام
// ----------------------------------------------------
const toast = ref({
    show: false,
    type: 'success',
    title: '',
    message: ''
});

const showSuccessAlert = (message) => {
  toast.value = {
    show: true,
    type: 'success',
    title: 'نجاح',
    message: message
  };
  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
};

const showErrorAlert = (message) => {
  toast.value = {
    show: true,
    type: 'error',
    title: 'خطأ',
    message: message
  };
  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
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
const dateFrom = ref("");
const dateTo = ref("");
const showDateFilter = ref(false);
const sortKey = ref("quantity");
const sortOrder = ref("asc");

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
        comparison = (Number(a.quantity) || 0) - (Number(b.quantity) || 0);
      } else if (sortKey.value === "manufacturer") {
        comparison = (a.manufacturer || "").localeCompare(b.manufacturer || "", "ar");
      } else if (sortKey.value === "unitsPerBox") {
        comparison = (Number(a.units_per_box) || Number(a.unitsPerBox) || 0) - (Number(b.units_per_box) || Number(b.unitsPerBox) || 0);
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
    ...drug, // Move spread here
    id: drug.id,
    drugCode: drug.id?.toString() || '', // استخدام ID كرمز الدواء
    drugName: drug.name || '',
    scientificName: drug.genericName || drug.generic_name || '',
    therapeuticClass: drug.category || '',
    manufacturer: drug.manufacturer || '',
    country: drug.country || '',
    form: drug.form || '',
    strength: drug.strength || '',
    unit: drug.unit || '',
    status: drug.status || '',
    max_monthly_dose: drug.max_monthly_dose || drug.maxMonthlyDose || 0,
    quantity: drug.quantity || 0, 
    neededQuantity: drug.neededQuantity || 0, 
    units_per_box: drug.units_per_box || drug.unitsPerBox || 1,
    indications: drug.indications || '',
    contraindications: drug.contraindications || drug.contra_indications || '',
    createdAt: drug.created_at || drug.createdAt || '',
    utilization_type: drug.utilization_type || drug.utilizationType || '',
    is_discontinued: ['غير متوفر', 'قيد الإيقاف التدريجي', 'مؤرشف'].includes(drug.status) || drug.is_discontinued === true,
    is_phasing_out: drug.status === 'قيد الإيقاف التدريجي',
    is_archived: drug.status === 'مؤرشف',
  };
};

// وظيفة لتحديد أيقونة الدواء بناءً على الوحدة
const getDrugIconDynamic = (unit) => {
    if (!unit) return 'solar:pill-bold-duotone';
    const u = unit.toLowerCase();
    if (u === 'حقنة' || u === 'إبرة') return 'solar:syringe-bold-duotone';
    if (u === 'جرام' || u === 'قنينة' || u === 'مل') return 'solar:bottle-bold-duotone';
    return 'solar:pill-bold-duotone';
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
      showSuccessAlert(" تم تحميل قائمة الأدوية بنجاح");
      // إخفاء الإشعار تلقائياً بعد 3 ثوانٍ
      setTimeout(() => {
        toast.value.show = false;
      }, 3000);
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
    showSuccessAlert(" تم إضافة الدواء بنجاح");
    
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
    
    showSuccessAlert(" تم تحديث بيانات الدواء بنجاح");
    
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

// تأكيد الحذف - فتح نافذة التأكيد مع اختيار السياسة
const confirmDeleteDrug = (drugId) => {
  drugToDelete.value = drugId;
  isDeleteConfirmationModalOpen.value = true;
};

// إيقاف دواء (discontinue) - دائماً بسياسة الصرف حتى نفاذ الكمية
const discontinueDrug = async () => {
  if (!drugToDelete.value) return;
  
  const drugId = drugToDelete.value;
  isLoading.value = true;
  
  try {
    const response = await api.patch(`/super-admin/drugs/${drugId}/discontinue`, {
      policy: 'dispense_until_zero' // دائماً استخدام سياسة الصرف حتى نفاذ الكمية
    });
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
    
    showSuccessAlert(response.data.message || " تم إيقاف الدواء بنجاح");
    
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
    
    showSuccessAlert(" تم إعادة تفعيل الدواء بنجاح");
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

  const printWindow = window.open("", "_blank", "height=800,width=1000");

  if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
    showErrorAlert("❌ فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
    return;
  }

  const printDate = new Date().toLocaleString('ar-LY', { 
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
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        @media print {
            @page { margin: 15mm; size: A4; }
            .no-print { display: none; }
        }
        
        * { box-sizing: border-box; font-family: 'Cairo', sans-serif; }
        body { padding: 0; margin: 0; color: #1e293b; background: white; line-height: 1.5; }
        
        .print-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        /* Header Styling */
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            padding-bottom: 20px; 
            border-bottom: 2px solid #2E5077;
        }
        
        .gov-title { text-align: center; }
        .gov-title h2 { margin: 0; font-size: 20px; font-weight: 800; color: #2E5077; }
        .gov-title p { margin: 5px 0 0; font-size: 14px; color: #64748b; }
        
        .report-title { text-align: center; margin: 20px 0; }
        .report-title h1 { 
            margin: 0; 
            font-size: 24px; 
            color: #1e293b; 
            background: #f1f5f9;
            display: inline-block;
            padding: 10px 40px;
            border-radius: 50px;
        }
        
        /* Stats Summary */
        .summary-box {
            display: grid;
            grid-template-cols: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .stat-item { display: flex; flex-direction: column; }
        .stat-label { font-size: 12px; color: #64748b; font-weight: 600; }
        .stat-value { font-size: 16px; color: #2E5077; font-weight: 700; }
        
        /* Table Styling */
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        th { 
            background-color: #2E5077; 
            color: white; 
            font-weight: 700; 
            padding: 12px 15px; 
            text-align: right; 
            border: none;
            font-size: 13px;
        }
        td { 
            padding: 12px 15px; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 13px; 
            color: #334155;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) { background-color: #f8fafc; }
        
        .badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #64748b;
        }
        
        .signature-box { text-align: left; }
        .signature-line { margin-top: 30px; width: 150px; border-top: 1px solid #cbd5e1; }
        
        .no-data { text-align: center; padding: 50px; color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="page-header">
            <div class="gov-title">
                <h2>وزارة الصحة</h2>
                <p>إدارة الأدوية والمعدات الطبية</p>
            </div>
            <div style="text-align: left">
                <p style="margin: 0; font-size: 12px; color: #64748b;">تاريخ التقرير</p>
                <p style="margin: 3px 0 0; font-weight: 700; color: #1e293b;">${printDate}</p>
            </div>
        </div>

        <div class="report-title">
            <h1>قائمة الأدوية المعتمدة</h1>
        </div>

        <div class="summary-box">
            <div class="stat-item">
                <span class="stat-label">إجمالي الأدوية بالتقرير</span>
                <span class="stat-value">${resultsCount} صنف</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">جهة الاستخراج</span>
                <span class="stat-value">الإدارة العامة - نظام حُصـــتي</span>
            </div>
        </div>

        ${resultsCount > 0 ? `
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th style="width: 100px;">رمز الدواء</th>
                    <th>اسم الدواء</th>
                    <th>الاسم العلمي</th>
                    <th>الفئة العلاجية</th>
                    <th>التركيز</th>
                    <th>الشكل الدوائي</th>
                </tr>
            </thead>
            <tbody>
                ${filteredDrugss.value.map((drug, index) => `
                <tr>
                    <td style="text-align: center; font-weight: 700; color: #64748b;">${index + 1}</td>
                    <td style="font-weight: 700; color: #2E5077;">${drug.drugCode || '-'}</td>
                    <td style="font-weight: 600;">${drug.drugName || '-'}</td>
                    <td style="font-size: 12px; color: #64748b;">${drug.scientificName || '-'}</td>
                    <td>${drug.therapeuticClass || '-'}</td>
                    <td style="font-weight: 600; color: #475569;">${drug.strength || '-'}</td>
                    <td>${drug.form || '-'}</td>
                </tr>
                `).join('')}
            </tbody>
        </table>
        ` : `
        <div class="no-data"> لا توجد بيانات أدوية متاحة للطباعة حالياً </div>
        `}

        <div class="footer">
            <div class="signature-box">
                <p>اعتماد الجهة المصدرة</p>
                <div class="signature-line"></div>
                <p style="font-size: 10px; margin-top: 5px;">ختم وزارة الصحة</p>
            </div>
            <div style="text-align: right;">
                <p>نظام حُصتي لإدارة توزيع الأدوية</p>
                <p style="font-size: 10px; margin-top: 5px;">تم استخراج هذا التقرير آلياً</p>
            </div>
        </div>
    </div>
</body>
</html>
`;

  printWindow.document.write(tableHtml);
  printWindow.document.close();

  printWindow.onload = () => {
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        if (resultsCount > 0) {
          showSuccessAlert(" تم تجهيز التقرير بنجاح للطباعة.");
        }
    }, 200);
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
    <DefaultLayout>
            <main class="flex-1 p-4 sm:p-5 pt-3">
                <!-- المحتوى الرئيسي -->
                <div>
                    <div
                        class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-3 sm:gap-0"
                    >
                        <div class="flex items-center gap-3 w-full sm:max-w-xl">
                     
                                <search v-model="searchTerm" placeholder="ابحث باسم الدواء أو رمز الدواء أو الفئة العلاجية هنا" />
                          



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
                                        حسب حبات العلبة:
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('unitsPerBox', 'asc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'unitsPerBox' &&
                                                    sortOrder === 'asc',
                                            }"
                                        >
                                            الأقل (علب صغيرة)
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            @click="sortDrugs('unitsPerBox', 'desc')"
                                            :class="{
                                                'font-bold text-[#4DA1A9]':
                                                    sortKey === 'unitsPerBox' &&
                                                    sortOrder === 'desc',
                                            }"
                                        >
                                            الأكثر (علب كبيرة)
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
                                <Icon icon="solar:add-circle-bold" class="w-5 h-5 ml-2" />
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
                                                :class="[
                                                    'drug-code-col',
                                                    getTextColorClass(
                                                        drug.quantity,
                                                        drug.neededQuantity
                                                    )
                                                ]"
                                            >
                                                {{ drug.drugCode }}
                                            </td>
                                            <td class="drug-name-col">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-[#2E5077]">{{ drug.drugName }}</span>
                                                    <div class="flex gap-1 mt-1">
                                                        <span v-if="drug.status === 'قيد الإيقاف التدريجي'" class="text-[10px] bg-amber-100 text-amber-700 px-1 py-0.5 rounded-md font-bold w-fit">
                                                            قيد الإيقاف التدريجي
                                                        </span>
                                                        <span v-if="drug.status === 'مؤرشف'" class="text-[10px] bg-gray-100 text-gray-700 px-1 py-0.5 rounded-md font-bold w-fit">
                                                            مؤرشف
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                :class="[
                                                    'scientific-name-col',
                                                    getTextColorClass(
                                                        drug.quantity,
                                                        drug.neededQuantity
                                                    )
                                                ]"
                                            >
                                                {{ drug.scientificName }}
                                            </td>
                                            <td
                                                :class="[
                                                    'category-col',
                                                    getTextColorClass(
                                                        drug.quantity,
                                                        drug.neededQuantity
                                                    )
                                                ]"
                                            >
                                                {{ drug.therapeuticClass }}
                                            </td>
                                            
                                                    <td class="actions-col">
                                                        <div class="flex gap-3 justify-center items-center">
                                                            <!-- زر المعاينة -->
                                                            <button 
                                                                @click="showDrugDetails(drug)"
                                                                class="p-2 rounded-lg bg-green-50 hover:bg-green-100 border border-green-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                                title="معاينة"
                                                            >
                                                                <Icon
                                                                    icon="tabler:eye-minus"
                                                                    class="w-4 h-4 text-green-600"
                                                                />
                                                            </button>

                                                            <!-- زر التعديل -->
                                                            <button 
                                                                @click="openEditDrugModal(drug)"
                                                                class="p-2 rounded-lg bg-yellow-50 hover:bg-yellow-100 border border-yellow-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                                title="تعديل"
                                                            >
                                                                <Icon
                                                                    icon="line-md:pencil"
                                                                    class="w-4 h-4 text-yellow-500"
                                                                />
                                                            </button>

                                                            <!-- زر التفعيل/الإيقاف -->
                                                            <button 
                                                                v-if="drug.is_discontinued"
                                                                @click="reactivateDrug(drug.id)"
                                                                class="p-2 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                                title="إعادة تفعيل"
                                                            >
                                                                <Icon
                                                                    icon="line-md:rotate-270"
                                                                    class="w-4 h-4 text-blue-600"
                                                                />
                                                            </button>
                                                            <button 
                                                                v-else
                                                                @click="confirmDeleteDrug(drug.id)"
                                                                class="p-2 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 transition-all duration-200 hover:scale-110 active:scale-95"
                                                                title="إيقاف"
                                                            >
                                                                <Icon
                                                                    icon="line-md:close-circle"
                                                                    class="w-4 h-4 text-red-600"
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
                        <h3 class="text-xl font-bold text-[#2E5077]">تأكيد إيقاف الدواء تدريجياً</h3>
                        <div class="bg-gradient-to-r from-orange-50 to-amber-50 border-r-4 border-orange-500 p-4 rounded-xl text-right" dir="rtl">
                            <div class="flex items-start gap-3">
                                <Icon icon="solar:info-circle-bold" class="w-6 h-6 text-orange-600 flex-shrink-0 mt-0.5" />
                                <div class="flex-1 space-y-2">
                                    <p class="font-bold text-gray-800 text-sm">سياسة الإيقاف التدريجي:</p>
                                    <ul class="text-xs text-gray-700 space-y-1.5 mr-4">
                                        <li class="flex items-start gap-2">
                                            <span class="text-orange-500 font-bold">•</span>
                                            <span>سيظل الدواء <strong>متاحاً للصرف</strong> حتى نفاذ المخزون بالكامل</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-orange-500 font-bold">•</span>
                                            <span>سيتم <strong>إلغاء جميع الطلبات الخارجية</strong> المعلقة تلقائياً</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-orange-500 font-bold">•</span>
                                            <span>سيتم <strong>إشعار الموظفين</strong> (مديري المستشفيات، الصيادلة، مديري المخازن)</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-orange-500 font-bold">•</span>
                                            <span>سيتم <strong>إشعار المرضى</strong> الذين لديهم وصفات نشطة لهذا الدواء</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-orange-500 font-bold">•</span>
                                            <span>عند وصول المخزون إلى <strong>صفر</strong>، سيتم أرشفة الدواء تلقائياً</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-600 mt-4">
                            هل أنت متأكد من رغبتك في إيقاف هذا الدواء تدريجياً؟
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
                            <Icon icon="solar:forbidden-circle-bold" class="w-5 h-5" />
                            إيقاف الدواء
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
       
    <Toast 
        :show="toast.show" 
        :type="toast.type" 
        :title="toast.title" 
        :message="toast.message" 
        @close="toast.show = false" 
    />
    </DefaultLayout>
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

/* تنسيقات أعمدة الجدول */
.actions-col {
    width: 140px;
    min-width: 140px;
    max-width: 140px;
    text-align: center;
}
.drug-code-col {
    width: 100px;
    min-width: 100px;
}
.drug-name-col {
    width: 180px;
    min-width: 180px;
}
.scientific-name-col {
    width: 180px;
    min-width: 180px;
}
.category-col {
    width: 160px;
    min-width: 160px;
}
.min-w-\[700px\] {
    min-width: 850px;
}

/* تحسينات عامة */
.table th {
    font-weight: 700;
    font-size: 14px;
    padding: 18px 15px !important;
    background-color: #9aced2 !important;
}
.table td {
    font-size: 14px;
    vertical-align: middle;
    padding: 12px 15px !important;
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
