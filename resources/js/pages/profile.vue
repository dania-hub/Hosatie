<template>
    <div class="drawer" dir="rtl">
        <Sidebar />
        <div class="drawer-content flex flex-col bg-gray-50 min-h-screen">
            <Navbar />
            <main class="flex-1 p-4 sm:p-8 pt-20 sm:pt-5">
              
               

                <!-- حالة النجاح -->
                <div class="bg-white shadow-lg rounded-xl p-4 sm:p-6 max-w-4xl mx-auto">
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
                                    <button
                                        @click="changeProfileImage"
                                        class="absolute bottom-0 left-0 bg-blue-600 p-2 rounded-full text-white shadow-lg hover:bg-blue-700 transition"
                                        aria-label="تغيير الصورة"
                                    >
                                        <Icon
                                            icon="ic:baseline-camera-alt"
                                            class="w-5 h-5"
                                        />
                                    </button>
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
</template>

<script setup>
import { ref, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";
import { useToast } from "vue-toastification";

import Navbar from "@/components/Navbar.vue";
import Sidebar from "@/components/Sidebar.vue";
import EditProfileForm from "@/components/EditProfileForm.vue";
import ChangePasswordForm from "@/components/ChangePasswordForm.vue";

const toast = useToast();

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
const error = ref(null);
const isEditing = ref(false);
const isChangingPassword = ref(false);

// API endpoints (يجب تعديلها حسب الـ API الخاص بك)
const API_BASE_URL = "https://api.example.com"; // استبدل بعنوان API الفعلي
  const endpoints = {
      profile: "/api/profile/dashboard",
      updateProfile: "/api/profile/dashboard",
      changePassword: "/api/profile/password/dashboard",
      uploadImage: "/api/profile/upload-image"
  };

// الحصول على التوكن من localStorage أو Vuex/Pinia
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
    error.value = null;
    
  try {
      const response = await api.get(endpoints.profile);
      
      if (response.data.success) {
          const profile = response.data.data || response.data;
          userData.value = {
              id: profile.id,
              fullName: profile.full_name || profile.fullName || profile.name || "",
              jobRole: profile.jobRole || profile.type || "",
              healthCenter: profile.healthCenter || profile.center || "",
              email: profile.email || "",
              phone: profile.phone || profile.mobile || "",
              profileImage: profile.profileImage || profile.avatar || null
          };
        } else {
            error.value = response.data.message || "فشل في تحميل البيانات";
        }
    } catch (err) {
        error.value = err.response?.data?.message || err.message || "حدث خطأ في الاتصال بالخادم";
        console.error("Error fetching user data:", err);
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
            // يمكن إضافة المزيد من الحقول حسب API
        });
        
        if (response.data.success) {
            // تحديث البيانات المحلية
            userData.value.fullName = response.data.data?.full_name || newProfileData.fullName;
            userData.value.email = response.data.data?.email || newProfileData.email;
            userData.value.phone = response.data.data?.phone || newProfileData.phone;
            
            toast.success("تم تحديث البيانات بنجاح");
            isEditing.value = false;
        } else {
            toast.error(response.data.message || "فشل في تحديث البيانات");
        }
    } catch (err) {
        const errorMsg = err.response?.data?.message || err.message || "حدث خطأ في التحديث";
        toast.error(errorMsg);
        console.error("Error updating profile:", err);
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
        
        if (response.data.success) {
            toast.success("تم تغيير كلمة المرور بنجاح");
            isChangingPassword.value = false;
        } else {
            toast.error(response.data.message || "فشل في تغيير كلمة المرور");
        }
    } catch (err) {
        const errorMsg = err.response?.data?.message || err.message || "حدث خطأ في تغيير كلمة المرور";
        toast.error(errorMsg);
        console.error("Error changing password:", err);
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
                toast.success("تم تحديث الصورة بنجاح");
            }
        } catch (err) {
            toast.error("فشل في رفع الصورة");
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
