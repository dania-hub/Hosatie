<script setup>
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    hospital: Object
});

const emit = defineEmits(['close']);
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

       <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform transition-all scale-100"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:hospital-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    عرض بيانات المستشفى
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- رقم المستشفى -->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hashtag-square-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم المستشفى
                        </Label>
                        <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-mono font-bold h-10 flex items-center">
                            {{ hospital.id }}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hashtag-square-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            كود المستشفى
                        </Label>
                        <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-mono font-bold h-10 flex items-center">
                            {{ hospital.code }}
                        </div>
                    </div>

                    <!-- اسم المستشفى -->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hospital-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم المستشفى
                        </Label>
                        <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-bold h-10 flex items-center">
                            {{ hospital.name }}
                        </div>
                    </div>

                    <!-- المدينة -->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:city-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المدينة
                        </Label>
                        <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-bold h-10 flex items-center">
                            {{ hospital.city || 'غير محدد' }}
                        </div>
                    </div>
                    
                    <!--العنوان-->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:map-point-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                       العنوان
                        </Label>
                        <div class="bg-white border border-gray-200 rounded-xl p-3 text-gray-700 h-10 flex items-center">
                           {{ hospital.address || 'لا يوجد عنوان' }}
                        </div>
                    </div>

                    <!-- رقم الهاتف -->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف
                        </Label>
                        <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-mono font-bold h-10 flex items-center">
                            {{ hospital.phone || 'لا يوجد' }}
                        </div>
                    </div>

                  


                    <!-- فاصل -->
                    <div class="md:col-span-2 border-t border-gray-200 my-2"></div>

                    <!-- معلومات المدير -->
                    <template v-if="hospital.admin">
                        <div class="md:col-span-2 space-y-4 p-4 rounded-xl ">
                            <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2 mb-4">
                                <Icon icon="solar:user-id-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                                معلومات مدير المستشفى
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- اسم مدير المستشفى -->
                                <div class="space-y-2">
                                    <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                        <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                        اسم المدير
                                    </Label>
                                    <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-bold h-10 flex items-center">
                                        {{ hospital.admin?.name || hospital.managerNameDisplay || 'غير محدد' }}
                                    </div>
                                </div>

                             

                                <!-- بريد المدير -->
                                <div class="space-y-2">
                                    <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                        <Icon icon="solar:letter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                        البريد الإلكتروني
                                    </Label>
                                    <div class="bg-white border border-gray-200 rounded-xl p-3 text-gray-700 font-mono h-10 flex items-center">
                                        {{ hospital.admin?.email || 'لا يوجد' }}
                                    </div>
                                </div>

                                <!-- هاتف مدير المستشفى -->
                                <div class="space-y-2">
                                    <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                        <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                        رقم الهاتف
                                    </Label>
                                    <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-mono font-bold h-10 flex items-center">
                                        {{ hospital.admin?.phone || 'لا يوجد' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <template v-else>
                        <div class=" space-y-2">
                            <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:user-id-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                مدير المستشفى
                            </Label>
                            <div class="bg-white border border-gray-200 rounded-xl p-3 text-gray-500 italic h-10 flex items-center">
                                لا يوجد مدير معين
                            </div>
                        </div>
                    </template>

                    <!-- معلومات المورد -->
                    <template v-if="hospital.supplier">
                        <div class="md:col-span-2 space-y-4 p-4">
                            <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2 mb-4">
                                <Icon icon="solar:box-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                                معلومات المورد
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- اسم المورد -->
                                <div class="space-y-2">
                                    <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                        <Icon icon="solar:box-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                        اسم المورد
                                    </Label>
                                    <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-bold h-10 flex items-center">
                                        {{ hospital.supplier?.name || hospital.supplierNameDisplay || 'غير محدد' }}
                                    </div>
                                </div>

                             

                             
                                <!-- هاتف المورد -->
                                <div class="space-y-2">
                                    <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                        <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                        رقم الهاتف
                                    </Label>
                                    <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-mono font-bold h-10 flex items-center">
                                        {{ hospital.supplier?.phone || 'لا يوجد' }}
                                    </div>
                                </div>

                                <!-- عنوان المورد -->
                                <div class="space-y-2 ">
                                    <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                        <Icon icon="solar:map-point-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                        العنوان
                                    </Label>
                                    <div class="bg-white border border-gray-200 rounded-xl p-3 text-gray-700 h-10 flex items-center">
                                        {{ hospital.supplier?.address || 'لا يوجد عنوان' }}
                                    </div>
                                </div>

                                <!-- مدينة المورد -->
                                <div class="space-y-2">
                                    <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                        <Icon icon="solar:city-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                        المدينة
                                    </Label>
                                    <div class="bg-white border border-gray-200 rounded-xl p-3 text-[#2E5077] font-bold h-10 flex items-center">
                                        {{ hospital.supplier?.city || 'غير محدد' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <template v-else>
                        <div class=" space-y-2">
                            <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:box-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                المورد
                            </Label>
                            <div class="bg-white border border-gray-200 rounded-xl p-3 text-gray-500 italic h-10 flex items-center">
                                لا يوجد مورد معين
                            </div>
                        </div>
                    </template>

                    <!-- حالة المستشفى -->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:shield-check-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            حالة المستشفى
                        </Label>
                        <div class="flex items-center h-10">
                            <span
                                :class="[
                                    'px-4 py-1.5 rounded-xl text-sm font-bold flex items-center gap-2',
                                    hospital.isActive
                                        ? 'bg-green-100 text-green-700 border border-green-200'
                                        : 'bg-red-100 text-red-700 border border-red-200',
                                ]"
                            >
                                <Icon :icon="hospital.isActive ? 'solar:check-circle-bold' : 'solar:close-circle-bold'" class="w-5 h-5" />
                                {{ hospital.isActive ? "مفعل" : "معطل" }}
                            </span>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        @click="$emit('close')"
                        class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                    >
                        إغلاق
                    </button>



                </div>
            </div>
        </div>
    </div>
</template>