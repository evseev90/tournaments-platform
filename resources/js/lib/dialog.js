import { nextTick, onBeforeUnmount, watch } from 'vue';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function useDialogFocus(dialog, isOpen, close) {
    let previousFocus = null;

    function onKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = [...(dialog.value?.querySelectorAll(FOCUSABLE) ?? [])];
        if (!focusable.length) {
            event.preventDefault();
            dialog.value?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.value?.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !dialog.value?.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    }

    const stop = watch(isOpen, async (open) => {
        if (open) {
            previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            await nextTick();
            const initial = dialog.value?.querySelector('[data-dialog-initial-focus]')
                ?? dialog.value?.querySelector(FOCUSABLE);
            (initial ?? dialog.value)?.focus();
            window.addEventListener('keydown', onKeydown);
            return;
        }

        window.removeEventListener('keydown', onKeydown);
        if (previousFocus?.isConnected) previousFocus.focus();
        previousFocus = null;
    }, { immediate: true, flush: 'post' });

    onBeforeUnmount(() => {
        stop();
        window.removeEventListener('keydown', onKeydown);
    });
}
