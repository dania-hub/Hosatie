<script setup>
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close']);
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[80] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F6F4F0] rounded-xl 
            shadow-2xl w-full sm:w-150 max-w-[95vw] 
            sm:max-w-[700px] max-h-[95vh] sm:max-h-[90vh] 
            overflow-y-auto transform transition-all duration-300 rtl"
        >
            <div
                class="flex items-center justify-between p-4 sm:pr-6 sm:pl-6 pb-2.5 bg-[#F6F4F0] rounded-t-xl sticky top-0 z-10"
            >
                <h2 class="text-xl sm:text-2xl font-bold text-[#2E5077] flex items-center pt-1.5">
                    <Icon
                        icon="tabler:eye"
                        class="w-7 h-7 sm:w-9 sm:h-9 ml-2 text-[#2E5077]"
                    />
                    نموذج عرض البيانات
                </h2>

                <Button
                    @click="$emit('close')"
                    variant="ghost"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900"
                >
                    <Icon
                        icon="ri:close-large-fill"
                        class="w-6 h-6 text-[#2E5077] mt-3"
                    />
                </Button>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 space-y-4 sm:space-y-6">
                
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1">
                        المعلومات الشخصية
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">الرقم الوطني</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    readonly
                                    :model-value="patient.nationalId"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-white cursor-default focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">الإسم رباعي</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    readonly
                                    :model-value="patient.name"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-white cursor-default focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">تاريخ الميلاد</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    readonly
                                    :model-value="patient.birth"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-white cursor-default focus:ring-0"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 pt-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1">
                        معلومات الإتصال
                    </h3>
                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">رقم الهاتف</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    readonly
                                    :model-value="patient.phone"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-white cursor-default focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">البريد الإلكتروني</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    readonly
                                    :model-value="patient.email"
                                    class="h-9 text-right rounded-2xl w-full border-[#B8D7D9] bg-white cursor-default focus:ring-0"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end items-center">
                    <div class="flex gap-3 pb-4">
                        <button
                            @click="$emit('close')"
                            class="inline-flex h-11 items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] 
                            transition-all duration-200 ease-in relative overflow-hidden text-[15px] 
                            cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                        >
                            إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>