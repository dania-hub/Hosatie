<template>
  <div
    dir="rtl"
    class="w-full min-h-screen lg:grid lg:grid-cols-2 font-['Inter'] bg-gray-50 overflow-x-hidden"
  >
    <div
      class="hidden lg:block bg-[#2E5077] relative overflow-hidden p-12 text-white shadow-2xl"
    >
      <svg
        class="absolute -top-24 -left-24 w-80 h-80 text-white opacity-40 pointer-events-none"
        viewBox="0 0 100 100"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <circle
          cx="50"
          cy="50"
          r="40"
          stroke="currentColor"
          stroke-width="8"
        />
      </svg>

      <svg
        class="absolute -bottom-52 -right-2 w-96 h-96 text-white opacity-40 pointer-events-none"
        viewBox="0 0 100 100"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <rect
          x="25"
          y="25"
          width="50"
          height="50"
          rx="10"
          transform="rotate(30 50 50)"
          stroke="currentColor"
          stroke-width="8"
        />
      </svg>

      <svg
        class="absolute bottom-1 -right-38 w-64 h-64 text-white opacity-40 pointer-events-none"
        viewBox="0 0 100 100"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <rect
          x="25"
          y="25"
          width="50"
          height="50"
          rx="8"
          transform="rotate(45 50 50)"
          stroke="currentColor"
          stroke-width="8"
        />
      </svg>

      <svg
        class="absolute -bottom-20 -left-20 w-72 h-72 text-white opacity-40 pointer-events-none"
        viewBox="0 0 100 100"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <rect
          x="20"
          y="20"
          width="60"
          height="60"
          rx="10"
          transform="rotate(15 50 50)"
          stroke="currentColor"
          stroke-width="8"
        />
      </svg>

      <div class="relative z-10 flex flex-col h-full justify-center space-y-8">
        <h1 class="text-5xl font-extrabold text-right">حصتي</h1>
        <p class="text-lg lg:text-xl leading-relaxed text-right">
          مرحبًا بك في نظام "حصتي" لإدارة توزيع الأدوية المدعومة.
          <br />
          صُمم هذا النظام خصيصًا لدعم الكوادر الطبية والإدارية في تنظيم ومتابعة
          عمليات صرف الأدوية المدعومة، وضمان وصولها للمستحقين بكل شفافية وعدالة.
        </p>
        <p class="text-lg font-semibold text-right">
          سجّل دخولك للوصول إلى أدوات الإدارة والتحكم.
        </p>
      </div>
    </div>

    <div
      class="flex items-center justify-center min-h-screen p-4 sm:p-8 relative"
    >
      <div
        class="w-full max-w-[470px] grid gap-6 text-center relative custom-container mx-auto"
      >
        <div
          class="w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center border-4 border-white shadow-xl bg-white z-20 absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
        >
          <Stethoscope class="w-8 h-8 sm:w-12 sm:h-12 text-[#2E5077]" />
        </div>

        <div class="flex flex-col items-center gap-2 mt-4 sm:mt-0">
          <h1
            class="text-2xl sm:text-3xl font-extrabold text-[#2E5077] mt-2 sm:mt-0"
          >
            تسجيل الدخول
          </h1>
          <p class="text-gray-500 text-sm">أدخل بياناتك للوصول للنظام</p>
        </div>

        <form @submit.prevent="handleLogin" class="grid gap-4 sm:gap-5 text-right">
          <div class="grid gap-1">
            <label for="email" class="sr-only"
              >أدخل البريد الإلكتروني الخاص بك</label
            >
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
                class="absolute right-5 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"
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

          <div class="grid gap-1">
            <label for="password" class="sr-only"
              >أدخل كلمة المرور الخاصة بك</label
            >
            <div class="relative">
              <input
                id="password"
                type="password"
                v-model="password"
                @blur="handleBlur('password')"
                @input="passwordError = ''"
                placeholder="أدخل كلمة المرور الخاصة بك"
                class="custom-input text-right text-sm sm:text-base"
                :class="{ 'input-error': passwordError }"
              />
              <Lock
                class="absolute right-5 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none"
              />
            </div>
            <span
              v-if="passwordError"
              class="text-red-500 text-xs font-bold mr-2 mt-1 flex items-center gap-1"
            >
              <Icon
                icon="material-symbols-light:error-outline-rounded"
                class="w-4 h-4"
              />
              {{ passwordError }}
            </span>
          </div>

          <div class="text-center text-sm mt-1">
            <a
              href="#"
              class="inline-block text-[#2E5077] hover:underline transition duration-200"
            >
              هل نسيت كلمة المرور؟
            </a>
          </div>

          <div class="flex flex-col items-center mt-2 w-full">
            <button type="submit" class="button w-full sm:w-3/4">
              تسجيل الدخول
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
import { Mail, Lock, Stethoscope } from "lucide-vue-next";
import { Icon } from "@iconify/vue";

// تعريف المتغيرات
const email = ref("");
const password = ref("");
const emailError = ref("");
const passwordError = ref("");

// ****** متغيرات الحالة لتعقب اللمس ******
const emailTouched = ref(false);
const passwordTouched = ref(false);

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

// دالة التحقق من كلمة المرور (التحقق الفعلي)
const validatePassword = () => {
    if (!password.value) {
        passwordError.value = "حقل كلمة المرور مطلوب";
        return false;
    } else if (password.value.length < 6) {
        passwordError.value = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
        return false;
    } else {
        passwordError.value = "";
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
            // نمسح الخطأ إذا كان الحقل فارغاً، ونعتمد على handleLogin لإظهار "مطلوب"
            emailError.value = "";
        }
    } else if (field === 'password') {
        passwordTouched.value = true;
        if (password.value.length > 0) {
             validatePassword();
        } else {
            passwordError.value = "";
        }
    }
};

// دالة الإرسال النهائية - تضمن ظهور أخطاء "مطلوب" في حال كانت الحقول فارغة
const handleLogin = () => {
    // نجبر التحقق على جميع الحقول
    const isEmailValid = validateEmail();
    const isPasswordValid = validatePassword();

    // يتم الإرسال فقط إذا كانت جميع التحققات ناجحة
    if (isEmailValid && isPasswordValid) {
        // هنا يتم وضع كود الاتصال بالـ API
        console.log("تم تسجيل الدخول بنجاح", { email: email.value });
    } else {
        console.log("خطأ في التحقق، يرجى مراجعة البيانات المدخلة.");
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
  /* العرض يتم التحكم فيه بواسطة Tailwind Classes الآن */
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