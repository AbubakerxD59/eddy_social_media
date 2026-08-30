import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { csrfHeaders } from '@/lib/csrf';
import { notifyError, notifySuccess } from '@/lib/notify';
import type { SignalLink, SignalType } from '@/types/social';

export function useSignalComposer(options: { onSuccess?: () => void; parentId?: string } = {}) {
    const user = computed(() => usePage().props.auth.user);
    const imageInput = ref<HTMLInputElement | null>(null);
    const videoInput = ref<HTMLInputElement | null>(null);
    const bodyInput = ref<HTMLTextAreaElement | null>(null);
    const previewing = ref(false);
    const preview = ref<SignalLink | null>(null);
    const localPreviews = ref<string[]>([]);

    const form = useForm({
        type: 'quote' as SignalType,
        parent_id: options.parentId ?? '',
        body: '',
        link_url: '',
        link_title: '',
        link_description: '',
        link_image: '',
        media: [] as File[],
    });

    const canSubmit = computed(() => {
        if (form.processing) {
            return false;
        }

        if (form.type === 'quote') {
            return form.body.trim().length > 0;
        }

        if (form.type === 'images' || form.type === 'video') {
            return form.media.length > 0;
        }

        return form.link_url.trim().length > 0;
    });

    const placeholder = computed(() => {
        if (form.type === 'quote') {
            return options.parentId ? 'Reply…' : "What's new?";
        }

        return 'Add a caption (optional)';
    });

    const resizeBody = () => {
        const el = bodyInput.value;

        if (!el) {
            return;
        }

        el.style.height = 'auto';
        el.style.height = `${Math.max(el.scrollHeight, 72)}px`;
    };

    const focusBody = () => {
        void nextTick(() => {
            resizeBody();
            bodyInput.value?.focus();
        });
    };

    const clearAttachments = () => {
        form.media = [];
        form.link_url = '';
        form.link_title = '';
        form.link_description = '';
        form.link_image = '';
        preview.value = null;
        localPreviews.value.forEach((url) => URL.revokeObjectURL(url));
        localPreviews.value = [];
    };

    const setType = (type: SignalType) => {
        if (form.type === type) {
            if (type === 'images') {
                imageInput.value?.click();
                return;
            }

            if (type === 'video') {
                videoInput.value?.click();
                return;
            }

            if (type === 'link') {
                form.type = 'quote';
                clearAttachments();
            }

            return;
        }

        form.type = type;
        clearAttachments();

        if (type === 'images') {
            void nextTick(() => imageInput.value?.click());
        }

        if (type === 'video') {
            void nextTick(() => videoInput.value?.click());
        }
    };

    const onImages = (event: Event) => {
        const input = event.target as HTMLInputElement;
        const files = Array.from(input.files ?? []).slice(0, 6);
        input.value = '';

        if (files.length === 0) {
            if (form.media.length === 0) {
                form.type = 'quote';
            }

            return;
        }

        form.media = files;
        localPreviews.value.forEach((url) => URL.revokeObjectURL(url));
        localPreviews.value = files.map((file) => URL.createObjectURL(file));
    };

    const onVideo = (event: Event) => {
        const input = event.target as HTMLInputElement;
        const files = Array.from(input.files ?? []).slice(0, 1);
        input.value = '';

        if (files.length === 0) {
            if (form.media.length === 0) {
                form.type = 'quote';
            }

            return;
        }

        form.media = files;
        localPreviews.value.forEach((url) => URL.revokeObjectURL(url));
        localPreviews.value = files.map((file) => URL.createObjectURL(file));
    };

    const fetchPreview = async () => {
        if (!form.link_url.trim()) {
            return;
        }

        previewing.value = true;

        try {
            const response = await fetch('/link-preview', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify({ url: form.link_url }),
            });

            if (!response.ok) {
                notifyError('Could not fetch a preview for that link.');
                return;
            }

            const data = (await response.json()) as SignalLink;
            preview.value = data;
            form.link_url = data.url;
            form.link_title = data.title ?? '';
            form.link_description = data.description ?? '';
            form.link_image = data.image ?? '';
            notifySuccess('Link preview ready.');
        } catch {
            notifyError('Could not fetch a preview for that link.');
        } finally {
            previewing.value = false;
        }
    };

    const resetComposer = () => {
        form.reset();
        form.type = 'quote';
        form.parent_id = options.parentId ?? '';
        clearAttachments();
        void nextTick(resizeBody);
    };

    const submit = () => {
        form.post('/signals', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                resetComposer();
                options.onSuccess?.();
            },
        });
    };

    return {
        user,
        form,
        imageInput,
        videoInput,
        bodyInput,
        previewing,
        preview,
        localPreviews,
        canSubmit,
        placeholder,
        resizeBody,
        focusBody,
        setType,
        onImages,
        onVideo,
        fetchPreview,
        resetComposer,
        submit,
    };
}
