<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div
            @click="closeModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[95vh] overflow-y-auto"
            dir="rtl"
            role="dialog"
            aria-modal="true"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    معالجة الشحنة رقم {{ requestData.shipmentNumber || "..." }}
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                
                <!-- Shipment Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات الشحنة
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">الجهة الطالبة</span>
                            <span class="font-bold text-[#2E5077]">{{ requestData.department || "غير محدد" }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ الطلب</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestData.date) || "غير محدد" }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">الحالة</span>
                            <span class="inline-block px-3 py-1 rounded-lg text-sm font-bold bg-orange-100 text-orange-600">
                                {{ requestData.status || "قيد الاستلام" }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:checklist-minimalistic-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        <span v-if="isProcessing">الكميات المستلمة</span>
                        <span v-else>الأدوية المطلوبة والمخزون المتاح</span>
                    </h3>

                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <div v-if="receivedItems.length > 0" class="divide-y divide-gray-50">
                            <div 
                                v-for="(item, index) in receivedItems" 
                                :key="item.id || index"
                                class="p-5 hover:bg-gray-50/50 transition-colors"
                            >
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                    <!-- Item Info -->
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <Icon icon="solar:pill-bold" class="w-5 h-5 text-[#4DA1A9]" />
                                            <h4 class="font-bold text-[#2E5077] text-lg">{{ item.name }}</h4>
                                            <span v-if="item.dosage" class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-md font-medium">
                                                {{ item.dosage }}
                                            </span>
                                        </div>
                                        
                                        <div class="text-sm text-gray-500 mt-1 flex items-center gap-2 flex-wrap">
                                            <div class="flex items-center gap-2 flex-wrap text-xs">
                                                <div class="px-2 py-1 bg-blue-50 text-blue-600 rounded-lg font-bold border border-blue-100 flex items-center gap-1">
                                                    مطلوب: <span v-html="getFormattedQuantity(item.originalQuantity, item.unit, item.units_per_box)"></span>
                                                </div>
                                                <div v-if="item.sentQuantity !== null" class="px-2 py-1 bg-green-50 text-green-600 rounded-lg font-bold border border-green-100 flex items-center gap-1">
                                                    مرسل: <span v-html="getFormattedQuantity(item.sentQuantity, item.unit, item.units_per_box)"></span>
                                                </div>
                                                
                                                <div 
                                                    v-if="!isProcessing"
                                                    class="flex items-center gap-1 px-2 py-1 rounded-lg border font-bold"
                                                    :class="{
                                                        'bg-green-50 border-green-100 text-green-700': item.availableQuantity >= item.originalQuantity,
                                                        'bg-red-50 border-red-100 text-red-700': item.availableQuantity < item.originalQuantity
                                                    }"
                                                >
                                                    <span class="font-medium text-xs">متوفر:</span>
                                                    <span class="font-bold text-xs" v-html="getFormattedQuantity(item.availableQuantity, item.unit, item.units_per_box)"></span>
                                                    <Icon v-if="item.availableQuantity < item.originalQuantity" icon="solar:danger-circle-bold" class="w-3 h-3" />
                                                </div>
                                            </div>

                                            <!-- Batch Info Display -->
                                            <div class="flex items-center gap-3 mt-3">
                                                <div v-if="item.batchNumber" class="bg-amber-50 text-amber-700 px-3 py-1.5 rounded-xl border border-amber-200 flex items-center gap-2">
                                                    <div class="p-1 bg-amber-200/50 rounded-lg">
                                                        <Icon icon="solar:tag-bold" class="w-4 h-4" />
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] uppercase font-bold text-amber-600 leading-none mb-0.5">رقم الدفعة</span>
                                                        <span class="text-sm font-black">{{ item.batchNumber }}</span>
                                                    </div>
                                                </div>
                                                
                                                <div v-if="item.expiryDate" class="bg-purple-50 text-purple-700 px-3 py-1.5 rounded-xl border border-purple-200 flex items-center gap-2">
                                                    <div class="p-1 bg-purple-200/50 rounded-lg">
                                                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] uppercase font-bold text-purple-600 leading-none mb-0.5">تاريخ الانتهاء</span>
                                                        <span class="text-sm font-black">{{ formatDate(item.expiryDate) }}</span>
                                                    </div>
                                                </div>

                                                <div v-if="!item.batchNumber && !item.expiryDate" class="text-xs text-gray-400 italic flex items-center gap-1">
                                                    <Icon icon="solar:info-circle-linear" class="w-4 h-4" />
                                                    لا توجد بيانات للدفعة أو تاريخ الانتهاء
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex flex-col md:flex-row items-start md:items-center gap-3 w-full md:w-auto">
                                        <!-- عند تأكيد الاستلام: عرض حقل "مستلم" فقط -->
                                        <template v-if="isProcessing">
                                            <div class="flex flex-col items-end gap-2 w-full md:w-auto">
                                                <div class="flex items-center gap-2 bg-gray-50/50 p-1.5 rounded-xl border border-gray-100">
                                                    <template v-if="item.units_per_box > 1">
                                                        <div class="flex flex-col">
                                                            <div class="flex items-center">
                                                                <span class="text-[11px] text-gray-500 font-bold px-1.5">الكمية المستلمة:</span>
                                                                <div class="flex items-center bg-white border border-gray-200 rounded-lg overflow-hidden focus-within:border-[#4DA1A9] transition-all">
                                                                    <input
                                                                        type="number"
                                                                        v-model.number="item.receivedBoxes"
                                                                        class="w-16 h-8 text-center outline-none font-bold text-[#2E5077] text-sm"
                                                                        @input="handleBoxInput(index, 'received')"
                                                                        :disabled="props.isLoading || isConfirming || item.sentQuantity === 0"
                                                                    />
                                                                    <span class="px-1.5 bg-gray-50/50 text-[10px] text-gray-400 font-bold border-r border-gray-100 h-8 flex items-center">
                                                                        {{ item.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="text-[10px] text-gray-400 mt-0.5 mr-2 font-medium">
                                                                الإجمالي: {{ item.receivedQuantity }} {{ item.unit }}
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-[11px] text-gray-500 font-bold px-1.5">الكمية المستلمة:</span>
                                                        <div class="flex items-center bg-white border border-gray-200 rounded-lg overflow-hidden focus-within:border-[#4DA1A9] transition-all">
                                                            <input
                                                                type="number"
                                                                v-model.number="item.receivedQuantity"
                                                                :max="item.sentQuantity"
                                                                :min="0"
                                                                class="w-20 h-8 text-center outline-none font-bold text-[#2E5077] text-sm"
                                                                @input="validateReceivedQuantity(index, item.sentQuantity)"
                                                                :disabled="props.isLoading || isConfirming || item.sentQuantity === 0"
                                                            />
                                                            <span class="px-2 bg-gray-50/50 text-[10px] text-gray-400 font-bold border-r border-gray-100 h-8 flex items-center">
                                                                {{ item.unit || "وحدة" }}
                                                            </span>
                                                        </div>
                                                    </template>
                                                </div>
                                                
                                                <div 
                                                    v-if="item.sentQuantity > 0"
                                                    class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold"
                                                    :class="{
                                                        'bg-green-100 text-green-700': item.receivedQuantity === item.sentQuantity,
                                                        'bg-amber-100 text-amber-700': item.receivedQuantity > 0 && item.receivedQuantity < item.sentQuantity,
                                                        'bg-red-100 text-red-700': item.receivedQuantity === 0
                                                    }"
                                                >
                                                    <Icon v-if="item.receivedQuantity === item.sentQuantity" icon="solar:check-circle-bold" class="w-4 h-4" />
                                                    <Icon v-else-if="item.receivedQuantity > 0" icon="solar:danger-circle-bold" class="w-4 h-4" />
                                                    <Icon v-else icon="solar:close-circle-bold" class="w-4 h-4" />
                                                    {{ item.receivedQuantity === item.sentQuantity ? 'استلام كامل' : (item.receivedQuantity > 0 ? 'استلام جزئي' : 'لم يتم الاستلام') }}
                                                </div>
                                            </div>
                                        </template>
                                        
                                        <!-- عند الإرسال: عرض الكمية المقترحة والمرسلة -->
                                        <template v-else>
                                            <!-- الكمية المقترحة -->
                                            <div class="flex items-center gap-2 bg-blue-50/50 p-1.5 rounded-xl border border-blue-100">
                                                <label class="text-[11px] font-bold text-blue-600 px-1">
                                                    الكمية المقترحة:
                                                </label>
                                                <div class="w-auto px-3 h-8 flex items-center justify-center bg-white border border-blue-200 rounded-lg font-bold text-blue-700 text-sm shadow-sm">
                                                    <span v-html="getFormattedQuantity(item.suggestedQuantity, item.unit, item.units_per_box)"></span>
                                                </div>
                                            </div>
                                            
                                            <!-- الكمية المرسلة -->
                                            <div class="flex items-center gap-2 bg-gray-50/50 p-1.5 rounded-xl border border-gray-100">
                                                <label :for="`sent-qty-${index}`" class="text-[11px] font-bold text-gray-500 px-1">
                                                    الكمية المرسلة:
                                                </label>
                                                <template v-if="item.units_per_box > 1">
                                                    <div class="flex items-center bg-white border border-gray-200 rounded-lg overflow-hidden focus-within:border-[#4DA1A9]">
                                                        <input
                                                            type="number"
                                                            v-model.number="item.sentBoxes"
                                                            class="w-16 h-8 text-center focus:ring-0 border-none outline-none font-bold text-[#2E5077] text-sm"
                                                            @input="handleBoxInput(index, 'sent', Math.min(item.availableQuantity, item.originalQuantity))"
                                                            :disabled="props.isLoading || isConfirming"
                                                        />
                                                        <span class="px-1.5 text-[10px] text-gray-400 font-bold border-r border-gray-100 bg-gray-50/50 h-8 flex items-center">{{ item.unit === 'مل' ? 'عبوة' : 'علبة' }}</span>
                                                    </div>
                                                    <div class="text-[10px] text-gray-400 mr-1 font-medium">
                                                        ({{ item.sentQuantity }} {{ item.unit }})
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    <div class="flex items-center bg-white border border-gray-200 rounded-lg overflow-hidden focus-within:border-[#4DA1A9]">
                                                        <input
                                                            :id="`sent-qty-${index}`"
                                                            type="number"
                                                            v-model.number="item.sentQuantity"
                                                            :max="Math.min(item.availableQuantity, item.originalQuantity)"
                                                            :min="0"
                                                            class="w-20 h-8 text-center bg-white border-none focus:ring-0 outline-none transition-all font-bold text-[#2E5077] text-sm"
                                                            @input="validateQuantity(index, Math.min(item.availableQuantity, item.originalQuantity))"
                                                            :disabled="props.isLoading || isConfirming"
                                                        />
                                                        <span class="px-2 bg-gray-50/50 text-[10px] text-gray-400 font-bold border-r border-gray-100 h-8 flex items-center">
                                                            {{ item.unit || "وحدة" }}
                                                        </span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-12 text-center text-gray-500">
                            <Icon icon="solar:box-minimalistic-broken" class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            لا توجد أدوية في هذا الطلب
                        </div>
                    </div>
                </div>

                <!-- Rejection Section -->
                <div v-if="showRejectionNote" class="bg-red-50 border border-red-100 rounded-2xl p-6 animate-in fade-in slide-in-from-top-4 duration-300">
                    <h4 class="text-lg font-bold text-red-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                        سبب رفض الطلب
                    </h4>
                    
                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-red-800">
                            يرجى كتابة سبب الرفض (إلزامي) <span class="text-red-600">*</span>
                        </label>
                        
                    <textarea
                        v-model="rejectionNote"
                        placeholder="مثال: نقص في المخزون - طلب غير مطابق للسياسات - بيانات ناقصة..."
                        rows="3"
                        class="w-full p-4 border-2 rounded-xl bg-white text-gray-800 transition-all duration-200 resize-none focus:outline-none focus:ring-4 focus:ring-red-500/10"
                        :class="{
                            'border-red-500 focus:border-red-500': rejectionError,
                            'border-red-200 focus:border-red-400': !rejectionError
                        }"
                        @input="rejectionError = false"
                    ></textarea>
                        
                        <div v-if="rejectionError" class="text-red-600 text-sm flex items-center gap-1 font-medium">
                            <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                            يجب كتابة سبب الرفض قبل الإرسال
                        </div>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div v-else class="space-y-2">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        <span v-if="isProcessing">ملاحظات الاستلام</span>
                        <span v-else>ملاحظات الإرسال</span>
                        <span v-if="(isProcessing && hasShortage) || (!isProcessing && hasZeroQuantityItem)" class="text-sm font-normal text-red-600">* (إجباري لوجود نقص)</span>
                        <span v-else class="text-sm font-normal text-gray-400">(اختياري)</span>
                    </h3>
                    <textarea
                        v-model="additionalNotes"
                        :placeholder="(isProcessing && hasShortage) ? 'يجب إضافة ملاحظات عند وجود نقص في الكميات المستلمة...' : (!isProcessing && hasZeroQuantityItem) ? 'يجب إضافة ملاحظات عند وجود عنصر بكمية مرسلة = 0...' : 'أضف أي ملاحظات حول الشحنة...'"
                        rows="2"
                        class="w-full p-4 bg-white border rounded-xl text-gray-700 focus:ring-2 transition-all resize-none"
                        :class="{
                            'bg-gray-100': isProcessing && !hasShortage,
                            'border-red-500 focus:border-red-500 focus:ring-red-500/20': ((isProcessing && hasShortage) || (!isProcessing && hasZeroQuantityItem)) && notesError,
                            'border-orange-300 focus:border-orange-500 focus:ring-orange-500/20': ((isProcessing && hasShortage) || (!isProcessing && hasZeroQuantityItem)) && !notesError,
                            'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !((isProcessing && hasShortage) || (!isProcessing && hasZeroQuantityItem))
                        }"
                        @input="notesError = false"
                    ></textarea>
                    <div v-if="(isProcessing && hasShortage && notesError)" class="text-red-600 text-sm flex items-center gap-1 font-medium">
                        <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                        يجب إضافة ملاحظات الاستلام عند وجود نقص في الكميات المستلمة
                    </div>
                    <div v-if="(!isProcessing && hasZeroQuantityItem && notesError)" class="text-red-600 text-sm flex items-center gap-1 font-medium">
                        <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                        يجب إضافة ملاحظات الإرسال عند وجود عنصر بكمية مرسلة = 0
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex flex-col-reverse sm:flex-row justify-between gap-3 border-t border-gray-100 sticky bottom-0">
                <div class="flex gap-3 w-full sm:w-auto">
                    <button
                        @click="printConfirmation"
                        class="px-6 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 flex items-center justify-center gap-2 w-full sm:w-auto"
                        :disabled="props.isLoading || isConfirming"
                    >
                        <Icon icon="solar:printer-bold-duotone" class="w-5 h-5" />
                        طباعة
                    </button>
                    <button
                        @click="closeModal"
                        class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                        :disabled="props.isLoading || isConfirming"
                    >
                        إلغاء
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <!-- Rejection Actions -->
                    <template v-if="showRejectionNote">
                        <button
                            @click="cancelRejection"
                            class="px-6 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                            :disabled="isConfirming"
                        >
                            إلغاء الرفض
                        </button>
                        
                        <button
                            @click="confirmRejection"
                            class="px-6 py-2.5 rounded-xl bg-red-500 text-white font-medium hover:bg-red-600 transition-colors duration-200 shadow-lg shadow-red-500/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="isConfirming"
                        >
                            <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            <Icon v-else icon="solar:close-circle-bold" class="w-5 h-5" />
                            تأكيد الرفض
                        </button>
                    </template>

                    <!-- Normal Actions -->
                    <template v-else>
                        <!-- عند تأكيد الاستلام -->
                        <template v-if="isProcessing">
                            <button
                                @click="confirmReceipt"
                                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                                :disabled="props.isLoading || isConfirming"
                            >
                                <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                                <Icon v-else icon="solar:check-read-bold" class="w-5 h-5" />
                                {{ isConfirming ? "جاري التأكيد..." : "تأكيد الاستلام" }}
                            </button>
                        </template>
                        
                        <!-- عند الإرسال -->
                        <template v-else>
                            <button
                                @click="initiateRejection"
                                class="px-6 py-2.5 rounded-xl text-red-500 bg-red-50 border border-red-100 font-medium hover:bg-red-100 transition-colors duration-200 flex items-center justify-center gap-2 w-full sm:w-auto"
                                :disabled="props.isLoading || isConfirming"
                            >
                                <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                                رفض الطلب
                            </button>

                            <button
                                @click="sendShipment"
                                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                                :class="{
                                    'opacity-50 cursor-not-allowed': isAllItemsZero,
                                    'hover:bg-[#3a8c94]': !isAllItemsZero
                                }"
                                :disabled="props.isLoading || isConfirming || isAllItemsZero"
                            >
                                <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                                <Icon v-else icon="solar:plain-bold" class="w-5 h-5" />
                                {{ isConfirming ? "جاري الإرسال..." : "إرسال الشحنة" }}
                            </button>
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    requestData: {
        type: Object,
        default: () => ({
            id: null,
            shipmentNumber: "",
            date: "",
            department: "",
            status: "قيد الاستلام",
            items: [],
        }),
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "send", "reject", "confirm"]);

// البيانات
const receivedItems = ref([]);
const isConfirming = ref(false);
const showRejectionNote = ref(false);
const rejectionNote = ref("");
const rejectionError = ref(false);
const additionalNotes = ref("");
const notesError = ref(false);

// التحقق من حالة الطلب - منع التعديل إذا كان في حالة "قيد الاستلام"
const isProcessing = computed(() => {
    const status = props.requestData.status || "";
    return status === "قيد الاستلام" || status === "approved";
});

// التحقق من أن جميع الأدوية لديها كمية متوفرة أو مرسلة = صفر
const isAllItemsZero = computed(() => {
    if (!receivedItems.value || receivedItems.value.length === 0) {
        return true;
    }
    
    // التحقق من أن جميع الأدوية لديها availableQuantity = 0 أو sentQuantity = 0
    return receivedItems.value.every(item => {
        const availableQty = item.availableQuantity || 0;
        const sentQty = item.sentQuantity || 0;
        return availableQty === 0 && sentQty === 0;
    });
});

// التحقق من وجود عنصر واحد على الأقل بكمية مرسلة = 0
const hasZeroQuantityItem = computed(() => {
    if (!receivedItems.value || receivedItems.value.length === 0) {
        return false;
    }
    
    // التحقق من وجود عنصر واحد على الأقل بكمية مرسلة = 0
    return receivedItems.value.some(item => {
        const sentQty = item.sentQuantity || 0;
        return sentQty === 0;
    });
});

// التحقق من وجود نقص في الكميات المستلمة (الكمية المستلمة < الكمية المرسلة)
const hasShortage = computed(() => {
    if (!receivedItems.value || receivedItems.value.length === 0) {
        return false;
    }
    
    // التحقق من وجود عنصر واحد على الأقل حيث الكمية المستلمة أقل من الكمية المرسلة
    return receivedItems.value.some(item => {
        const receivedQty = item.receivedQuantity || 0;
        const sentQty = item.sentQuantity || 0;
        return receivedQty < sentQty;
    });
});

// إعادة تعيين حالة الرفض عند فتح النموذج
watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            // إعادة تعيين حالة الرفض عند فتح النموذج
            showRejectionNote.value = false;
            rejectionNote.value = "";
            rejectionError.value = false;
            isConfirming.value = false;
            additionalNotes.value = "";
            notesError.value = false;
        }
    }
);

// تهيئة receivedItems
watch(
    () => props.requestData.items,
    (newItems) => {
        if (newItems && newItems.length > 0) {
            console.log('🔍 Processing items for ConfirmationModal:', newItems);
            receivedItems.value = newItems.map((item) => {
                // الحصول على الكمية المتاحة من المخزون - أولوية للقيم الصحيحة من API
                let available = 0;
                if (item.availableQuantity !== undefined && item.availableQuantity !== null) {
                    available = Number(item.availableQuantity);
                } else if (item.stock !== undefined && item.stock !== null) {
                    available = Number(item.stock);
                } else if (item.quantity !== undefined && item.quantity !== null) {
                    available = Number(item.quantity);
                }
                
                // الحصول على الكمية المطلوبة
                let requested = 0;
                if (item.requested_qty !== undefined && item.requested_qty !== null) {
                    requested = Number(item.requested_qty);
                } else if (item.requestedQuantity !== undefined && item.requestedQuantity !== null) {
                    requested = Number(item.requestedQuantity);
                } else if (item.originalQuantity !== undefined && item.originalQuantity !== null) {
                    requested = Number(item.originalQuantity);
                } else if (item.quantity !== undefined && item.quantity !== null) {
                    requested = Number(item.quantity);
                }
                
                // استخدام الكمية المقترحة من الـ API إذا كانت متوفرة
                // الـ API يحسب الكمية المقترحة بناءً على:
                // - إذا كان المخزون كافي لجميع الطلبات: الكمية المطلوبة بالكامل
                // - إذا كان المخزون ناقص: توزيع متساوي حسب نسبة الطلب
                let suggestedQty = 0;
                if (item.suggestedQuantity !== undefined && item.suggestedQuantity !== null) {
                    // استخدام الكمية المقترحة من الـ API مباشرة (الـ API يتأكد من صحة القيمة)
                    suggestedQty = Number(item.suggestedQuantity);
                    // التأكد من أن القيمة صحيحة (للأمان فقط - يجب أن تكون القيمة من API صحيحة)
                    suggestedQty = Math.max(0, Math.min(suggestedQty, available, requested));
                } else {
                    // إذا لم تكن الكمية المقترحة متوفرة من الـ API، استخدام الحد الأدنى كحل احتياطي
                    suggestedQty = Math.max(0, Math.min(requested, available));
                }

                console.log(`📦 Item: ${item.name || item.drug_name || 'غير محدد'}`, {
                    rawItem: item,
                    requested,
                    available,
                    suggestedQty,
                    suggestedQuantityFromAPI: item.suggestedQuantity,
                    availableQuantityFromAPI: item.availableQuantity,
                    stockFromAPI: item.stock,
                    fulfilled_qty: item.fulfilled_qty,
                    fulfilledQty: item.fulfilledQty,
                    fulfilled: item.fulfilled,
                    isProcessing: isProcessing.value
                });

                // عند تأكيد الاستلام (قيد الاستلام)، نستخدم approved_qty (الكمية المرسلة من المستودع)
                // وعند الإرسال، نستخدم suggestedQty كقيمة افتراضية
                let finalSentQty = 0;
                if (isProcessing.value) {
                    // عند تأكيد الاستلام (قيد الاستلام): استخدام fulfilled_qty (الكمية المرسلة من المورد) إذا وجدت
                    // إذا لم توجد (مثل حالات التوافق القديمة)، نستخدم approved_qty
                    if (item.fulfilled_qty !== null && item.fulfilled_qty !== undefined) {
                        finalSentQty = Number(item.fulfilled_qty);
                    } else if (item.fulfilledQty !== null && item.fulfilledQty !== undefined) {
                        finalSentQty = Number(item.fulfilledQty);
                    } else if (item.approved_qty !== null && item.approved_qty !== undefined) {
                        finalSentQty = Number(item.approved_qty);
                    } else if (item.approvedQty !== null && item.approvedQty !== undefined) {
                        finalSentQty = Number(item.approvedQty);
                    } else if (item.sentQuantity !== null && item.sentQuantity !== undefined) {
                        finalSentQty = Number(item.sentQuantity);
                    } else {
                        finalSentQty = 0;
                    }
                    console.log(`✅ Using ${item.fulfilled_qty !== undefined ? 'fulfilled_qty' : 'approved_qty'} for sentQuantity: ${finalSentQty}`);
                } else {
                    // عند الإرسال: استخدام suggestedQty كقيمة افتراضية، أو approved_qty إذا كان موجوداً
                    if (item.approved_qty !== null && item.approved_qty !== undefined) {
                        finalSentQty = Number(item.approved_qty);
                    } else if (item.approvedQty !== null && item.approvedQty !== undefined) {
                        finalSentQty = Number(item.approvedQty);
                    } else if (item.sentQuantity !== null && item.sentQuantity !== undefined) {
                        finalSentQty = Number(item.sentQuantity);
                    } else {
                        finalSentQty = suggestedQty > 0 ? suggestedQty : 0;
                    }
                }
                
                const upb = Number(item.units_per_box || item.unitsPerBox || 1);
                return {
                    id: item.id || item.drugId || item.drug_id,
                    name: item.name || item.drugName || item.drug_name || 'دواء غير محدد',
                    originalQuantity: requested,
                    availableQuantity: available,
                    suggestedQuantity: suggestedQty, // الكمية المقترحة من الـ API
                    sentQuantity: finalSentQty, // استخدام fulfilled_qty عند تأكيد الاستلام، أو suggestedQty عند الإرسال
                    sentBoxes: Math.floor(finalSentQty / upb),
                    receivedQuantity: item.receivedQuantity || item.received_qty || 0, // الكمية المستلمة
                    receivedBoxes: Math.floor((item.receivedQuantity || item.received_qty || 0) / upb),
                    units_per_box: upb,
                    batchNumber: item.batch_number || item.batchNumber || null,
                    expiryDate: item.expiry_date || item.expiryDate || null,
                    unit: item.unit || "حبة",
                    dosage: item.dosage || item.strength || ''
                };
            });
            console.log('✅ Final receivedItems:', receivedItems.value);
        } else {
            receivedItems.value = [];
        }
    },
    { immediate: true, deep: true }
);

// إعادة تعيين isConfirming عند انتهاء التحميل
watch(
    () => props.isLoading,
    (newValue) => {
        if (!newValue) {
            isConfirming.value = false;
        }
    }
);

// دالة تنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return "";
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch {
        return dateString;
    }
};

// التحقق من الكمية المدخلة
const validateQuantity = (index, maxQuantity) => {
    let value = receivedItems.value[index].sentQuantity;

    if (isNaN(value) || value === null) {
        value = 0;
    }
    
    if (value > maxQuantity) {
        value = maxQuantity;
    }
    if (value < 0) {
        value = 0;
    }

    receivedItems.value[index].sentQuantity = Math.floor(value);
    
    // مزامنة العلب
    const item = receivedItems.value[index];
    item.sentBoxes = Math.floor(item.sentQuantity / (item.units_per_box || 1));
};

// التحقق من الكمية المستلمة المدخلة
const validateReceivedQuantity = (index, maxQuantity) => {
    let value = receivedItems.value[index].receivedQuantity;

    if (isNaN(value) || value === null) {
        value = 0;
    }
    
    if (value > maxQuantity) {
        value = maxQuantity;
    }
    if (value < 0) {
        value = 0;
    }

    receivedItems.value[index].receivedQuantity = Math.floor(value);
    
    // مزامنة العلب
    const item = receivedItems.value[index];
    item.receivedBoxes = Math.floor(item.receivedQuantity / (item.units_per_box || 1));
};

const handleBoxInput = (index, type = 'received', maxQuantity = null) => {
    const item = receivedItems.value[index];
    const upb = Number(item.units_per_box || 1);
    
    if (type === 'received') {
        const boxes = Number(item.receivedBoxes || 0);
        item.receivedQuantity = boxes * upb;
        validateReceivedQuantity(index, item.sentQuantity);
    } else if (type === 'sent') {
        const boxes = Number(item.sentBoxes || 0);
        item.sentQuantity = boxes * upb;
        validateQuantity(index, maxQuantity || Math.min(item.availableQuantity, item.originalQuantity));
    }
};

const getFormattedQuantity = (quantity, unit = 'قرص', unitsPerBox = 1) => {
    const qty = Number(quantity || 0);
    const upb = Number(unitsPerBox || 1);
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';

    if (upb > 1) {
        const boxes = Math.floor(qty / upb);
        const remainder = qty % upb;
        
        if (boxes === 0 && qty > 0) return `${qty} ${unit}`;
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += ` <span class="text-[10px] text-gray-400 font-normal">و ${remainder} ${unit}</span>`;
        }
        return display;
    }
    return `${qty} ${unit}`;
};

// بدء عملية الرفض
const initiateRejection = () => {
    showRejectionNote.value = true;
    rejectionNote.value = "";
    rejectionError.value = false;
};

// إلغاء عملية الرفض
const cancelRejection = () => {
    showRejectionNote.value = false;
    rejectionNote.value = "";
    rejectionError.value = false;
};

// تأكيد الرفض
const confirmRejection = () => {
    if (!rejectionNote.value.trim()) {
        rejectionError.value = true;
        return;
    }

    isConfirming.value = true;
    
    const rejectionData = {
        id: props.requestData.id,
        shipmentNumber: props.requestData.shipmentNumber,
        rejectionReason: rejectionNote.value.trim(),
        timestamp: new Date().toISOString()
    };

    emit("reject", rejectionData);
};

// تأكيد الاستلام
const confirmReceipt = async () => {
    // التحقق من صحة الكميات المستلمة
    const hasInvalidQuantity = receivedItems.value.some(
        (item) =>
            item.receivedQuantity === null ||
            item.receivedQuantity === undefined ||
            item.receivedQuantity < 0 ||
            item.receivedQuantity > item.sentQuantity
    );
    
    if (hasInvalidQuantity) {
        alert("يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف، وأنها لا تتجاوز الكمية المرسلة.");
        return;
    }
    
    // التحقق من ملاحظات الاستلام إذا كان هناك نقص (الكمية المستلمة < الكمية المرسلة)
    if (hasShortage.value && !additionalNotes.value.trim()) {
        notesError.value = true;
        return;
    }
    
    const hasItemsReceived = receivedItems.value.some(item => item.receivedQuantity > 0);
    if (receivedItems.value.length > 0 && !hasItemsReceived) {
        if (!confirm("لم تحدد أي كمية مستلمة. هل تريد تأكيد الاستلام بدون كميات؟")) {
            return;
        }
    }

    isConfirming.value = true;
    
    try {
        const confirmationData = {
            receivedItems: receivedItems.value.map(item => ({
                id: item.id,
                name: item.name,
                originalQuantity: item.originalQuantity,
                receivedQuantity: item.receivedQuantity || 0,
                sentQuantity: item.sentQuantity || 0,
                unit: item.unit
            })),
            notes: additionalNotes.value.trim()
        };

        emit("confirm", confirmationData);
    } catch (error) {
        console.error("Error confirming receipt:", error);
        alert("حدث خطأ أثناء تأكيد الاستلام.");
        isConfirming.value = false;
    }
};

// إرسال الشحنة
const sendShipment = async () => {
    // التحقق من صحة الكميات - لا يمكن إرسال أكثر من الكمية المطلوبة أو المتوفرة
    const hasInvalidQuantity = receivedItems.value.some(
        (item) => {
            const maxAllowed = Math.min(item.availableQuantity || 0, item.originalQuantity || 0);
            return item.sentQuantity === null ||
                item.sentQuantity === undefined ||
                item.sentQuantity < 0 ||
                item.sentQuantity > maxAllowed;
        }
    );
    
    if (hasInvalidQuantity) {
        alert("يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف، وأنها لا تتجاوز الكمية المطلوبة أو المتوفرة.");
        return;
    }
    
    // التحقق من ملاحظات الإرسال إذا كان هناك عنصر بكمية مرسلة = 0
    if (hasZeroQuantityItem.value && !additionalNotes.value.trim()) {
        notesError.value = true;
      
        return;
    }
    
    const hasItemsToSend = receivedItems.value.some(item => item.sentQuantity > 0);
    if (receivedItems.value.length > 0 && !hasItemsToSend) {
        if (!confirm("لم تحدد أي كمية للإرسال. هل تريد إرسال شحنة فارغة؟ (يمكنك رفض الطلب بدلاً من ذلك).")) {
            return;
        }
    }

    isConfirming.value = true;
    
    try {
        const itemsToSend = receivedItems.value
            .map((item) => ({
                id: item.id,
                name: item.name,
                requestedQuantity: item.originalQuantity,
                sentQuantity: Number(item.sentQuantity) || 0, // التأكد من أن القيمة رقم وليست null/undefined
                unit: item.unit,
            }));
        
        console.log('📦 Sending shipment data:', {
            itemsToSend,
            receivedItems: receivedItems.value,
            allItemsHaveQuantity: itemsToSend.every(item => item.sentQuantity > 0)
        });
        
        const shipmentData = {
            id: props.requestData.id,
            shipmentNumber: props.requestData.shipmentNumber,
            itemsToSend: itemsToSend,
            notes: additionalNotes.value.trim()
        };

        emit("send", shipmentData);
    } catch (error) {
        console.error("Error preparing shipment data:", error);
        alert("حدث خطأ أثناء تحضير بيانات الشحنة.");
        isConfirming.value = false;
    }
};

// إغلاق النموذج
const closeModal = () => {
    if (!props.isLoading && !isConfirming.value) {
        if (showRejectionNote.value) {
            cancelRejection();
        }
        emit("close");
    }
};

// دالة لتنسيق الكمية بالعبوة للطباعة (نص بدون HTML)
const getFormattedQuantityForPrint = (quantity, unit = 'وحدة', unitsPerBox = 1) => {
    const qty = Number(quantity || 0);
    const upb = Number(unitsPerBox || 1);
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';

    if (upb > 1) {
        const boxes = Math.floor(qty / upb);
        const remainder = qty % upb;
        
        if (boxes === 0 && qty > 0) return `${qty} ${unit}`;
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += ` و ${remainder} ${unit}`;
        }
        return display;
    }
    return `${qty} ${unit}`;
};

// دالة الطباعة
const printConfirmation = () => {
    try {
        const printWindow = window.open('', '_blank', 'height=600,width=800');
        
        if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
            console.error('فشل في فتح نافذة الطباعة. يرجى السماح بفتح النوافذ المنبثقة.');
            return;
        }
        
        const requestData = props.requestData;
        
        // إعداد بيانات الأدوية للطباعة
        const itemsHtml = receivedItems.value.map(item => {
            const requestedQty = item.originalQuantity || 0;
            const sentQty = item.sentQuantity || 0;
            const receivedQty = item.receivedQuantity || 0;
            const unitsPerBox = item.units_per_box || 1;
            const unit = item.unit || 'وحدة';
            
            // استخدام getFormattedQuantityForPrint لعرض الكميات
            const formattedRequested = getFormattedQuantityForPrint(requestedQty, unit, unitsPerBox);
            const formattedSent = getFormattedQuantityForPrint(sentQty, unit, unitsPerBox);
            const formattedReceived = getFormattedQuantityForPrint(receivedQty, unit, unitsPerBox);
            
            // معلومات الدفعة وتاريخ الانتهاء
            let expiryInfo = '-';
            if (item.batchNumber || item.expiryDate) {
                const batchStr = item.batchNumber ? `دفعة: ${item.batchNumber}` : '';
                const expiryStr = item.expiryDate ? `انتهاء: ${formatDate(item.expiryDate)}` : '';
                expiryInfo = [batchStr, expiryStr].filter(Boolean).join(' - ') || '-';
            }
            
            // بناء صف الجدول حسب الحالة
            if (isProcessing.value) {
                // عند تأكيد الاستلام: عرض مطلوب / مرسل / مستلم
                return `
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;">${item.name || 'غير محدد'}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedRequested}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedSent}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedReceived}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 11px;">${expiryInfo}</td>
                    </tr>
                `;
            } else {
                // عند الإرسال: عرض مطلوب / متوفر / مقترح / مرسل
                const availableQty = item.availableQuantity || 0;
                const suggestedQty = item.suggestedQuantity || 0;
                const formattedAvailable = getFormattedQuantityForPrint(availableQty, unit, unitsPerBox);
                const formattedSuggested = getFormattedQuantityForPrint(suggestedQty, unit, unitsPerBox);
                
                return `
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;">${item.name || 'غير محدد'}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedRequested}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedAvailable}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedSuggested}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedSent}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 11px;">${expiryInfo}</td>
                    </tr>
                `;
            }
        }).join('');
        
        // بناء رأس الجدول حسب الحالة
        let tableHeader = '';
        if (isProcessing.value) {
            tableHeader = `
                <thead>
                    <tr>
                        <th>اسم الدواء</th>
                        <th>الكمية المطلوبة</th>
                        <th>الكمية المرسلة</th>
                        <th>الكمية المستلمة</th>
                        <th>الدفعة / تاريخ الانتهاء</th>
                    </tr>
                </thead>
            `;
        } else {
            tableHeader = `
                <thead>
                    <tr>
                        <th>اسم الدواء</th>
                        <th>الكمية المطلوبة</th>
                        <th>المتوفر</th>
                        <th>المقترح</th>
                        <th>الكمية المرسلة</th>
                        <th>الدفعة / تاريخ الانتهاء</th>
                    </tr>
                </thead>
            `;
        }
        
        const printContent = `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="UTF-8">
                <title>طباعة معالجة الشحنة</title>
                <style>
                    body { font-family: 'Arial', sans-serif; direction: rtl; padding: 20px; }
                    h1 { text-align: center; color: #2E5077; margin-bottom: 20px; }
                    h2 { color: #2E5077; margin-top: 20px; }
                    .info-section { margin-bottom: 20px; }
                    .info-row { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; }
                    .info-label { font-weight: bold; color: #666; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
                    th { background-color: #9aced2; font-weight: bold; }
                    .notes-section { background-color: #f0f9ff; padding: 15px; border: 1px solid #4DA1A9; margin-top: 20px; border-radius: 5px; }
                    .rejection-section { background-color: #fee; padding: 15px; border: 1px solid #fcc; margin-top: 20px; border-radius: 5px; }
                    @media print {
                        button { display: none; }
                        @page { margin: 1cm; }
                    }
                </style>
            </head>
            <body>
                <h1>${isProcessing.value ? 'تأكيد استلام الشحنة' : 'معالجة الشحنة'}</h1>
                
                <div class="info-section">
                    <div class="info-row">
                        <span class="info-label">رقم الشحنة:</span>
                        <span>${requestData.shipmentNumber || requestData.id || 'غير محدد'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الجهة الطالبة:</span>
                        <span>${requestData.department || 'غير محدد'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">تاريخ الطلب:</span>
                        <span>${formatDate(requestData.date) || 'غير محدد'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">حالة الطلب:</span>
                        <span>${requestData.status || 'قيد الاستلام'}</span>
                    </div>
                </div>

                ${showRejectionNote.value && rejectionNote.value ? `
                    <div class="rejection-section">
                        <h3 style="color: #dc2626; margin-top: 0;">سبب الرفض</h3>
                        <p>${rejectionNote.value}</p>
                    </div>
                ` : ''}

                <h2>${isProcessing.value ? 'الأدوية المستلمة' : 'الأدوية المطلوبة'}</h2>
                <table>
                    ${tableHeader}
                    <tbody>
                        ${itemsHtml || '<tr><td colspan="6" style="text-align: center;">لا توجد أدوية</td></tr>'}
                    </tbody>
                </table>

                ${additionalNotes.value ? `
                <div class="notes-section">
                    <h3 style="color: #2E5077; margin-top: 0;">${isProcessing.value ? 'ملاحظات الاستلام' : 'ملاحظات الإرسال'}</h3>
                    <p>${additionalNotes.value}</p>
                </div>
                ` : ''}

                <p style="text-align: left; color: #666; font-size: 12px; margin-top: 30px;">
                    تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} ${new Date().toLocaleTimeString('ar-SA')}
                </p>
            </body>
            </html>
        `;
        
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // استخدام setTimeout لضمان تحميل المحتوى قبل الطباعة
        setTimeout(() => {
            if (printWindow && !printWindow.closed) {
                printWindow.focus();
                printWindow.print();
            }
        }, 250);
    } catch (error) {
        console.error('خطأ في عملية الطباعة:', error);
    }
};
</script>

<style scoped>
input[type="number"] {
    -moz-appearance: textfield;
}

.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>