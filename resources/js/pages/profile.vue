<template>
    <div class="drawer" dir="rtl">
        <Sidebar />
        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />
            <main class="flex-1 p-4 sm:p-8 pt-20 sm:pt-5">
              
                <!-- حالة التحميل -->
                <div v-if="loading" class="text-center py-10">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#4DA1A9] mx-auto"></div>
                    <p class="mt-4 text-gray-600">جاري تحميل البيانات الشخصية...</p>
                </div>

                <!-- حالة النجاح -->
                <div v-else class="bg-white shadow-lg rounded-xl p-4 sm:p-6 max-w-4xl mx-auto">
                    <div v-if="!isEditing && !isChangingPassword">
                        <h2
                            class="text-3xl font-bold mb-6 sm:mb-8 text-right text-gray-800 border-b border-[#4DA1A9] pb-3"
                        >
                            البيانات الشخصية
                        </h2>

                        <div
                            class="flex flex-col md:flex-row items-center md:items-start gap-y-6"
                        >
                            <div
                                class="w-full md:w-1/3 flex flex-col items-center pb-4 md:pb-0 border-b md:border-b-0"
                            >
                                <div class="relative w-40 h-40">
                                    <div
                                        v-if="userData.profileImage"
                                        class="rounded-full overflow-hidden bg-gray-200 border-4 border-white shadow-md w-40 h-40"
                                    >
                                        <img 
                                            :src="userData.profileImage" 
                                            :alt="userData.fullName"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div
                                        v-else
                                        class="rounded-full overflow-hidden bg-gray-200 border-4 border-white shadow-md"
                                    >
                                        <svg
                                            class="w-40 h-40 text-gray-400"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"
                                            />
                                        </svg>
                                    </div>
                                    
                                </div>
                            </div>
                            <div class="md:w-2/3 w-full pr-7 space-y-4">
                                <div
                                    class="flex justify-between items-center py-2 border-b border-[#4DA1A9]"
                                >
                                    <span
                                        class="text-gray-500 text-base sm:text-lg"
                                        >الاسم الرباعي</span
                                    >
                                    <span
                                        class="font-medium text-gray-800 text-base sm:text-lg"
                                    >
                                        {{ userData.fullName }}
                                    </span>
                                </div>

                                <div
                                    class="flex justify-between items-center py-2 border-b border-[#4DA1A9]"
                                >
                                    <span
                                        class="text-gray-500 text-base sm:text-lg"
                                        >الدور الوظيفي</span
                                    >
                                    <span
                                        class="font-medium text-gray-800 text-base sm:text-lg"
                                    >
                                        {{ userData.jobRole }}
                                    </span>
                                </div>

                                <div
                                    class="flex justify-between items-center py-2 border-b border-[#4DA1A9]"
                                >
                                    <span
                                        class="text-gray-500 text-base sm:text-lg"
                                        >المركز الصحي</span
                                    >
                                    <span
                                        class="font-medium text-gray-800 text-base sm:text-lg"
                                    >
                                        {{ userData.healthCenter }}
                                    </span>
                                </div>

                                <div
                                    class="flex justify-between items-center py-2 border-b border-[#4DA1A9]"
                                >
                                    <span
                                        class="text-gray-500 text-base sm:text-lg"
                                        >البريد الإلكتروني</span
                                    >
                                    <span
                                        class="font-medium text-gray-800 text-base sm:text-lg ltr"
                                    >
                                        {{ userData.email }}
                                    </span>
                                </div>

                                <div
                                    class="flex justify-between items-center py-2"
                                >
                                    <span
                                        class="text-gray-500 text-base sm:text-lg"
                                        >رقم الهاتف</span
                                    >
                                    <span
                                        class="font-medium text-gray-800 text-base sm:text-lg ltr"
                                    >
                                        {{ userData.phone }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3 mt-8 pt-4 border-t border-[#4DA1A9]"
                        >
                            <button
                                @click="isChangingPassword = true"
                                class="inline-flex items-center w-48 px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] h-15 cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                            >
                                <Icon
                                    icon="ic:baseline-lock"
                                    class="w-5 h-5 ml-2"
                                />
                                تغيير كلمة المرور
                            </button>

                             <button
                                @click="isEditing = true"
                                class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-15 w-40 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                            >
                                <Icon
                                    icon="ic:baseline-edit"
                                    class="w-5 h-5 ml-2"
                                />
                                تعديل البيانات
                            </button>
                        </div>
                    </div>

                    <div v-else-if="isEditing">
                        <EditProfileForm
                            :initialData="userData"
                            :loading="updating"
                            @save="handleSave"
                            @cancelEdit="isEditing = false"
                        />
                    </div>

                    <div v-else-if="isChangingPassword">
                        <ChangePasswordForm
                            :loading="changingPassword"
                            @passwordSaved="handlePasswordSave"
                            @cancelChange="isChangingPassword = false"
                        />
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Alert Notification -->
    <Transition
        enter-active-class="transition duration-300 ease-out transform"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition duration-200 ease-in transform"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
    >
        <div 
            v-if="isSuccessAlertVisible" 
            class="fixed top-4 right-55 z-[1000] p-4 text-right rounded-lg shadow-xl max-w-xs transition-all duration-300"
            dir="rtl"
            :class="{
                'bg-green-50 border border-green-200 text-green-800': successMessage.includes('✅'),
                'bg-red-50 border border-red-200 text-red-800': successMessage.includes('⚠️'),
                'bg-blue-50 border border-blue-200 text-blue-800': !successMessage.includes('✅') && !successMessage.includes('⚠️')
            }"
        >
            <div class="flex items-start gap-3">
                <Icon 
                    :icon="successMessage.includes('✅') ? 'solar:check-circle-bold' : 'solar:danger-triangle-bold'" 
                    class="w-5 h-5 mt-0.5 flex-shrink-0"
                    :class="successMessage.includes('✅') ? 'text-green-600' : 'text-red-600'"
                />
                <div>
                    <p class="font-medium text-sm whitespace-pre-line">{{ successMessage.replace('✅', '').replace('⚠️', '') }}</p>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import EditProfileForm from "@/components/EditProfileForm.vue";
import ChangePasswordForm from "@/components/ChangePasswordForm.vue";

// حالة المستخدم
const userData = ref({
    id: null,
    fullName: "",
    jobRole: "",
    healthCenter: "",
    email: "",
    phone: "",
    profileImage: null,
});

// حالات التحكم
const loading = ref(true);
const updating = ref(false);
const changingPassword = ref(false);
const isEditing = ref(false);
const isChangingPassword = ref(false);

// نظام الإشعارات (مشابه لصفحة المرضى)
const isSuccessAlertVisible = ref(false);
const successMessage = ref("");
let alertTimeout = null;

// دالة عرض الإشعارات
const showSuccessAlert = (message) => {
    if (alertTimeout) {
        clearTimeout(alertTimeout);
    }
    
    successMessage.value = message;
    isSuccessAlertVisible.value = true;
    
    alertTimeout = setTimeout(() => {
        isSuccessAlertVisible.value = false;
        successMessage.value = "";
    }, 4000);
};

// API endpoints
const endpoints = {
    profile: "/api/profile/dashboard",
    updateProfile: "/api/profile/dashboard",
    changePassword: "/api/profile/password/dashboard",
    uploadImage: "/api/profile/upload-image"
};

// الحصول على التوكن من localStorage
const getAuthToken = () => {
    return localStorage.getItem("auth_token") || "";
};

// تهيئة axios مع التوكن
const api = axios.create({
    baseURL: "/",
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
    }
});

// إضافة interceptor للتعامل مع الردود
api.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        // معالجة أخطاء الشبكة
        if (!error.response) {
            showSuccessAlert("⚠️ فشل الاتصال بالخادم. الرجاء التحقق من اتصال الإنترنت.");
            return Promise.reject(error);
        }
        return Promise.reject(error);
    }
);

api.interceptors.request.use(
    (config) => {
        const token = getAuthToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// جلب بيانات المستخدم من API
const fetchUserData = async () => {
    loading.value = true;
    
    try {
        const response = await api.get(endpoints.profile);
        
        if (response.data.success) {
            const profile = response.data.data || response.data;
            
            userData.value = {
                id: profile.id,
                fullName: profile.full_name || profile.fullName || profile.name || "",
                jobRole: profile.type || profile.role || profile.job_title || "غير محدد",
                healthCenter: profile.hospital_name || 
                             profile.hospital?.name || 
                             profile.center || 
                             "غير محدد",
                email: profile.email || "",
                phone: profile.phone || profile.mobile || "",
                profileImage: profile.profileImage || profile.avatar || null,
                hospitalId: profile.hospital_id,
                hospitalData: profile.hospital
            };
        } else {
            showSuccessAlert(`⚠️ ${response.data.message || "فشل في تحميل البيانات الشخصية"}`);
        }
    } catch (err) {
        let errorMsg = "حدث خطأ في الاتصال بالخادم";
        
        if (err.response?.status === 401) {
            errorMsg = "انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى";
        } else if (err.response?.status === 403) {
            errorMsg = "ليس لديك صلاحية للوصول إلى هذه الصفحة";
        } else if (err.response?.status === 404) {
            errorMsg = "لم يتم العثور على بيانات المستخدم";
        } else if (err.response?.data?.message) {
            errorMsg = err.response.data.message;
        } else if (err.message) {
            errorMsg = err.message;
        }
        
        showSuccessAlert(`⚠️ ${errorMsg}`);
    } finally {
        loading.value = false;
    }
};

// حفظ التعديلات
const handleSave = async (newProfileData) => {
    updating.value = true;
    
    try {
        const response = await api.put(endpoints.updateProfile, {
            full_name: newProfileData.fullName,
            email: newProfileData.email,
            phone: newProfileData.phone,
        });
        
        if (response.data.success) {
            // تحديث البيانات المحلية
            userData.value.fullName = response.data.data?.full_name || newProfileData.fullName;
            userData.value.email = response.data.data?.email || newProfileData.email;
            userData.value.phone = response.data.data?.phone || newProfileData.phone;
            
            showSuccessAlert("✅ تم تحديث بياناتك الشخصية بنجاح");
            
            isEditing.value = false;
        } else {
            showSuccessAlert(`⚠️ ${response.data.message || "فشل في تحديث البيانات"}`);
        }
    } catch (err) {
        const errorData = err.response?.data;
        
        if (errorData?.errors) {
            // عرض أخطاء التحقق
            const errorList = Object.values(errorData.errors).flat();
            if (errorList.length > 0) {
                showSuccessAlert(`⚠️ ${errorList[0]}`);
            } else {
                showSuccessAlert("⚠️ فشل في تحديث البيانات");
            }
        } else if (errorData?.message) {
            // رسائل محددة
            if (errorData.message.includes("email") || errorData.message.includes("بريد")) {
                showSuccessAlert("⚠️ البريد الإلكتروني غير صالح أو مستخدم بالفعل");
            } else if (errorData.message.includes("phone") || errorData.message.includes("هاتف")) {
                showSuccessAlert("⚠️ رقم الهاتف غير صالح أو مستخدم بالفعل");
            } else if (errorData.message.includes("full_name") || errorData.message.includes("اسم")) {
                showSuccessAlert("⚠️ الاسم الرباعي غير صالح");
            } else {
                showSuccessAlert(`⚠️ ${errorData.message}`);
            }
        } else if (err.response?.status === 422) {
            showSuccessAlert("⚠️ البيانات المدخلة غير صالحة. يرجى التحقق من المعلومات");
        } else if (err.response?.status === 409) {
            showSuccessAlert("⚠️ هذا البريد الإلكتروني أو رقم الهاتف مستخدم بالفعل");
        } else if (err.response?.status === 401) {
            showSuccessAlert("⚠️ انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى");
        } else if (err.response?.status === 403) {
            showSuccessAlert("⚠️ ليس لديك صلاحية لتعديل البيانات");
        } else if (err.response?.status === 500) {
            showSuccessAlert("⚠️ حدث خطأ في الخادم. الرجاء المحاولة مرة أخرى لاحقاً");
        } else if (err.code === 'NETWORK_ERROR' || !err.response) {
            showSuccessAlert("⚠️ فشل الاتصال بالخادم. الرجاء التحقق من اتصال الإنترنت.");
        } else {
            showSuccessAlert("⚠️ حدث خطأ غير متوقع أثناء تحديث البيانات");
        }
    } finally {
        updating.value = false;
    }
};

// تغيير كلمة المرور
const handlePasswordSave = async (passwordData) => {
    changingPassword.value = true;
    
    try {
        const response = await api.put(endpoints.changePassword, {
            current_password: passwordData.currentPassword,
            new_password: passwordData.newPassword,
            new_password_confirmation: passwordData.newPassword
        });
        
        // الطلب ناجح تقنياً
        if (response.data.success) {
            showSuccessAlert(`✅ ${response.data.message || "تم تغيير كلمة المرور بنجاح"}`);
            isChangingPassword.value = false;
        } else {
            // success: false في الـ response
            showSuccessAlert(`⚠️ ${response.data.message || "فشل في تغيير كلمة المرور"}`);
        }
    } catch (err) {
        // هنا نتعامل مع الأخطاء التقنية (شبكة، 500، إلخ)
        // الـ 400 تم التعامل معه في interceptor
        
        const errorData = err.response?.data;
        
        if (errorData?.message) {
            // رسائل محددة للكلمة المرور
            if (errorData.message.includes("current password") || errorData.message.includes("كلمة المرور الحالية")) {
                showSuccessAlert("⚠️ كلمة المرور الحالية غير صحيحة");
            } else if (errorData.message.includes("same") || errorData.message.includes("مطابقة")) {
                showSuccessAlert("⚠️ كلمة المرور الجديدة يجب أن تكون مختلفة عن القديمة");
            } else if (errorData.message.includes("weak") || errorData.message.includes("ضعيفة")) {
                showSuccessAlert("⚠️ كلمة المرور ضعيفة. يجب أن تحتوي على أحرف كبيرة وصغيرة وأرقام ورموز");
            } else if (errorData.message.includes("confirmed") || errorData.message.includes("متطابقة")) {
                showSuccessAlert("⚠️ كلمات المرور غير متطابقة");
            } else {
                showSuccessAlert(`⚠️ ${errorData.message}`);
            }
        } else if (err.response?.status === 400) {
            showSuccessAlert("⚠️ كلمة المرور الحالية غير صحيحة");
        } else if (err.response?.status === 422) {
            showSuccessAlert("⚠️ البيانات المدخلة غير صالحة. يرجى التحقق من كلمات المرور");
        } else if (err.response?.status === 401) {
            showSuccessAlert("⚠️ انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى");
        } else if (err.response?.status === 403) {
            showSuccessAlert("⚠️ ليس لديك صلاحية لهذا الإجراء");
        } else if (err.response?.status === 500) {
            showSuccessAlert("⚠️ حدث خطأ في الخادم. الرجاء المحاولة مرة أخرى لاحقاً");
        } else if (err.code === 'NETWORK_ERROR' || !err.response) {
            showSuccessAlert("⚠️ فشل الاتصال بالخادم. الرجاء التحقق من اتصال الإنترنت.");
        } else {
            showSuccessAlert("⚠️ حدث خطأ غير متوقع أثناء تغيير كلمة المرور");
        }
    } finally {
        changingPassword.value = false;
    }
};

// تغيير صورة الملف الشخصي
const changeProfileImage = async () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        // تحقق من حجم الصورة
        if (file.size > 5 * 1024 * 1024) {
            showSuccessAlert("⚠️ حجم الصورة كبير جداً. الحد الأقصى 5MB");
            return;
        }
        
        const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            showSuccessAlert("⚠️ نوع الملف غير مدعوم. يُرجى اختيار صورة (JPEG, PNG, JPG, GIF)");
            return;
        }
        
        const formData = new FormData();
        formData.append('image', file);
        
        try {
            const response = await api.post(endpoints.uploadImage, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            });
            
            if (response.data.success) {
                userData.value.profileImage = response.data.data.imageUrl;
                showSuccessAlert("✅ تم تحديث صورتك الشخصية بنجاح");
            } else {
                showSuccessAlert(`⚠️ ${response.data.message || "فشل في تحديث الصورة"}`);
            }
        } catch (err) {
            if (err.response?.status === 413) {
                showSuccessAlert("⚠️ حجم الصورة كبير جداً");
            } else if (err.response?.status === 415) {
                showSuccessAlert("⚠️ نوع الملف غير مدعوم");
            } else {
                showSuccessAlert("⚠️ فشل في رفع الصورة. حاول مرة أخرى");
            }
        }
    };
    
    input.click();
};

// جلب البيانات عند تحميل المكون
onMounted(() => {
    fetchUserData();
});
</script>

<style scoped>
/* لتمكين النص بالاتجاه الإنجليزي (LTR) داخل حقل البريد والهاتف */
.ltr {
    direction: ltr;
}

/* أنيميشن التحميل */
@keyframes spin {
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>