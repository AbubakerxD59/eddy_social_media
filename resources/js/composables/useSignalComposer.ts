import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { firstCaptionUrl, urlsMatch } from '@/lib/captionUrl';
import { csrfHeaders } from '@/lib/csrf';
import { htmlToPlainText } from '@/lib/htmlBody';
import { notifyError, notifyFormError, notifySuccess } from '@/lib/notify';
import { signalTypeMeta } from '@/lib/signalTypes';
import { formatTimelineDuration, type TimelineUnit } from '@/lib/timeline';
import type { SignalLink, SignalType } from '@/types/social';

export type MediaKind = 'none' | 'files';

export function useSignalComposer(options: { onSuccess?: () => void; parentId?: string; initialType?: SignalType } = {}) {
    const user = computed(() => usePage().props.auth.user);
    const bodyEditor = ref<{ focus: () => void } | null>(null);
    const titleInput = ref<HTMLInputElement | null>(null);
    const previewing = ref(false);
    const preview = ref<SignalLink | null>(null);
    const previewSourceUrl = ref<string | null>(null);
    const mediaKind = ref<MediaKind>('none');
    const mediaUploading = ref(false);

    const form = useForm({
        type: (options.initialType ?? 'drop') as SignalType,
        parent_id: options.parentId ?? '',
        title: '',
        body: '',
        budget: '',
        timeline: '',
        timeline_amount: '',
        timeline_unit: 'days' as TimelineUnit,
        location: '',
        latitude: null as number | null,
        longitude: null as number | null,
        place_id: '',
        skills: '',
        project_value: '',
        trades: '',
        poll_options: ['', ''] as string[],
        link_url: '',
        link_title: '',
        link_description: '',
        link_image: '',
        media_ids: [] as string[],
    });

    const isReply = computed(() => Boolean(options.parentId));
    const meta = computed(() => signalTypeMeta(form.type));

    const bodyText = computed(() => htmlToPlainText(form.body));

    const canSubmit = computed(() => {
        if (form.processing || mediaUploading.value) {
            return false;
        }

        if (form.type === 'need' || form.type === 'opportunity') {
            return form.title.trim().length > 0;
        }

        if (form.type === 'poll') {
            return bodyText.value.length > 0 && form.poll_options.filter((option) => option.trim()).length >= 2;
        }

        return bodyText.value.length > 0 || form.media_ids.length > 0;
    });

    const detectedUrl = computed(
        () => firstCaptionUrl(bodyText.value) ?? firstCaptionUrl(form.title),
    );

    const showLinkPreviewButton = computed(
        () => Boolean(detectedUrl.value) && !preview.value,
    );

    const placeholder = computed(() => {
        if (isReply.value) {
            return 'Reply…';
        }

        return meta.value.placeholder;
    });

    const submitLabel = computed(() => (isReply.value ? 'Reply' : meta.value.submit));

    const focusBody = () => {
        void nextTick(() => bodyEditor.value?.focus());
    };

    const focusTitle = () => {
        void nextTick(() => titleInput.value?.focus());
    };

    const clearPreview = () => {
        preview.value = null;
        previewSourceUrl.value = null;
        form.link_title = '';
        form.link_description = '';
        form.link_image = '';
    };

    const clearLink = () => {
        form.link_url = '';
        clearPreview();
    };

    watch(
        [detectedUrl, () => form.type],
        ([url, type]) => {
            if (!url) {
                clearLink();
                return;
            }

            if (previewSourceUrl.value && !urlsMatch(previewSourceUrl.value, url)) {
                clearPreview();
            }

            if (type === 'drop') {
                form.link_url = preview.value?.url ?? url;
                form.link_title = preview.value?.title ?? '';
                form.link_description = preview.value?.description ?? '';
                form.link_image = preview.value?.image ?? '';
                return;
            }

            form.link_url = '';
            form.link_title = '';
            form.link_description = '';
            form.link_image = '';
        },
    );

    const clearMedia = () => {
        form.media_ids = [];

        if (mediaKind.value === 'files') {
            mediaKind.value = 'none';
        }
    };

    const setType = (type: SignalType) => {
        if (isReply.value) {
            return;
        }

        form.type = type;

        if (type === 'poll') {
            mediaKind.value = 'none';
            clearMedia();
            focusBody();
            return;
        }

        if (type === 'need' || type === 'opportunity') {
            focusTitle();
            return;
        }

        focusBody();
    };

    const fetchPreview = async () => {
        const url = detectedUrl.value;

        if (!url || previewing.value) {
            return;
        }

        previewing.value = true;

        try {
            const response = await fetch('/link-preview', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify({ url }),
            });

            if (!response.ok) {
                notifyError('Could not fetch a preview for that link.');
                return;
            }

            const data = (await response.json()) as SignalLink;
            preview.value = data;
            previewSourceUrl.value = url;

            if (form.type === 'drop') {
                form.link_url = data.url;
                form.link_title = data.title ?? '';
                form.link_description = data.description ?? '';
                form.link_image = data.image ?? '';
            }

            notifySuccess('Link preview ready.');
        } catch {
            notifyError('Could not fetch a preview for that link.');
        } finally {
            previewing.value = false;
        }
    };

    const addPollOption = () => {
        if (form.poll_options.length >= 4) {
            return;
        }

        form.poll_options.push('');
    };

    const removePollOption = (index: number) => {
        if (form.poll_options.length <= 2) {
            return;
        }

        form.poll_options.splice(index, 1);
    };

    const resetComposer = () => {
        form.reset();
        form.type = 'drop';
        form.parent_id = options.parentId ?? '';
        form.poll_options = ['', ''];
        form.media_ids = [];
        mediaKind.value = 'none';
        mediaUploading.value = false;
        clearLink();
    };

    const submit = () => {
        if (!canSubmit.value) {
            return;
        }

        form.transform((data) => {
            const isDrop = data.type === 'drop';
            const hasPlace = data.type === 'need' || data.type === 'opportunity';
            const linkUrl = isDrop ? (data.link_url || detectedUrl.value || '') : '';
            const { timeline_amount, timeline_unit, ...rest } = data;

            return {
                ...rest,
                poll_options: data.type === 'poll' ? data.poll_options.filter((option) => option.trim()) : [],
                link_url: linkUrl,
                link_title: isDrop ? data.link_title : '',
                link_description: isDrop ? data.link_description : '',
                link_image: isDrop ? data.link_image : '',
                timeline: hasPlace ? formatTimelineDuration(timeline_amount, timeline_unit) : '',
                location: hasPlace ? data.location : '',
                latitude: hasPlace ? data.latitude : null,
                longitude: hasPlace ? data.longitude : null,
                place_id: hasPlace ? data.place_id : '',
            };
        }).post('/signals', {
            preserveScroll: true,
            onSuccess: () => {
                resetComposer();
                options.onSuccess?.();
            },
            onError: (errors) => notifyFormError(errors),
            onFinish: () => form.transform((data) => data),
        });
    };

    return {
        user,
        form,
        bodyEditor,
        titleInput,
        previewing,
        preview,
        mediaKind,
        mediaUploading,
        isReply,
        meta,
        canSubmit,
        showLinkPreviewButton,
        placeholder,
        submitLabel,
        focusBody,
        focusTitle,
        setType,
        clearMedia,
        fetchPreview,
        addPollOption,
        removePollOption,
        resetComposer,
        submit,
    };
}
