<template>
    <div
        dir="rtl"
        class="w-full min-h-screen font-['Inter'] bg-gray-50 overflow-x-hidden"
    >
        <div
            class="flex items-center justify-center min-h-screen p-4 sm:p-8 relative"
        >
            <div
                class="w-full max-w-[470px] grid gap-6 text-center relative custom-container mx-auto"
            >
                <a
                    href="/login"
                    class="absolute top-6 left-6 w-10 h-10 rounded-full flex items-center justify-center text-gray-500 transition-all duration-300 z-20 hover:text-white hover:bg-[#2E5077] hover:scale-105"
                >
                    <Icon icon="mdi:arrow-left" class="w-7 h-7" />
                </a>

                <div
                    class="w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center border-4 border-white shadow-xl bg-white z-20 absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                >
                    <Stethoscope
                        class="w-8 h-8 sm:w-12 sm:h-12 text-[#2E5077]"
                    />
                </div>

                <div class="flex flex-col items-center gap-2 mt-4 sm:mt-0">
                    <h1
                        class="text-2xl sm:text-2xl font-extrabold text-[#2E5077] mt-2 mb-3 sm:mt-0"
                    >
                        نسيت كلمة المرور
                    </h1>
                    <p class="text-gray-500 text-sm">رمز التحقق</p>

                    <p class="text-gray-500 text-sm">
                        يرجى كتابة رمز التحقق المرسل إليك عبر البريد الإلكتروني
                    </p>
                </div>

                <form
                    @submit.prevent="handleVerifyOtp"
                    class="grid gap-4 sm:gap-5 text-right"
                >
                    <div class="grid gap-1">
                        <div class="flex justify-center w-full">
                            <div class="otp-container flex-row-reverse">
                                <input
                                    v-for="(digit, index) in otp"
                                    :key="index"
                                    maxlength="1"
                                    v-model="otp[index]"
                                    @focus="focusFirstEmptyBox"
                                    @input="moveNext(index)"
                                    @keydown="handleKeydown($event, index)"
                                    @keypress="isNumber($event)"
                                    type="text"
                                    pattern="\d*"
                                    inputmode="numeric"
                                    :ref="
                                        (el) => {
                                            if (el) otpInputs[index] = el;
                                        }
                                    "
                                    :class="{ 'border-red-500': otpError }"
                                />
                            </div>
                        </div>
                        <div class="text-center text-sm mt-1">
                            <a
                                href="#"
                                class="inline-block text-[#2E5077] hover:underline transition duration-200 pt-4"
                            >
                                إعادة إرسال رمز التحقق
                            </a>
                        </div>
                        <p
                            v-if="otpError"
                            class="text-red-500 text-xs mt-1 text-center"
                        >
                            {{ otpError }}
                        </p>
                    </div>

                    <div class="flex flex-col items-center mt-2 w-full">
                        <button
                            type="submit"
                            class="button w-full sm:w-3/4"
                            :disabled="isSubmitting"
                        >
                            {{
                                isSubmitting
                                    ? "جاري التحقق..."
                                    : "تأكــــــيـد الرمز"
                            }}
                        </button>
                        <p
                            class="mt-6 sm:mt-8 text-center text-xs text-gray-400"
                        >
                            2024© حصتي. جميع الحقوق محفوظة
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Stethoscope } from "lucide-vue-next";
import { Icon } from "@iconify/vue";
import { reactive, ref, onMounted, nextTick } from "vue";

const otp = reactive(Array(4).fill(""));
const otpInputs = ref([]);
const otpError = ref(null);
const isSubmitting = ref(false);

onMounted(() => {
    if (otpInputs.value.length > 0) {
        otpInputs.value[0].focus();
    }
});

// --- دوال التحكم في حقول OTP ---

function focusFirstEmptyBox() {
    const firstEmptyIndex = otp.findIndex((digit) => digit === "");

    if (firstEmptyIndex !== -1) {
        otpInputs.value[firstEmptyIndex].focus();
    } else {
        otpInputs.value[otp.length - 1].focus();
    }
}

function isNumber(evt) {
    const charCode = evt.which ? evt.which : evt.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        evt.preventDefault();
    }
}

function moveNext(index) {
    otpError.value = null;
    if (otp[index].length === 1 && index < otp.length - 1) {
        otpInputs.value[index + 1].focus();
    }
}

/**
 * دالة معالجة المفاتيح للحذف (Backspace أو Delete).
 * - لـ Backspace: إذا كان الحقل الحالي مملوءاً، يمسحه. إذا كان فارغاً وليس الأول، ينتقل لليمين ويمسح السابق.
 * - لـ Delete: نفس منطق Backspace للتوافق.
 * @param {Event} event - حدث المفتاح.
 * @param {number} index - مؤشر الحقل الحالي.
 */
async function handleKeydown(event, index) {
    if (event.key === "Backspace" || event.key === "Delete") {
        event.preventDefault(); // منع السلوك الافتراضي لتجنب التأخر
        if (otp[index] !== "") {
            otp[index] = "";
        } else if (index > 0) {
            otp[index - 1] = "";
            await nextTick(); // انتظار تحديث DOM
            otpInputs.value[index - 1].focus();
        }
    }
}

// --- دالة معالجة إرسال النموذج ---

const handleVerifyOtp = async () => {
    const otpCode = otp.join("");

    if (otpCode.length !== 4) {
        otpError.value = "يرجى إدخال رمز التحقق المكون من 4 أرقام بالكامل.";
        return;
    }

    otpError.value = null;
    isSubmitting.value = true;

    // محاكاة لعملية إرسال
    setTimeout(() => {
        isSubmitting.value = false;
        alert(
            `تم التحقق من الرمز: ${otpCode}. يمكنك الآن المتابعة لخطوة كلمة المرور.`
        );
    }, 1500);
};
</script>

<style>
/* التنسيقات (CSS) - لم يتم تغييرها */
.custom-container {
    background: linear-gradient(0deg, #ffffff 0%, #f4f7fb 100%);
    border-radius: 35px;
    padding: 3.5rem 1.5rem 2rem 1.5rem !important;
    border: 5px solid #efefef;
    box-shadow: rgba(46, 80, 119, 0.88) 0px 30px 30px -20px;
}
@media (min-width: 640px) {
    .custom-container {
        padding: 4rem 2.5rem 2.5rem 2.5rem !important;
    }
}
.button {
    position: relative;
    transition: all 0.3s ease-in-out;
    box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.2);
    padding: 0.2rem 1.25rem;
    background-color: #2e5077;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    gap: 10px;
    font-weight: bold;
    border: 3px solid #ffffff4d;
    outline: none;
    overflow: hidden;
    font-size: 15px;
    cursor: pointer;
    height: 48px;
    width: 60%;
    margin-bottom: 9px;
    margin-top: 15px;
}
.button:hover:not(:disabled) {
    transform: scale(1.02);
    border-color: #fff9;
}
.button:disabled {
    background-color: #a0aec0;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}
.button:hover::before {
    animation: shine 1.5s ease-out infinite;
}
.button::before {
    content: "";
    position: absolute;
    width: 100px;
    height: 100%;
    background-image: linear-gradient(
        120deg,
        rgba(255, 255, 255, 0) 30%,
        rgba(255, 255, 255, 0.8),
        rgba(255, 255, 255, 0) 70%
    );
    top: 0;
    left: -100px;
    opacity: 0.6;
}
@keyframes shine {
    0% {
        left: -100px;
    }
    60% {
        left: 100%;
    }
    to {
        left: 100%;
    }
}

/* تنسيقات حقول OTP */
.otp-container {
    display: flex;
    gap: 8px;
}
.otp-container input {
    width: 55px;
    height: 50px;
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    color: #2e5077;
    border: 2px solid #ddd;
    border-radius: 10px;
    transition: all 0.2s ease-in-out;
    outline: none;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.otp-container input:focus {
    border-color: #2e5077;
    box-shadow: 0 0 0 3px rgba(46, 80, 119, 0.3);
}
.otp-container input::-webkit-outer-spin-button,
.otp-container input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.otp-container input[type="number"] {
    -moz-appearance: textfield;
}
</style>
