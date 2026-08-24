import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { createPinia } from 'pinia'
import axios from 'axios'
import '../css/app.css'

// Đảm bảo mọi request axios (không chỉ Inertia) đều gửi CSRF token.
// Đọc trực tiếp từ thẻ <meta> thay vì để axios tự dò cookie XSRF-TOKEN —
// một số trình duyệt di động không đọc/gửi cookie đó ổn định qua axios.
axios.defaults.withCredentials = true
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

createInertiaApp({
    title: (title) => title ? `${title} — Mã Giảm Giá` : 'Mã Giảm Giá',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .mount(el)
    },
    progress: {
        color: '#F5511E',
    },
})
