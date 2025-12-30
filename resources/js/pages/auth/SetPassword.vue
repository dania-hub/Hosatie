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
          <img src="/assets/logo4.png" alt="logo" class="w-25 h-29 sm:w-12 sm:h-12" />
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
                placeholder="أدخل كلمة المرور"
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
                placeholder="تأكيد كلمة المرور"
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
            <button 
              type="submit" 
              class="button w-full sm:w-3/4"
              :disabled="loading"
              :class="{ 'opacity-60 cursor-not-allowed': loading }"
            >
              <span v-if="!loading">تأكــــــيـد</span>
              <span v-else class="flex items-center gap-2">
                <Icon icon="eos-icons:loading" class="w-5 h-5" />
                جاري المعالجة...
              </span>
            </button>

            <!-- رسالة النجاح -->
            <div
              v-if="successMessage"
              class="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center gap-2 w-full"
            >
              <Icon icon="mdi:check-circle" class="w-6 h-6" />
              <span>{{ successMessage }}</span>
            </div>

            <!-- رسالة الخطأ -->
            <div
              v-if="errorMessage"
              class="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center gap-2 w-full"
            >
              <Icon icon="mdi:alert-circle" class="w-6 h-6" />
              <span>{{ errorMessage }}</span>
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
import { ref, onMounted } from "vue";
import axios from "axios";
import { Lock } from "lucide-vue-next";
import { Icon } from "@iconify/vue";
import { usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';

// الحصول على البيانات من URL
const page = usePage();
const token = ref("");
const email = ref("");

onMounted(() => {
  // الحصول على token و email من query parameters
  const urlParams = new URLSearchParams(window.location.search);
  token.value = urlParams.get('token') || "";
  email.value = urlParams.get('email') || "";
  
  console.log("Token:", token.value);
  console.log("Email:", email.value);
});

// تعريف المتغيرات
const newPassword = ref("");
const confirmPassword = ref("");
const newPasswordError = ref("");
const confirmPasswordError = ref("");
const loading = ref(false);
const successMessage = ref("");
const errorMessage = ref("");

// دالة التحقق الشاملة للحد الأدنى للطول
const validatePassword = (passwordValue, errorRef, fieldName) => {
  if (!passwordValue) {
    errorRef.value = `حقل ${fieldName} مطلوب`;
    return false;
  } else if (passwordValue.length < 8) {
    errorRef.value = "كلمة المرور يجب أن تكون 8 أحرف على الأقل";
    return false;
  } else {
    errorRef.value = "";
    return true;
  }
};

// دالة منفصلة للتحقق من تطابق كلمتي المرور
const validateConfirmMatch = () => {
    if (confirmPasswordError.value && confirmPasswordError.value !== "كلمتا المرور غير متطابقتين") {
        return;
    }

    if (newPassword.value !== confirmPassword.value && confirmPassword.value.length > 0) {
      confirmPasswordError.value = "كلمتا المرور غير متطابقتين";
    } else if (newPassword.value === confirmPassword.value && confirmPassword.value.length > 0) {
      if (confirmPasswordError.value === "كلمتا المرور غير متطابقتين") {
          confirmPasswordError.value = "";
      }
    }
};

// دالة لمعالجة حدث الخروج من الحقل (Blur)
const validateField = (field) => {
  if (field === "newPassword") {
    if (newPassword.value.length > 0) {
      validatePassword(newPassword.value, newPasswordError, "كلمة المرور الجديدة");
    }

    if (confirmPassword.value.length > 0) {
      validateConfirmMatch();
    }

  } else if (field === "confirmPassword") {
    if (confirmPassword.value.length > 0) {
      validatePassword(confirmPassword.value, confirmPasswordError, "تأكيد كلمة المرور");
    }
    validateConfirmMatch();
  }
};

// دالة الإرسال النهائية مع الاتصال بالـ API
const handleResetPassword = async () => {
  successMessage.value = "";
  errorMessage.value = "";

  const isNewPasswordValid = validatePassword(newPassword.value, newPasswordError, "كلمة المرور الجديدة");
  let isConfirmPasswordValid = validatePassword(confirmPassword.value, confirmPasswordError, "تأكيد كلمة المرور");

  if (isNewPasswordValid && newPassword.value !== confirmPassword.value) {
      confirmPasswordError.value = "كلمتا المرور غير متطابقتين";
      isConfirmPasswordValid = false;
  }

  if (!token.value || !email.value) {
    errorMessage.value = "رابط غير صالح. يرجى طلب رابط جديد.";
    return;
  }

  if (isNewPasswordValid && isConfirmPasswordValid) {
    loading.value = true;

    try {
      console.log("إرسال البيانات:", {
        email: email.value,
        token: token.value,
        password: newPassword.value
      });

      // 👇 استخدام activate-account API
      const response = await axios.post('/api/activate-account', {
        email: email.value,
        token: token.value,
        password: newPassword.value,
        password_confirmation: confirmPassword.value
      });

      console.log("الاستجابة:", response.data);

      if (response.data.success) {
        successMessage.value = response.data.message || "تم تفعيل حسابك بنجاح!";
        
       setTimeout(() => {
  router.visit('/');   // أو route('login') إذا كنت تستخدم named routes
}, 2000);

      }

    } catch (error) {
      console.error("خطأ:", error);
      
      if (error.response) {
        console.error("استجابة الخطأ:", error.response.data);
        errorMessage.value = error.response.data.message || "حدث خطأ أثناء التفعيل";
      } else if (error.request) {
        errorMessage.value = "لا يمكن الاتصال بالخادم";
      } else {
        errorMessage.value = "حدث خطأ غير متوقع";
      }
    } finally {
      loading.value = false;
    }
  } else {
    console.log("خطأ في التحقق من البيانات");
  }
};
</script>

<style>
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
