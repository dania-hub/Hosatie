import './bootstrap';
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import '../css/app.css'
import LoadingState from './components/Shared/LoadingState.vue';
import ErrorState from './components/Shared/ErrorState.vue';
import EmptyState from './components/Shared/EmptyState.vue';
import TableSkeleton from './components/Shared/TableSkeleton.vue';

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue')
        return pages[`./Pages/${name}.vue`]()
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .component('LoadingState', LoadingState)
            .component('ErrorState', ErrorState)
            .component('EmptyState', EmptyState)
            .component('TableSkeleton', TableSkeleton)
            .mount(el)
    },
})