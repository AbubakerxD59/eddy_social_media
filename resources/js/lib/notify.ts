import { toast } from 'vue-sonner';

const fallbackError = 'Something went wrong. Please try again.';

export function notifySuccess(message: string): void {
    toast.success(message);
}

export function notifyError(message: string = fallbackError): void {
    toast.error(message);
}

export function firstValidationError(
    errors?: Record<string, string | string[] | undefined>,
): string | undefined {
    if (!errors) {
        return undefined;
    }

    const first = Object.values(errors).find((value) => {
        if (Array.isArray(value)) {
            return value[0];
        }

        return Boolean(value);
    });

    if (Array.isArray(first)) {
        return first[0];
    }

    return first;
}

export function notifyFormError(
    errors?: Record<string, string | string[] | undefined>,
    fallback: string = 'Please fix the highlighted fields and try again.',
): void {
    notifyError(firstValidationError(errors) ?? fallback);
}
