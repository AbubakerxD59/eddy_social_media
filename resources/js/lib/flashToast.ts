import { router } from '@inertiajs/vue3';
import { firstValidationError, notifyError } from '@/lib/notify';
import type { FlashToast } from '@/types/ui';
import { toast } from 'vue-sonner';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    router.on('error', (event) => {
        notifyError(
            firstValidationError(event.detail.errors) ??
                'Please fix the highlighted fields and try again.',
        );
    });

    router.on('httpException', () => {
        notifyError('Something went wrong. Please try again.');
    });

    router.on('networkError', () => {
        notifyError('Network error. Check your connection and try again.');
    });
}
