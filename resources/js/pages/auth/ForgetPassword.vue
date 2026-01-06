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
      <!-- الرجوع للصفحة السابقة  -->
  <a
  href="/"
  class="absolute top-6 left-6 w-10 h-10 rounded-full flex items-center justify-center
         text-gray-500 transition-all duration-300 z-20
         hover:text-white hover:bg-[#2E5077] hover:scale-105"
>
  <Icon icon="mdi:arrow-left" class="w-7 h-7" />
</a>
        <div
          class="w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center border-4 border-white shadow-xl bg-white z-20 absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
        >
          <Stethoscope class="w-8 h-8 sm:w-12 sm:h-12 text-[#2E5077]" />
        </div>

        <div class="flex flex-col items-center gap-2 mt-4 sm:mt-0">
          <h1
            class="text-2xl sm:text-2xl font-extrabold text-[#2E5077] mt-2 mb-3 sm:mt-0"
          >
            نسيت كلمة المرور
          </h1>
          <p class="text-gray-500 text-sm">ادخل البريد الإلكتروني المرتبط بحسابك.</p>
        </div>

        <form @submit.prevent="handleResetPassword" class="grid gap-4 sm:gap-5 text-right">
          <div class="grid gap-1">
            <div class="relative">
              <input
                id="email"
                type="email"
                v-model="email"
                @blur="handleBlur('email')"
                @input="emailError = ''"
                placeholder="أدخل البريد الإلكتروني الخاص بك"
                class="custom-input text-right text-sm sm:text-base"
                :class="{ 'input-error': emailError }"
              />
              <Mail
                class="absolute right-5 top-1/2 transform -translate-y-4 w-5 h-5 text-gray-400 pointer-events-none"
              />
            </div>
            <span
              v-if="emailError"
              class="text-red-500 text-xs font-bold mr-2 mt-1 flex items-center gap-1"
            >
              <Icon
                icon="material-symbols-light:error-outline-rounded"
                class="w-4 h-4"
              />
              {{ emailError }}
            </span>
          </div>

          <div class="flex flex-col items-center mt-2 w-full">
            <button 
              type="submit" 
              class="button w-full sm:w-3/4"
              :disabled="loading"
              :class="{ 'opacity-50 cursor-not-allowed': loading }"
            >
              <span v-if="loading" class="flex items-center justify-center gap-2">
                <Icon icon="eos-icons:loading" class="w-5 h-5" />
                جاري الإرسال...
              </span>
              <span v-else>تأكــــــيـد</span>
            </button>
            
            <!-- رسالة الخطأ من الـ API -->
            <div v-if="apiError" class="mt-4 p-2 bg-red-50 border border-red-200 rounded-lg w-full sm:w-3/4">
              <p class="text-red-700 text-sm font-medium flex items-center gap-2 justify-center">
                <Icon icon="material-symbols:error-outline" class="w-4 h-4" />
                {{ apiError }}
              </p>
            </div>
            
            <p class="mt-6 sm:mt-8 text-center text-xs text-gray-400">
              2024© حصتي. جميع الحقوق محفوظة
            </p>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { Mail, Stethoscope } from "lucide-vue-next"; 
import { Icon } from "@iconify/vue";
import { router } from '@inertiajs/vue3';

// تعريف المتغيرات
const email = ref("");
const emailError = ref("");
const apiError = ref("");
const loading = ref(false);

// ****** متغيرات الحالة لتعقب اللمس ******
const emailTouched = ref(false); 

// دالة التحقق من البريد الإلكتروني (التحقق الفعلي)
const validateEmail = () => {
    const emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
    
    if (!email.value) {
        emailError.value = "حقل البريد الإلكتروني مطلوب";
        return false;
    } 
    else if (!emailPattern.test(email.value)) {
        emailError.value = "صيغة البريد الإلكتروني غير صحيحة";
        return false;
    } 
    else {
        emailError.value = "";
        return true;
    }
};

// دالة لمعالجة حدث الخروج من الحقل (Blur)
const handleBlur = (field) => {
    if (field === 'email') {
        emailTouched.value = true;
        // نتحقق فقط إذا كان هناك محتوى في الحقل (لتجنب ظهور "مطلوب" بمجرد الخروج من حقل فارغ)
        if (email.value.length > 0) {
            validateEmail();
        } else {
            // نمسح الخطأ إذا كان الحقل فارغاً، ونعتمد على handleResetPassword لإظهار "مطلوب" عند الإرسال
            emailError.value = "";
        }
    }
};

// دالة الإرسال النهائية - تم تغيير اسمها لـ handleResetPassword
const handleResetPassword = async () => {
    // نجبر التحقق على الحقل
    const isEmailValid = validateEmail();
    
    // يتم الإرسال فقط إذا كان التحقق ناجحاً
    if (!isEmailValid) {
        return;
    }

    loading.value = true;
    apiError.value = "";

    try {
        const response = await fetch('/api/forgot-password/dashboard', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                email: email.value
            })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'حدث خطأ أثناء إرسال رمز التحقق');
        }

        // حفظ البريد الإلكتروني في localStorage للاستخدام في الصفحات التالية
        localStorage.setItem('reset_password_email', email.value);

        // عرض رسالة نجاح
        alert('✅ تم إرسال رمز التحقق إلى بريدك الإلكتروني. يرجى التحقق من صندوق الوارد.');

        // الانتقال لصفحة OTP
        router.visit('/otp');
    } catch (error) {
        console.error("خطأ في إرسال رمز التحقق:", error);
        apiError.value = error.message || 'فشل الاتصال بالخادم';
    } finally {
        loading.value = false;
    }
};
</script>

<style>
/* الحاوية الرئيسية للنموذج - متجاوبة */
.custom-container {
  background: linear-gradient(0deg, #ffffff 0%, #f4f7fb 100%);
  border-radius: 35px;
  /* حواشي متجاوبة: صغيرة للموبايل وكبيرة للشاشات الأكبر */
  padding: 3.5rem 1.5rem 2rem 1.5rem !important;
  border: 5px solid #efefef;
  box-shadow: rgba(46, 80, 119, 0.88) 0px 30px 30px -20px;
}

/* ميديا كويري للشاشات الأكبر من الموبايل */
@media (min-width: 640px) {
  .custom-container {
    padding: 4rem 2.5rem 2.5rem 2.5rem !important;
  }
}

/* تنسيق الزر */
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
.button:hover {
  transform: scale(1.02); 
  border-color: #fff9;
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

/* تنسيق حقول الإدخال */
.custom-input {
  width: 100% !important;
  background: white !important;
  border: 1px solid #d1d5db !important;
  /* تعديل البادينج للموبايل */
  padding: 15px 50px 15px 15px !important;
  border-radius: 15px !important;
  box-shadow: #afc6e1 0px 10px 10px -5px;
  transition: all 0.2s ease-in-out;
  color: #1f2937;
  /* تم إزالة margin-bottom من هنا لضبطه في القالب عبر Tailwind */
}

.custom-input:focus {
  outline: none !important;
  border: 3px solid #91afd1 !important;
}

.input-error {
  border: 1px solid #ef4444 !important;
  background-color: #fef2f2 !important;
}
.input-error:focus {
  border: 1px solid #ef4444 !important;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
}

.custom-input::placeholder {
  color: rgb(170, 170, 170);
  text-align: right;
}
</style>