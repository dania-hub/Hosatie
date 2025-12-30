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
      

        <div
          class="w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center border-4 border-white shadow-xl bg-white z-20 absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
        >
         <!--         <img src="/assets/logo2.png" alt="logo" class="h-15 w-15 object-contain flex-shrink-0" />
 -->
          <img src="/assets/logo4.png" alt="logo" class="w-25 h-29 sm:w-12 sm:h-12 " />
        </div>

        <div class="flex flex-col items-center gap-2 mt-4 sm:mt-0">
          <h1
            class="text-2xl sm:text-2xl font-extrabold text-[#2E5077] mt-2 mb-3 sm:mt-0"
          >
            تعيين كلمة المرور
          </h1>
        </div>

        <form @submit.prevent="handleResetPassword" class="grid gap-4 sm:gap-5 text-right">
          
          <div class="grid gap-1">
            <div class="relative">
              <input
                id="newPassword"
                type="password"
                v-model="newPassword"
                @blur="validateField('newPassword')"
                @input="newPasswordError = ''"
                placeholder="أدخل كلمة المرور "
                class="custom-input text-right text-sm sm:text-base"
                :class="{ 'input-error': newPasswordError }"
              />
              <Lock
                class="absolute right-5 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"
              />
            </div>
            <span
              v-if="newPasswordError"
              class="text-red-500 text-xs font-bold mr-2 mt-1 flex items-center gap-1"
            >
              <Icon
                icon="material-symbols-light:error-outline-rounded"
                class="w-4 h-4"
              />
              {{ newPasswordError }}
            </span>
          </div>

          <div class="grid gap-1">
            <div class="relative">
              <input
                id="confirmPassword"
                type="password"
                v-model="confirmPassword"
                @blur="validateField('confirmPassword')"
                @input="confirmPasswordError = ''"
                placeholder="تأكيد كلمة المرور "
                class="custom-input text-right text-sm sm:text-base"
                :class="{ 'input-error': confirmPasswordError }"
              />
              <Lock
                class="absolute right-5 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"
              />
            </div>
            <span
              v-if="confirmPasswordError"
              class="text-red-500 text-xs font-bold mr-2 mt-1 flex items-center gap-1"
            >
              <Icon
                icon="material-symbols-light:error-outline-rounded"
                class="w-4 h-4"
              />
              {{ confirmPasswordError }}
            </span>
          </div>

          <div class="flex flex-col items-center mt-2 w-full">
            <button type="submit" class="button w-full sm:w-3/4">
              تأكــــــيـد
            </button>
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
import { Lock, Stethoscope } from "lucide-vue-next";
import { Icon } from "@iconify/vue";

// تعريف المتغيرات
const newPassword = ref("");
const confirmPassword = ref("");

const newPasswordError = ref("");
const confirmPasswordError = ref("");

// دالة التحقق الشاملة للحد الأدنى للطول
const validatePassword = (passwordValue, errorRef, fieldName) => {
  if (!passwordValue) {
    errorRef.value = `حقل ${fieldName} مطلوب`;
    return false;
  } else if (passwordValue.length < 6) {
    errorRef.value = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    return false;
  } else {
    errorRef.value = "";
    return true;
  }
};

// دالة منفصلة للتحقق من تطابق كلمتي المرور
const validateConfirmMatch = () => {
    // إذا كان هناك خطأ بالفعل (مثل خطأ الطول)، نتركه أولاً
    if (confirmPasswordError.value && confirmPasswordError.value !== "كلمتا المرور غير متطابقتين") {
        return;
    }

    // التحقق من التطابق فقط
    if (newPassword.value !== confirmPassword.value && confirmPassword.value.length > 0) {
      confirmPasswordError.value = "كلمتا المرور غير متطابقتين";
    } else if (newPassword.value === confirmPassword.value && confirmPassword.value.length > 0) {
      // إذا تساويا، نزيل رسالة الخطأ الخاصة بالتطابق
      if (confirmPasswordError.value === "كلمتا المرور غير متطابقتين") {
          confirmPasswordError.value = "";
      }
    }
};

// دالة لمعالجة حدث الخروج من الحقل (Blur)
const validateField = (field) => {
  if (field === "newPassword") {
    // التحقق من حقل كلمة المرور الجديدة
    if (newPassword.value.length > 0) {
      validatePassword(newPassword.value, newPasswordError, "كلمة المرور الجديدة");
    }

    // التحقق من التطابق فوراً إذا كان حقل التأكيد مملوءاً
    if (confirmPassword.value.length > 0) {
      validateConfirmMatch();
    }

  } else if (field === "confirmPassword") {
    // التحقق من حقل تأكيد كلمة المرور
    if (confirmPassword.value.length > 0) {
      validatePassword(confirmPassword.value, confirmPasswordError, "تأكيد كلمة المرور");
    }
    // التحقق الإضافي للتطابق
    validateConfirmMatch();
  }
};

// دالة الإرسال النهائية
const handleResetPassword = () => {
  // نجبر التحقق على جميع الحقول
  const isNewPasswordValid = validatePassword(newPassword.value, newPasswordError, "كلمة المرور الجديدة");
  let isConfirmPasswordValid = validatePassword(confirmPassword.value, confirmPasswordError, "تأكيد كلمة المرور");

  // التحقق من التطابق كخطوة أخيرة في الإرسال
  if (isNewPasswordValid && newPassword.value !== confirmPassword.value) {
      confirmPasswordError.value = "كلمتا المرور غير متطابقتين";
      isConfirmPasswordValid = false; // فشل التحقق
  }

  // يتم الإرسال فقط إذا كانت جميع التحققات ناجحة
  if (isNewPasswordValid && isConfirmPasswordValid) {
    // ****** هنا يتم وضع كود الاتصال بالـ API لإعادة تعيين كلمة المرور ******
    console.log("تمت إعادة تعيين كلمة المرور بنجاح");
    // مثال: router.push('/success');
  } else {
    console.log("خطأ في التحقق، يرجى مراجعة البيانات المدخلة.");
  }
};
</script>

<style>
/* التنسيقات (CSS) تستخدم Tailwind CSS ومُضافة للتصميم الجمالي.
  هذا الجزء لم يتم تعديله بناءً على طلبك، ولكنه جزء أساسي من شكل النموذج.
*/
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
.custom-input {
  width: 100% !important;
  background: white !important;
  border: 1px solid #d1d5db !important;
  padding: 15px 50px 15px 15px !important;
  border-radius: 15px !important;
  box-shadow: #afc6e1 0px 10px 10px -5px;
  transition: all 0.2s ease-in-out;
  color: #1f2937;
  margin-top: 0;
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