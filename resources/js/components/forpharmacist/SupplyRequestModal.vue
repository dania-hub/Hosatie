<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 bg-opacity-50 flex items-center justify-center p-4 "
    >
        <div
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full max-w-2xl mx-auto my-10 transform transition-all duration-300 scale-100 opacity-100"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
        >
            <!-- الهيدر -->
            <div
                class="flex justify-between items-center bg-[#F6F4F0] p-4 sm:p-6 border-b border-[#B8D7D9] sticky top-0 rounded-t-2xl z-10"
            >
                <h3
                    id="modal-title"
                    class="text-xl font-bold text-[#2E5077] flex items-center"
                >
                    <Icon
                        icon="tabler:medical-cross"
                        class="w-6 h-6 ml-2 text-[#4DA1A9]"
                    />
                    طلب توريد
                </h3>
                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-gray-600 transition duration-150"
                    :disabled="isLoading || isSubmitting"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <!-- المحتوى -->
            <div class="p-4 sm:pr-6 sm:pl-6 space-y-6 max-h-[70vh] overflow-y-auto">
                
                <!-- حالة التحميل -->
                <div v-if="isLoading" class="text-center py-8">
                    <Icon icon="eos-icons:loading" class="w-12 h-12 text-[#4DA1A9] mx-auto mb-4" />
                    <p class="text-gray-600">جاري تحميل البيانات...</p>
                </div>
                
                <!-- معلومات الدواء -->
                <div v-else class="space-y-4">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center"
                    >
                        <Icon
                            icon="tabler:medical-cross"
                            class="w-5 h-5 ml-2"
                        />
                        معلومات الدواء
                    </h3>

                    <!-- البحث والاختيار -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="flex flex-col gap-1 sm:col-span-1">
                            <label
                                for="drugCategory"
                                class="text-right font-medium text-[#2E5077] pt-2"
                                >الفئة:</label
                            >
                            <select
                                id="drugCategory"
                                v-model="selectedCategory"
                                @change="handleInput"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white cursor-pointer shadow-none text-gray-700"
                                :disabled="isSubmitting"
                            >
                                <option value="">كل الفئات</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1 relative sm:col-span-2">
                            <label
                                for="drug-search"
                                class="text-right font-medium text-[#2E5077] pt-2"
                                >اسم الدواء:</label
                            >
                            <input
                                id="drug-search"
                                type="text"
                                v-model="searchTermDrug"
                                @input="handleInput"
                                @focus="showAllDrugsOnFocus"
                                @blur="hideResults"
                                placeholder="ابدأ بكتابة اسم الدواء"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white shadow-none disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed"
                                :disabled="selectedDrugName.length > 0 || isSubmitting"
                            />

                            <!-- نتائج البحث -->
                            <ul
                                v-if="showResults && uniqueFilteredDrugs.length"
                                class="absolute top-full left-0 right-0 z-10 list-none p-0 m-0 border border-[#4DA1A9] border-t-0 rounded-b-lg max-h-52 overflow-y-auto bg-white shadow-xl"
                            >
                                <li
                                    v-for="drug in uniqueFilteredDrugs"
                                    :key="drug.id"
                                    @mousedown="selectDrug(drug)"
                                    class="p-2.5 px-4 cursor-pointer border-b border-gray-100 text-sm text-[#2E5077] hover:bg-[#EAF3F4]"
                                >
                                    {{ drug.name }}
                                    <span
                                        v-if="drug.mostCommonDosage"
                                        class="text-gray-500 text-xs"
                                        >({{ drug.mostCommonDosage }})</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- الكمية والإضافة -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1 col-span-1">
                            <label
                                :for="quantityInputId"
                                class="text-right font-medium text-[#2E5077] pt-2"
                            >
                                الكمية (<span class="unit-text">{{
                                    quantityUnit
                                }}</span
                                >):
                            </label>
                            <input
                                :id="quantityInputId"
                                type="number"
                                min="0"
                                v-model.number="dailyQuantity"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white shadow-none disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed"
                                placeholder="أدخل الكمية"
                                :disabled="!selectedDrugName || isSubmitting"
                            />
                            <p
                                v-if="quantityError"
                                class="text-xs text-red-500 mt-1 font-semibold"
                            >
                                {{ quantityError }}
                            </p>
                        </div>

                        <div class="pt-9">
                            <button
                                @click="addNewDrug"
                                :disabled="!isCurrentDrugValid || isSubmitting"
                                class="h-11 inline-flex items-center justify-center px-[25px] border-2 border-[#4DA1A9] rounded-[30px] transition-all duration-200 ease-in text-[15px] cursor-pointer text-[#4DA1A9] bg-white hover:bg-[#EAF3F4] disabled:bg-gray-300 disabled:border-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed w-full"
                            >
                                <Icon icon="tabler:plus" class="w-5 h-5 ml-1" />
                                إضافة للقائمة
                            </button>
                        </div>
                    </div>

                    <!-- رسائل التوجيه -->
                    <div
                        v-if="
                            !selectedDrugName &&
                            (searchTermDrug.length > 0 ||
                                selectedCategory.length > 0) &&
                            filteredDrugs.length > 0
                        "
                    >
                        <p
                            class="text-sm p-3 rounded-md border-r-4 border-[#4DA1A9] bg-[#EAF3F4] text-[#2E5077]"
                        >
                            الرجاء اختيار دواء من قائمة النتائج الظاهرة
                            لتحديد الكمية.
                        </p>
                    </div>
                    <div
                        v-else-if="
                            !selectedDrugName &&
                            (searchTermDrug.length > 0 ||
                                selectedCategory.length > 0) &&
                            filteredDrugs.length === 0
                        "
                    >
                        <p
                            class="text-sm p-3 rounded-md border-r-4 border-red-400 bg-red-50 text-red-700"
                        >
                            لا توجد نتائج مطابقة لاسم الدواء في الفئة المختارة.
                        </p>
                    </div>

                    <!-- القائمة المضافة -->
                    <div v-if="dailyDosageList.length > 0" class="mt-8">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:list-details" class="w-5 h-5 ml-2" />
                            الأدوية المضافة للقائمة ({{
                                dailyDosageList.length
                            }})
                        </h3>

                        <div
                            class="p-4 border border-[#B8D7D9] rounded-md bg-[#F6F4F0] max-h-48 overflow-y-auto"
                        >
                            <ul class="list-none p-0 m-0">
                                <li
                                    v-for="(item, index) in dailyDosageList"
                                    :key="index"
                                    class="bg-white p-2.5 px-4 rounded-xl mb-2 flex justify-between items-center text-sm text-[#2E5077] font-medium border border-[#B8D7D9] shadow-sm"
                                >
                                    <span>
                                        **{{ item.name }}** -
                                        {{ item.quantity }} {{ item.unit }}
                                    </span>
                                    <span
                                        class="text-red-600 cursor-pointer text-base opacity-90 hover:opacity-70 transition duration-200"
                                        @click="removeItem(index)"
                                        :class="{'cursor-not-allowed opacity-50': isSubmitting}"
                                        :disabled="isSubmitting"
                                        >❌ حذف</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>
                    <p v-else class="text-center text-gray-500 py-2">
                        لم يتم إضافة أي أدوية بعد.
                    </p>
                </div>
                
                <!-- ملاحظات -->
                <div class="space-y-4 pt-2">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center"
                    >
                        <Icon icon="tabler:notes" class="w-5 h-5 ml-2" />
                        ملاحظات الطلب (اختياري)
                    </h3>
                    <div class="p-2 border border-[#B8D7D9] rounded-xl bg-white">
                        <textarea
                            v-model="requestNotes"
                            rows="3"
                            placeholder="أدخل أي ملاحظات خاصة بطلب التوريد (مثل: حاجة مستعجلة، تفضيلات مورد، إلخ)..."
                            class="w-full px-2 py-2 border-none focus:outline-none text-sm text-[#2E5077] bg-transparent resize-none placeholder-gray-400"
                            :disabled="isSubmitting"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- الأزرار -->
            <div
                class="p-4 sm:pr-6 sm:pl-6 pt-2 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-[#B8D7D9]"
            >
                <button
                    @click="confirmAddition"
                    :disabled="!isReadyToConfirm || isSubmitting"
                    class="inline-flex items-center px-[25px] py-[9px] border-2 h-11 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] border-[#4DA1A9] hover:bg-[#398086] hover:border-[#398086] disabled:bg-gray-300 disabled:border-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed font-medium"
                >
                    <Icon v-if="isSubmitting" icon="eos-icons:loading" class="w-5 h-5 ml-1" />
                    <span v-else>تأكيد القائمة ({{ dailyDosageList.length + (isCurrentDrugValid ? 1 : 0) }})</span>
                </button>

                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border-[#a8a8a8] hover:bg-[#b7b9bb] font-medium"
                    :disabled="isSubmitting"
                >
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Icon } from "@iconify/vue";
import axios from "axios";

// إنشاء axios instance
const api = axios.create({
  baseURL: "http://localhost:3000/api",
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json"
  }
});

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    categories: {
        type: Array,
        default: () => []
    },
    allDrugsData: {
        type: Array,
        default: () => []
    },
    drugsData: {
        type: Array,
        default: () => []
    },
    isLoading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'confirm', 'show-alert']);

// البيانات المحلية
const selectedCategory = ref("");
const searchTermDrug = ref("");
const filteredDrugs = ref([]);
const selectedDrugName = ref("");
const selectedDrugType = ref("");
const dailyQuantity = ref(null);
const showResults = ref(false);
const dailyDosageList = ref([]);
const requestNotes = ref('');
const isSubmitting = ref(false);

// الثوابت
const MAX_PILL_QTY = 1000;
const MAX_LIQUID_QTY = 1000;

// ✅ جلب الأدوية من API
const fetchFilteredDrugs = async () => {
  try {
    const params = {};
    if (selectedCategory.value) {
      params.categoryId = selectedCategory.value;
    }
    if (searchTermDrug.value) {
      params.search = searchTermDrug.value;
    }
    
    const response = await api.get("/drugs/search", { params });
    filteredDrugs.value = response.data;
  } catch (error) {
    console.error("Error fetching filtered drugs:", error);
    filteredDrugs.value = [];
  }
};

// ✅ دالة جديدة لحساب الأدوية التي تحتاج توريد
const getDrugsNeedingSupply = () => {
    if (!props.drugsData || props.drugsData.length === 0) return [];
    
    const drugsToReorder = [];
    
    props.drugsData.forEach((drug) => {
        const neededSupply = drug.neededQuantity - drug.quantity;
        
        if (neededSupply > 0) {
            const drugInfo = props.allDrugsData.find(d => 
                d.name === drug.drugName || d.name.includes(drug.drugName.split(' ')[0])
            );
            
            const drugType = drugInfo ? drugInfo.type : 'Tablet';
            const unit = drugType === 'Liquid' ? 'مل' : 'حبة/قرص';
            
            drugsToReorder.push({
                drugId: drug.id,
                drugCode: drug.drugCode,
                name: drug.drugName,
                currentQuantity: drug.quantity,
                neededQuantity: drug.neededQuantity,
                quantity: neededSupply,
                unit: unit,
                type: drugType,
                expiryDate: drug.expiryDate,
                requestStatus: drug.requestStatus
            });
        }
    });
    
    return drugsToReorder;
};

const quantityUnit = computed(() => {
    if (selectedDrugType.value === "Tablet") return "حبة/قرص";
    if (selectedDrugType.value === "Liquid") return "مل";
    return "وحدة";
});

const quantityInputId = computed(() => {
    return selectedDrugType.value === "Tablet"
        ? "pill-quantity-input"
        : "liquid-quantity-input";
});

const quantityError = computed(() => {
    const quantity = dailyQuantity.value;
    if (quantity === null || quantity === "") return null;

    const numericQuantity = Number(quantity);
    if (isNaN(numericQuantity)) return "الكمية يجب أن تكون رقماً.";

    if (numericQuantity <= 0) {
        return "يجب أن تكون الكمية أكبر من الصفر.";
    }

    if (selectedDrugType.value === "Tablet") {
        if (numericQuantity > MAX_PILL_QTY) {
            return `لا يمكن أن تتجاوز الكمية ${MAX_PILL_QTY} حبة/قرص.`;
        }
        if (!Number.isInteger(numericQuantity)) {
            return "يجب أن يكون عدد الحبات رقماً صحيحاً (غير عشري).";
        }
    } else if (selectedDrugType.value === "Liquid") {
        if (numericQuantity > MAX_LIQUID_QTY) {
            return `لا يمكن أن تتجاوز الكمية ${MAX_LIQUID_QTY} مل.`;
        }
    }

    return null;
});

const isCurrentDrugValid = computed(() => {
    const quantity = dailyQuantity.value;
    const isQuantityValid = quantity !== null && quantity > 0;
    const noQuantityError = quantityError.value === null;

    return (
        selectedDrugName.value.length > 0 &&
        selectedDrugType.value.length > 0 &&
        isQuantityValid &&
        noQuantityError
    );
});

const isReadyToConfirm = computed(() => {
    return dailyDosageList.value.length > 0 || isCurrentDrugValid.value;
});

const uniqueFilteredDrugs = computed(() => {
  const uniqueNames = new Set();
  return filteredDrugs.value.filter(drug => {
    if (uniqueNames.has(drug.name)) return false;
    uniqueNames.add(drug.name);
    return true;
  });
});

// الوظائف
const clearForm = () => {
    selectedCategory.value = "";
    searchTermDrug.value = "";
    selectedDrugName.value = "";
    selectedDrugType.value = "";
    dailyQuantity.value = null;
    dailyDosageList.value = [];
    filteredDrugs.value = [];
    requestNotes.value = '';
    isSubmitting.value = false;
};

const getDrugType = (drugName) => {
    const drugInfo = props.allDrugsData.find(d => 
        d.name.toLowerCase() === drugName.toLowerCase() ||
        drugName.toLowerCase().includes(d.name.toLowerCase())
    );
    return drugInfo ? drugInfo.type : 'Tablet';
};

let debounceTimer;
const handleInput = () => {
    selectedDrugName.value = "";
    selectedDrugType.value = "";
    dailyQuantity.value = null;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(async () => {
        if (searchTermDrug.value.length > 1 || selectedCategory.value) {
            await fetchFilteredDrugs();
            showResults.value = true;
        } else {
            filteredDrugs.value = [];
            showResults.value = false;
        }
    }, 300);
};

const selectDrug = (drug) => {
    searchTermDrug.value = drug.name;
    selectedDrugName.value = drug.name;

    const fullDrugData = props.allDrugsData.find((d) => d.name === drug.name);
    const newDrugType = fullDrugData ? fullDrugData.type : "";
    selectedDrugType.value = newDrugType;

    dailyQuantity.value = null;
    showResults.value = false;
};

const hideResults = () => {
    setTimeout(() => {
        showResults.value = false;
    }, 200);
};

const showAllDrugsOnFocus = async () => {
    if (!searchTermDrug.value && !selectedCategory.value) {
        await fetchFilteredDrugs();
        showResults.value = true;
    } else {
        handleInput();
    }
};

const addNewDrug = () => {
    if (isCurrentDrugValid.value) {
        const drugInfo = props.allDrugsData.find(d => d.name === selectedDrugName.value);
        
        dailyDosageList.value.push({
            drugId: drugInfo?.id || null,
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
        });

        emit('show-alert', `✅ تم إضافة الدواء **${selectedDrugName.value}** إلى قائمة التوريد.`);

        searchTermDrug.value = "";
        selectedCategory.value = "";
        selectedDrugName.value = "";
        selectedDrugType.value = "";
        dailyQuantity.value = null;
    } else {
        const errorMessage =
            quantityError.value ||
            "الرجاء تحديد دواء وإدخال كمية توريد صحيحة أكبر من الصفر.";
        emit('show-alert', `❌ فشل الإضافة: ${errorMessage}`);
    }
};

const removeItem = (index) => {
    dailyDosageList.value.splice(index, 1);
    emit('show-alert', "🗑️ تم حذف الدواء من القائمة بنجاح.");
};

const confirmAddition = async () => {
    if (isCurrentDrugValid.value) {
        const drugInfo = props.allDrugsData.find(d => d.name === selectedDrugName.value);
        
        dailyDosageList.value.push({
            drugId: drugInfo?.id || null,
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
        });
        
        searchTermDrug.value = "";
        selectedCategory.value = "";
        selectedDrugName.value = "";
        selectedDrugType.value = "";
        dailyQuantity.value = null;
    }

    if (dailyDosageList.value.length === 0) {
        emit('show-alert', "⚠️ لا يمكنك التأكيد دون إضافة دواء واحد على الأقل.");
        return;
    }

    isSubmitting.value = true;
    
    try {
        const confirmationData = {
            items: dailyDosageList.value,
            notes: requestNotes.value.trim(),
            totalItems: dailyDosageList.value.length,
            timestamp: new Date().toISOString()
        };
        
        emit('confirm', confirmationData);
        
        setTimeout(() => {
            clearForm();
        }, 500);
        
    } catch (error) {
        console.error('Error submitting supply request:', error);
        emit('show-alert', `❌ فشل في إرسال طلب التوريد: ${error.message}`);
    } finally {
        isSubmitting.value = false;
    }
};

const closeModal = () => {
    if (!isSubmitting.value) {
        clearForm();
        emit('close');
    }
};

watch(() => props.isOpen, async (isOpen) => {
    if (isOpen) {
        clearForm();
        
        const drugsNeedingSupply = getDrugsNeedingSupply();
        
        if (drugsNeedingSupply.length > 0) {
            dailyDosageList.value = drugsNeedingSupply;
            
            requestNotes.value = `توريد تلقائي للأدوية الناقصة - ${new Date().toLocaleDateString('ar-EG')}`;
            
            const totalItems = drugsNeedingSupply.length;
            const totalQuantity = drugsNeedingSupply.reduce((sum, drug) => sum + drug.quantity, 0);
            
            emit('show-alert', 
                `📋 تم إدراج ${totalItems} دواء ناقص تلقائياً (إجمالي ${totalQuantity} وحدة).`
            );
        } else {
            emit('show-alert', "✅ جميع الأدوية متوفرة بالكميات المطلوبة حالياً.");
        }
        
        await fetchFilteredDrugs();
    }
});

// التهيئة الأولية
fetchFilteredDrugs();
</script>

<style scoped>
/* نفس الأنماط السابقة */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}

textarea:focus {
    outline: none;
    box-shadow: none;
}

.max-h-\[70vh\] {
    max-height: 70vh;
}

.max-h-48 {
    max-height: 12rem;
}

.quantity-info {
    font-size: 0.85rem;
    color: #4DA1A9;
    margin-top: 2px;
}

.drug-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}
</style>