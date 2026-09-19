/**
 * Время приходит с сервера в ISO с зоной, а поля ввода работают в зоне браузера.
 * Организатор мыслит местным временем, поэтому переводим на границе.
 */

export function toLocalInput(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    const shifted = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return shifted.toISOString().slice(0, 16);
}

export function fromLocalInput(value) {
    return value ? new Date(value).toISOString() : null;
}

export function formatTime(iso) {
    return iso ? new Date(iso).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) : '';
}

export function formatDay(iso) {
    return iso ? new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' }) : '';
}

export function formatDayShort(iso) {
    return iso ? new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }) : '';
}

export function sameDay(a, b) {
    return new Date(a).toDateString() === new Date(b).toDateString();
}
