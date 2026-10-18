import { ref } from 'vue';

const KEY = 'theme';

function systemPrefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function stored() {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
}

/**
 * Тема живёт классом на <html>: первую установку делает скрипт в app.blade.php,
 * здесь только переключение. Сохранённый выбор важнее системного.
 */
export const isDark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

export function toggleTheme() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    try {
        if (isDark.value === systemPrefersDark()) {
            localStorage.removeItem(KEY);
        } else {
            localStorage.setItem(KEY, isDark.value ? 'dark' : 'light');
        }
    } catch {
        // приватный режим — тема останется до перезагрузки
    }
}

export function watchSystemTheme() {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = (event) => {
        if (stored() !== null) return;
        isDark.value = event.matches;
        document.documentElement.classList.toggle('dark', event.matches);
    };
    media.addEventListener('change', onChange);
    return () => media.removeEventListener('change', onChange);
}
