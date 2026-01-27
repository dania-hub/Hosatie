<!-- components/forsuperadmin/EditDrugModal.vue -->
<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[1001] flex items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity duration-300"
        @click.self="closeModal"
    >
        <div
            class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto"
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
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تعديل بيانات الدواء
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-8 space-y-8">
                <!-- Basic Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-6">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:document-text-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                        المعلومات الأساسية
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- اسم الدواء -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon :icon="getDrugIconDynamic(formData.unit || formData.form)" class="w-4 h-4 text-[#4DA1A9]" />
                                اسم الدواء <span class="text-red-500">*</span>
                            </label>
                            <Input
                                v-model="formData.name"
                                type="text"
                                required
                                placeholder="أدخل اسم الدواء"
                                :class="[
                                    'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right font-medium',
                                    errors.name ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                                ]"
                                @input="validateField('name')"
                            />
                            <p v-if="errors.name" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.name }}
                            </p>
                        </div>

                        <!-- الاسم العلمي -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:atom-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الاسم العلمي <span class="text-red-500">*</span>
                            </label>
                            <Input
                                v-model="formData.generic_name"
                                type="text"
                                required
                                placeholder="أدخل الاسم العلمي"
                                :class="[
                                    'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right font-medium',
                                    errors.generic_name ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                                ]"
                                @input="validateField('generic_name')"
                            />
                            <p v-if="errors.generic_name" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.generic_name }}
                            </p>
                        </div>

                        <!-- التركيز -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:weight-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                التركيز <span class="text-red-500">*</span>
                            </label>
                            <Input
                                v-model="formData.strength"
                                type="text"
                                required
                                placeholder="مثال: 500mg"
                                :class="[
                                    'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right font-medium',
                                    errors.strength ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                                ]"
                                @input="validateField('strength')"
                            />
                            <p v-if="errors.strength" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.strength }}
                            </p>
                        </div>

                        <!-- الشكل الصيدلاني -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:pill-2-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الشكل الصيدلاني <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model="formData.form"
                                    required
                                    :class="[
                                        'w-full h-9 px-4 pr-10 border rounded-xl bg-white text-right font-medium transition-all duration-200 appearance-none cursor-pointer',
                                        'focus:outline-none focus:ring-1',
                                        errors.form 
                                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                                            : 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20'
                                    ]"
                                    @change="validateField('form')"
                                >
                                    <option value="">اختر الشكل الصيدلاني</option>
                                    <option value="أقراص">أقراص</option>
                                    <option value="كبسولات">كبسولات</option>
                                    <option value="شراب">شراب</option>
                                    <option value="حقن">حقن</option>
                                    <option value="مرهم">مرهم</option>
                                    <option value="كريم">كريم</option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-4 h-4 text-[#4DA1A9] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            </div>
                            <p v-if="errors.form" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.form }}
                            </p>
                        </div>

                        <!-- الفئة العلاجية -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:medical-kit-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الفئة العلاجية <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model="formData.category"
                                    required
                                    :class="[
                                        'w-full h-9 px-4 pr-10 border rounded-xl bg-white text-right font-medium transition-all duration-200 appearance-none cursor-pointer',
                                        'focus:outline-none focus:ring-1',
                                        errors.category 
                                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                                            : 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20'
                                    ]"
                                    @change="validateField('category')"
                                >
                                    <option value="">اختر الفئة العلاجية</option>
                                    <option value="أدوية أمراض القلب والأوعية الدموية">أدوية أمراض القلب والأوعية الدموية</option>
                                    <option value="أدوية السكري">أدوية السكري</option>
                                    <option value="أدوية الأمراض الصدرية والجهاز التنفسي">أدوية الأمراض الصدرية والجهاز التنفسي</option>
                                    <option value="أدوية أمراض الجهاز الهضمي">أدوية أمراض الجهاز الهضمي</option>
                                    <option value="أدوية الأمراض العصبية">أدوية الأمراض العصبية</option>
                                    <option value="أدوية الصحة النفسية">أدوية الصحة النفسية</option>
                                    <option value="المضادات الحيوية ومضادات الميكروبات">المضادات الحيوية ومضادات الميكروبات</option>
                                    <option value="مسكنات الألم ومضادات الالتهاب">مسكنات الألم ومضادات الالتهاب</option>
                                    <option value="أدوية أمراض الكلى والمسالك البولية">أدوية أمراض الكلى والمسالك البولية</option>
                                    <option value="أدوية أمراض الدم">أدوية أمراض الدم</option>
                                    <option value="أدوية الغدد الصماء والهرمونات">أدوية الغدد الصماء والهرمونات</option>
                                    <option value="أدوية الأمراض الجلدية">أدوية الأمراض الجلدية</option>
                                    <option value="أدوية علاج الأورام (العلاج الكيميائي)">أدوية علاج الأورام (العلاج الكيميائي)</option>
                                    <option value="الفيتامينات والمكملات الغذائية">الفيتامينات والمكملات الغذائية</option>
                                    <option value="أدوية الحساسية">أدوية الحساسية</option>
                                    <option value="أدوية العيون">أدوية العيون</option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-4 h-4 text-[#4DA1A9] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            </div>
                            <p v-if="errors.category" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.category }}
                            </p>
                        </div>

                        <!-- الوحدة -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:ruler-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الوحدة <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model="formData.unit"
                                    required
                                    :class="[
                                        'w-full h-9 px-4 pr-10 border rounded-xl bg-white text-right font-medium transition-all duration-200 appearance-none cursor-pointer',
                                        'focus:outline-none focus:ring-1',
                                        errors.unit 
                                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                                            : 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20'
                                    ]"
                                    @change="validateField('unit')"
                                >
                                    <option value="">اختر الوحدة</option>
                                    <option value="قرص">قرص</option>
                                    <option value="مل">مل</option>
                                    <option value="حقنة">حقنة</option>
                                    <option value="جرام">جرام</option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-4 h-4 text-[#4DA1A9] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            </div>
                            <p v-if="errors.unit" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.unit }}
                            </p>
                        </div>

                        <!-- الجرعة الشهرية القصوى -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:calendar-mark-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الجرعة الشهرية القصوى <span class="text-red-500">*</span>
                            </label>
                            <Input
                                v-model="formData.max_monthly_dose"
                                type="number"
                                required
                                min="1"
                                placeholder="أدخل الجرعة الشهرية القصوى"
                                :class="[
                                    'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right font-medium',
                                    errors.max_monthly_dose ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                                ]"
                                @input="validateField('max_monthly_dose')"
                            />
                            <p v-if="errors.max_monthly_dose" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.max_monthly_dose }}
                            </p>
                        </div>

                        <!-- الحالة -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:check-circle-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الحالة <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model="formData.status"
                                    required
                                    :class="[
                                        'w-full h-9 px-4 pr-10 border rounded-xl bg-white text-right font-medium transition-all duration-200 appearance-none cursor-pointer',
                                        'focus:outline-none focus:ring-1',
                                        errors.status 
                                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                                            : 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20'
                                    ]"
                                    @change="validateField('status')"
                                >
                                    <option value="">اختر الحالة</option>
                                    <option value="متوفر">متوفر</option>
                                    <option value="غير متوفر">غير متوفر</option>
                                    <option value="تم الصرف">تم الصرف</option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-4 h-4 text-[#4DA1A9] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            </div>
                            <p v-if="errors.status" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.status }}
                            </p>
                        </div>

                        <!-- الشركة المصنعة -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:factory-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الشركة المصنعة <span class="text-red-500">*</span>
                            </label>
                            <Input
                                v-model="formData.manufacturer"
                                type="text"
                                required
                                placeholder="أدخل اسم الشركة المصنعة"
                                :class="[
                                    'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right font-medium',
                                    errors.manufacturer ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                                ]"
                                @input="validateField('manufacturer')"
                            />
                            <p v-if="errors.manufacturer" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.manufacturer }}
                            </p>
                        </div>

                        <!-- الدولة -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:global-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الدولة <span class="text-red-500">*</span>
                            </label>
                            <Input
                                v-model="formData.country"
                                type="text"
                                required
                                placeholder="أدخل الدولة"
                                :class="[
                                    'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right font-medium',
                                    errors.country ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                                ]"
                                @input="validateField('country')"
                            />
                            <p v-if="errors.country" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.country }}
                            </p>
                        </div>

                        <!-- نوع الاستخدام -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:heart-pulse-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                نوع الاستخدام <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model="formData.utilization_type"
                                    required
                                    :class="[
                                        'w-full h-9 px-4 pr-10 border rounded-xl bg-white text-right font-medium transition-all duration-200 appearance-none cursor-pointer',
                                        'focus:outline-none focus:ring-1',
                                        errors.utilization_type 
                                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                                            : 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20'
                                    ]"
                                    @change="validateField('utilization_type')"
                                >
                                    <option value="">اختر نوع الاستخدام</option>
                                    <option value="مزمن">مزمن</option>
                                    <option value="حاد">حاد</option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-4 h-4 text-[#4DA1A9] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            </div>
                            <p v-if="errors.utilization_type" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.utilization_type }}
                            </p>
                        </div>

                        <!-- عدد الوحدات في العلبة/العبوة -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:box-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                عدد {{ formData.unit === 'مل' ? 'المليات في العبوة' : 'الحبات في العلبة' }} <span class="text-red-500">*</span>
                            </label>
                            <Input
                                v-model="formData.units_per_box"
                                type="number"
                                required
                                min="1"
                                placeholder="مثال: 30"
                                :class="[
                                    'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right font-medium',
                                    errors.units_per_box ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                                ]"
                                @input="validateField('units_per_box')"
                            />
                            <p v-if="errors.units_per_box" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ errors.units_per_box }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Medical Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-6">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:medical-case-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                        المعلومات الطبية
                    </h3>
                    
                    <!-- دواعي الاستعمال -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:heart-pulse-2-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            دواعي الاستعمال <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="formData.indications"
                            rows="4"
                            required
                            placeholder="أدخل دواعي الاستعمال..."
                            :class="[
                                'w-full px-4 py-2.5 border rounded-xl bg-white text-right font-medium transition-all duration-200 resize-none',
                                'focus:outline-none focus:ring-1',
                                errors.indications 
                                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                                    : 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20'
                            ]"
                            @input="validateField('indications')"
                        ></textarea>
                        <p v-if="errors.indications" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ errors.indications }}
                        </p>
                    </div>

                    <!-- موانع الاستعمال -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:forbidden-circle-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            موانع الاستعمال
                        </label>
                        <textarea
                            v-model="formData.contraindications"
                            rows="4"
                            placeholder="أدخل موانع الاستعمال..."
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl bg-white text-right font-medium focus:outline-none focus:ring-1 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 transition-all duration-200 resize-none"
                        ></textarea>
                    </div>

                    <!-- تحذيرات هامة -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:danger-triangle-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            تحذيرات هامة <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="formData.warnings"
                            rows="4"
                            required
                            placeholder="أدخل التحذيرات الهامة..."
                            :class="[
                                'w-full px-4 py-2.5 border rounded-xl bg-white text-right font-medium transition-all duration-200 resize-none',
                                'focus:outline-none focus:ring-1',
                                errors.warnings 
                                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                                    : 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20'
                            ]"
                            @input="validateField('warnings')"
                        ></textarea>
                        <p v-if="errors.warnings" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ errors.warnings }}
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                    <button
                        type="button"
                        class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                        @click="closeModal"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="isSubmitting || !isFormValid"
                    >
                        <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                        <span v-if="isSubmitting">جاري التحديث...</span>
                        <span v-else>تحديث البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch, computed } from "vue";
import { Icon } from "@iconify/vue";
import Input from "@/components/ui/input/Input.vue";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    drug: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['close', 'update-drug']);

const formData = ref({});
const errors = ref({});
const isSubmitting = ref(false);

// التحقق من صحة الحقل
const validateField = (field) => {
    const value = formData.value[field];
    
    if (field === 'max_monthly_dose' || field === 'units_per_box') {
        if (!value || value <= 0) {
            errors.value[field] = 'يجب إدخال قيمة أكبر من صفر';
        } else {
            errors.value[field] = '';
        }
    } else if (field === 'indications' || field === 'warnings') {
        if (!value || value.trim().length < 10) {
            errors.value[field] = 'يجب إدخال نص لا يقل عن 10 أحرف';
        } else {
            errors.value[field] = '';
        }
    } else {
        if (!value || value.trim() === '') {
            errors.value[field] = 'هذا الحقل مطلوب';
        } else {
            errors.value[field] = '';
        }
    }
};

// التحقق من جميع الحقول
const validateAllFields = () => {
    const requiredFields = ['name', 'generic_name', 'strength', 'form', 'category', 'unit', 'max_monthly_dose', 'status', 'manufacturer', 'country', 'utilization_type', 'warnings', 'indications'];
    requiredFields.forEach(field => validateField(field));
};

// التحقق من صحة النموذج
const isFormValid = computed(() => {
    const requiredFields = ['name', 'generic_name', 'strength', 'form', 'category', 'unit', 'max_monthly_dose', 'status', 'manufacturer', 'country', 'utilization_type', 'warnings', 'indications'];
    
    for (const field of requiredFields) {
        const value = formData.value[field];
        if (!value || (typeof value === 'string' && value.trim() === '')) {
            return false;
        }
        if (field === 'max_monthly_dose' || field === 'units_per_box') {
            if (value <= 0) return false;
        }
        if (field === 'indications' || field === 'warnings') {
            if (value.trim().length < 10) return false;
        }
    }
    
    return true;
});

// عند تغيير الدواء أو فتح النافذة، نسخ البيانات
watch(() => [props.isOpen, props.drug], ([isOpen, drug]) => {
    if (isOpen && drug) {
        formData.value = {
            id: drug.id,
            name: drug.name || drug.drugName || '',
            generic_name: drug.generic_name || drug.genericName || drug.scientificName || '',
            strength: drug.strength || '',
            form: drug.form || '',
            category: drug.category || drug.therapeuticClass || '',
            unit: drug.unit || '',
            max_monthly_dose: (drug.max_monthly_dose !== undefined && drug.max_monthly_dose !== null) ? drug.max_monthly_dose : ((drug.maxMonthlyDose !== undefined && drug.maxMonthlyDose !== null) ? drug.maxMonthlyDose : ''),
            status: drug.status || '',
            manufacturer: drug.manufacturer || '',
            country: drug.country || '',
            utilization_type: drug.utilization_type || '',
            warnings: drug.warnings || '',
            indications: drug.indications || '',
            contraindications: drug.contraindications || drug.contra_indications || '',
            units_per_box: drug.units_per_box || drug.unitsPerBox || 1,
        };
        // التحقق الفوري لإظهار الحقول الناقصة في البيانات القديمة
        setTimeout(() => validateAllFields(), 100); 
        errors.value = {};
    }
}, { immediate: true });

// إغلاق النافذة
const closeModal = () => {
    emit('close');
};

// إرسال النموذج
const submitForm = async () => {
    validateAllFields();
    
    if (!isFormValid.value) {
        return;
    }
    
    isSubmitting.value = true;
    
    try {
        emit('update-drug', formData.value);
        emit('close');
    } catch (error) {
        console.error('Error updating drug:', error);
    } finally {
        isSubmitting.value = false;
    }
};

const getDrugIconDynamic = (unit) => {
    if (!unit) return 'solar:pill-bold-duotone';
    const u = unit.toLowerCase();
    if (u === 'حقنة' || u === 'إبرة') return 'solar:syringe-bold-duotone';
    if (u === 'جرام' || u === 'قنينة' || u === 'مل') return 'solar:bottle-bold-duotone';
    return 'solar:pill-bold-duotone';
};
</script>
