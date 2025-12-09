<script setup>
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    department: Object
});

const emit = defineEmits(['close']);
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
        ></div>

       <div
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full sm:w-150 max-w-[95vw] sm:max-w-[700px] max-h-[95vh] sm:max-h-[90vh] overflow-y-auto transform transition-all duration-300 rtl"
        >
            <div
                class="flex items-center justify-between p-4 sm:pr-6 sm:pl-6 pb-2.5 bg-[#F6F4F0] rounded-t-xl sticky top-0 z-10"
            >
                <h2 class="text-xl sm:text-2xl font-bold text-[#2E5077] flex items-center pt-1.5">
                    <Icon
                        icon="jam:write-f"
                        class="w-7 h-7 sm:w-9 sm:h-9 ml-2 text-[#2E5077]"
                    />
                    عرض بيانات القسم
                </h2>

                <Button
                    @click="$emit('close')"
                    variant="ghost"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900 cursor-pointer"
                >
                    <Icon
                        icon="ri:close-large-fill"
                        class="w-6 h-6 text-[#2E5077] mt-3"
                    />
                </Button>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 space-y-4 sm:space-y-6">
                <!-- المعلومات الأساسية -->
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center">
                        <Icon icon="tabler:building" class="w-5 h-5 ml-2" />
                        المعلومات الأساسية للقسم
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[120px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">رقم القسم</Label>
                            <div class="relative w-full sm:max-w-xs">
                                <Input
                                    readonly
                                    :model-value="department.id"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-gray-50 cursor-default focus:ring-0"
                                />
                            </div>
                        </div>

                       

                        <div class="grid grid-cols-1 sm:grid-cols-[120px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">اسم القسم</Label>
                            <div class="relative w-full sm:max-w-xs">
                                <Input
                                    readonly
                                    :model-value="department.name"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-gray-50 cursor-default focus:ring-0"
                                />
                            </div>
                        </div>

                        
                    </div>
                </div>

                <!-- معلومات المدير -->
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center">
                        <Icon icon="tabler:user" class="w-5 h-5 ml-2" />
                        معلومات مدير القسم
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[120px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">اسم المدير</Label>
                            <div class="relative w-full sm:max-w-xs">
                                <Input
                                    readonly
                                    :model-value="department.managerName || 'لا يوجد'"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-gray-50 cursor-default focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[120px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">رقم ملف المدير</Label>
                            <div class="relative w-full">
                                <Input
                                    readonly
                                    :model-value="department.managerId || 'لا يوجد'"
                                    class="h-9 text-right rounded-2xl w-81 border-[#B8D7D9] bg-gray-50 cursor-default focus:ring-0"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- معلومات الحالة -->
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center">
                        <Icon icon="tabler:info-circle" class="w-5 h-5 ml-2" />
                        معلومات الحالة
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[120px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">حالة القسم</Label>
                            <div class="relative w-full sm:max-w-xs">
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="[
                                            'px-3 py-1 rounded-full text-sm font-semibold',
                                            department.isActive
                                                ? 'bg-green-100 text-green-800 border border-green-200'
                                                : 'bg-red-100 text-red-800 border border-red-200',
                                        ]"
                                    >
                                        {{
                                            department.isActive
                                                ? "مفعل"
                                                : "معطل"
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                       
                    </div>
                </div>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 pt-2 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-[#B8D7D9]">
                <Button @click="$emit('close')" class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]">موافق</Button>
            </div>
        </div>
    </div>
</template>