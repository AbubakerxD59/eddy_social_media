import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { firstCaptionUrl, urlsMatch } from '@/lib/captionUrl';
import { csrfHeaders } from '@/lib/csrf';
import { htmlToPlainText } from '@/lib/htmlBody';
import { notifyError, notifyFormError, notifySuccess } from '@/lib/notify';
import { signalTypeMeta } from '@/lib/signalTypes';
import { formatTimelineDuration, parseTimelineDuration } from '@/lib/timeline';
import type { FeedSignal, SignalLink, SignalType } from '@/types/social';

export type MediaKind = 'none' | 'files';

const emptyPollOptions = (): string[] => ['', ''];

const composerDefaults = (options: { parentId?: string; initialType?: SignalType; editing?: FeedSignal }) => {
    const signal = options.editing;
    const timeline = parseTimelineDuration(signal?.need?.timeline ?? signal?.opportunity?.timeline);
    const pollOptions = signal?.poll?.options.map((option) => option.text) ?? emptyPollOptions();

    return {
        type: (signal?.type ?? options.initialType ?? 'drop') as SignalType,
        parent_id: signal ? '' : (options.parentId ?? ''),
        title: signal?.title ?? '',
        body: signal?.body ?? '',
        budget: signal?.need?.budget ?? '',
        timeline: '',
        timeline_amount: timeline.amount,
        timeline_unit: timeline.unit,
        location: signal?.need?.location ?? signal?.opportunity?.location ?? '',
        latitude: signal?.latitude ?? null,
        longitude: signal?.longitude ?? null,
        place_id: signal?.place_id ?? '',
        skills: signal?.need?.skills.join(', ') ?? '',
        project_value: signal?.opportunity?.project_value ?? '',
        trades: signal?.opportunity?.trades.join(', ') ?? '',
        poll_options: pollOptions.length >= 2 ? pollOptions : [...pollOptions, ...emptyPollOptions()].slice(0, 2),
        link_url: signal?.link?.url ?? '',
        link_title: signal?.link?.title ?? '',
        link_description: signal?.link?.description ?? '',
        link_image: signal?.link?.image ?? '',
        media_ids: [] as string[],
    };
};

export function useSignalComposer(
    options: { onSuccess?: () => void; parentId?: string; initialType?: SignalType; editing?: FeedSignal } = {},
) {
    const user = computed(() => usePage().props.auth.user);
    const bodyEditor = ref<{ focus: () => void } | null>(null);
    const titleInput = ref<HTMLInputElement | null>(null);
    const previewing = ref(false);
    const preview = ref<SignalLink | null>(options.editing?.link ?? null);
    const previewSourceUrl = ref<string | null>(options.editing?.link?.url ?? null);
    const mediaKind = ref<MediaKind>('none');
    const mediaUploading = ref(false);

    const form = useForm(composerDefaults(options));

    const isEditing = computed(() => Boolean(options.editing));
    const isReply = computed(() => Boolean(options.parentId) || Boolean(options.editing?.is_reply));
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

        return (
            bodyText.value.length > 0
            || form.media_ids.length > 0
            || Boolean(form.link_url)
            || Boolean(options.editing?.media.length)
        );
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

    const submitLabel = computed(() => {
        if (isEditing.value) {
            return 'Save';
        }

        return isReply.value ? 'Reply' : meta.value.submit;
    });

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
        if (isReply.value || isEditing.value) {
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
        const defaults = composerDefaults(options);

        form.reset();
        form.type = defaults.type;
        form.parent_id = defaults.parent_id;
        form.title = defaults.title;
        form.body = defaults.body;
        form.budget = defaults.budget;
        form.timeline_amount = defaults.timeline_amount;
        form.timeline_unit = defaults.timeline_unit;
        form.location = defaults.location;
        form.latitude = defaults.latitude;
        form.longitude = defaults.longitude;
        form.place_id = defaults.place_id;
        form.skills = defaults.skills;
        form.project_value = defaults.project_value;
        form.trades = defaults.trades;
        form.poll_options = [...defaults.poll_options];
        form.link_url = defaults.link_url;
        form.link_title = defaults.link_title;
        form.link_description = defaults.link_description;
        form.link_image = defaults.link_image;
        form.media_ids = [];
        mediaKind.value = 'none';
        mediaUploading.value = false;
        preview.value = options.editing?.link ?? null;
        previewSourceUrl.value = options.editing?.link?.url ?? null;

        if (!options.editing?.link) {
            clearLink();
        }
    };

    const submit = () => {
        if (!canSubmit.value) {
            return;
        }

        const visit = form.transform((data) => {
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
        });

        const visitOptions = {
            preserveScroll: true,
            onSuccess: () => {
                resetComposer();
                options.onSuccess?.();
            },
            onError: (errors: Record<string, string>) => notifyFormError(errors),
            onFinish: () => form.transform((data) => data),
        };

        if (options.editing) {
            visit.patch(`/signals/${options.editing.id}`, visitOptions);
            return;
        }

        visit.post('/signals', visitOptions);
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
        isEditing,
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
