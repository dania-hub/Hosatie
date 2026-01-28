<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { Icon } from '@iconify/vue';
import axios from 'axios';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/ar';

dayjs.extend(relativeTime);
dayjs.locale('ar');

const notifications = ref([]);
const unreadCount = ref(0);
const loading = ref(true);
const isOpen = ref(false);
const showPreview = ref(false);
const selectedNotification = ref(null);
const dropdownRef = ref(null);

const getAuthHeaders = () => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    return {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };
};

const fetchNotifications = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/notifications/mobile', getAuthHeaders());
        if (response.data.success) {
            notifications.value = response.data.data;
            unreadCount.value = notifications.value.filter(n => !n.is_read).length;
        }
    } catch (error) {
        console.error('Error fetching notifications:', error);
    } finally {
        loading.value = false;
    }
};

const markAsRead = async (notification) => {
    if (notification.is_read) return;

    try {
        await axios.post('/api/notifications/mark-as-read', {
            notification_ids: [notification.id]
        }, getAuthHeaders());
        notification.is_read = true;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
};

const markAllAsRead = async () => {
    const unreadIds = notifications.value.filter(n => !n.is_read).map(n => n.id);
    if (unreadIds.length === 0) return;

    try {
        await axios.post('/api/notifications/mark-as-read', {
            notification_ids: unreadIds
        }, getAuthHeaders());
        notifications.value.forEach(n => n.is_read = true);
        unreadCount.value = 0;
    } catch (error) {
        console.error('Error marking all as read:', error);
    }
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchNotifications();
    }
};

const closeDropdown = (e) => {
    // Don't close if a preview is currently open
    if (showPreview.value) return;
    
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

const viewNotification = (notification) => {
    selectedNotification.value = notification;
    showPreview.value = true;
    markAsRead(notification);
};

const closePreview = () => {
    showPreview.value = false;
    selectedNotification.value = null;
};

onMounted(() => {
    fetchNotifications();
    document.addEventListener('click', closeDropdown);
    // Poll every 60 seconds
    const interval = setInterval(fetchNotifications, 60000);
    onUnmounted(() => {
        document.removeEventListener('click', closeDropdown);
        clearInterval(interval);
    });
});

const getIcon = (notification) => {
    if (notification.type === 'شحنة') {
        return 'solar:box-bold-duotone';
    }
    const text = (notification.title + ' ' + (notification.body || '')).toLowerCase();
    
    // Priority 1: Damage/Shortage/Alerts
    if (text.includes('نقص') || text.includes('تلف') || text.includes('damage') || text.includes('shortage')) {
        return 'solar:danger-triangle-bold-duotone';
    }
    
    // Priority 2: Supply Requests
    if (text.includes('توريد') || text.includes('طلب') || text.includes('supply') || text.includes('request')) {
        return 'solar:clipboard-list-bold-duotone';
    }

    // Priority 3: Rejection (High visibility)
    if (text.includes('رفض') || text.includes('rejected')) {
        return 'solar:shield-cross-bold-duotone';
    }

    // Priority 4: Prepared/Ready status
    if (text.includes('تجهيز') || text.includes('ready') || text.includes('prepared')) {
        return 'solar:box-bold-duotone';
    }

    // Priority 5: Shipments/Delivery
    if (text.includes('شحنة') || text.includes('shipment') || text.includes('delivery')) {
        return 'solar:delivery-bold-duotone';
    }

    // Priority 6: Drugs
    if (text.includes('دواء') || text.includes('أدوية') || text.includes('drug') || text.includes('stock')) {
        return 'solar:pill-bold-duotone';
    }

    // Priority 7: Expiry/Low Stock
    if (text.includes('منخفض') || text.includes('low') || text.includes('انتهاء') || text.includes('expiry')) {
        return 'solar:bell-bing-bold-duotone';
    }

    // Priority 8: Approval
    if (text.includes('موافقة') || text.includes('قبول') || text.includes('approved')) {
        return 'solar:check-circle-bold-duotone';
    }

    // Fallback to type-based icons
    switch (notification.type) {
        case 'success': return 'solar:check-circle-bold-duotone';
        case 'error': return 'solar:danger-bold-duotone';
        case 'warning': return 'solar:info-circle-bold-duotone';
        case 'info': return 'solar:info-circle-bold-duotone';
        case 'مستعجل': return 'solar:bell-bing-bold-duotone';
        case 'عادي': return 'solar:bell-bold-duotone';
        default: return 'solar:notification-lines-bold-duotone';
    }
};

const getColor = (type) => {
    switch (type) {
        case 'success': return 'text-green-600 bg-green-50';
        case 'error': return 'text-red-600 bg-red-50';
        case 'warning': return 'text-amber-600 bg-amber-50';
        case 'info': return 'text-blue-600 bg-blue-50';
        case 'مستعجل': return 'text-red-700 bg-red-100';
        case 'عادي': return 'text-blue-600 bg-blue-50';
        default: return 'text-gray-600 bg-gray-50';
    }
};
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <!-- Bell Button -->
        <button 
            @click.stop="toggleDropdown"
            class="relative p-2 text-gray-600 hover:text-[#3a8c94] transition-colors duration-200 rounded-full hover:bg-gray-100 focus:outline-none"
        >
            <Icon icon="mdi:bell-outline" class="w-6 h-6" />
            <span v-if="unreadCount > 0" class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full">
                {{ unreadCount > 9 ? '+9' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown Menu -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div v-if="isOpen" class="absolute left-0 z-50 mt-2 w-80 md:w-96 bg-white rounded-lg shadow-xl ring-1 ring-black ring-opacity-5 origin-top-left overflow-hidden">
                <!-- Header -->
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-gray-700">الإشعارات</h3>
                    <button 
                        @click="markAllAsRead" 
                        class="text-xs text-[#3a8c94] hover:text-[#2c6e74] hover:underline focus:outline-none"
                    >
                        تحديد الكل كمقروء
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="max-h-96 overflow-y-auto">
                    <div v-if="loading && notifications.length === 0" class="p-4 text-center text-gray-500">
                        <Icon icon="eos-icons:loading" class="w-6 h-6 mx-auto animate-spin" />
                    </div>

                    <div v-else-if="notifications.length === 0" class="p-8 text-center text-gray-500 flex flex-col items-center">
                         <Icon icon="solar:bell-off-bold-duotone" class="w-12 h-12 mb-2 text-gray-300" />
                        <p class="text-sm">لا توجد إشعارات جديدة</p>
                    </div>

                    <ul v-else class="divide-y divide-gray-100">
                        <li 
                            v-for="notification in notifications" 
                            :key="notification.id"
                            @click="viewNotification(notification)"
                            :class="['p-4 hover:bg-gray-50 cursor-pointer transition-all duration-200 border-l-4', notification.is_read ? 'border-transparent' : (notification.type === 'مستعجل' ? 'border-red-500 bg-red-50/30' : 'border-[#3a8c94] bg-blue-50/20')]"
                        >
                            <div class="flex space-x-3 space-x-reverse items-start">
                                <div :class="['flex-shrink-0 p-2 rounded-xl transition-colors duration-200', getColor(notification.type)]">
                                    <Icon :icon="getIcon(notification)" class="w-6 h-6" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p :class="['text-sm font-semibold truncate', notification.is_read ? 'text-gray-700' : 'text-gray-900']">
                                        {{ notification.title }}
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1 line-clamp-2 leading-relaxed">
                                        {{ notification.body || notification.message }}
                                    </p>
                                    <div class="flex items-center mt-2 text-[10px] text-gray-400 font-medium uppercase tracking-wider">
                                        <Icon icon="solar:clock-circle-linear" class="w-3 h-3 ml-1" />
                                        {{ dayjs(notification.created_at).fromNow() }}
                                    </div>
                                </div>
                                <div v-if="!notification.is_read" class="flex-shrink-0 self-center">
                                    <span class="inline-block w-2.5 h-2.5 bg-[#3a8c94] rounded-full shadow-sm ring-2 ring-white"></span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </transition>

        <!-- Notification Content Preview Modal -->
        <Teleport to="body">
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div v-if="showPreview" class="fixed inset-0 z-[9999] overflow-y-auto flex items-center justify-center p-4 sm:p-6" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <!-- Overlay with dynamic transition -->
                    <div @click.stop="closePreview" class="fixed inset-0 bg-gray-900/40 transition-opacity" aria-hidden="true" style="backdrop-filter: blur(2px);"></div>

                    <!-- Modal content box -->
                    <div class="relative bg-white rounded-3xl text-right overflow-hidden shadow-2xl transform transition-all w-full max-w-xl border border-gray-100 z-10">
                        <div class="bg-white p-6 sm:p-8">
                            <div class="flex flex-col sm:flex-row-reverse items-center sm:items-start text-center sm:text-right gap-6">
                                <div :class="['flex-shrink-0 flex items-center justify-center h-16 w-16 rounded-2xl shadow-sm', getColor(selectedNotification?.type)]">
                                    <Icon :icon="getIcon(selectedNotification)" class="h-10 w-10" />
                                </div>
                                <div class="flex-1">
                                    <span :class="['inline-block px-3 py-1 rounded-full text-[10px] font-bold mb-3 uppercase tracking-widest', getColor(selectedNotification?.type)]">
                                        {{ selectedNotification?.type === 'مستعجل' ? 'تنبيه عاجل' : 'إشعار من النظام' }}
                                    </span>
                                    <h3 class="text-2xl font-black text-gray-900 leading-tight mb-4" id="modal-title">
                                        {{ selectedNotification?.title }}
                                    </h3>
                                    <div class="bg-gray-50/80 p-6 rounded-2xl border border-gray-100 text-right">
                                        <p class="text-lg text-gray-700 leading-relaxed whitespace-pre-wrap font-bold">
                                            {{ selectedNotification?.body || selectedNotification?.message }}
                                        </p>
                                    </div>
                                    <div class="mt-6 flex items-center justify-center sm:justify-start text-sm text-gray-500 font-medium pb-2">
                                        <Icon icon="solar:clock-circle-bold-duotone" class="w-5 h-5 ml-2 text-gray-400" />
                                        <span>بتاريخ: {{ dayjs(selectedNotification?.created_at).format('DD MMMM YYYY [الساعة] HH:mm') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-6 py-4 sm:px-8 border-t border-gray-100 flex flex-row-reverse justify-end">
                            <button 
                                @click.stop="closePreview" 
                                class="w-full sm:w-auto inline-flex justify-center rounded-2xl shadow-md px-10 py-3 bg-[#3a8c94] text-base font-black text-white hover:bg-[#2c6e74] active:scale-95 focus:outline-none transition-all duration-200"
                            >
                                إغلاق
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* Scrollbar styling */
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
}
::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>
