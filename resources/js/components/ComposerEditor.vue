<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Italic, Link as LinkIcon, List, ListOrdered, Strikethrough, Underline } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const body = defineModel<string>('modelValue', { default: '' });

const { placeholder = 'Write something…' } = defineProps<{
    placeholder?: string;
}>();

const linkOpen = ref(false);
const linkUrl = ref('');
const selectionTick = ref(0);

const editor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: false,
            codeBlock: false,
            code: false,
            blockquote: false,
            horizontalRule: false,
            link: {
                openOnClick: false,
                autolink: true,
                HTMLAttributes: {
                    rel: 'noopener noreferrer nofollow',
                    target: '_blank',
                },
            },
        }),
        Placeholder.configure({
            placeholder: () => placeholder,
        }),
    ],
    content: body.value || '',
    editorProps: {
        attributes: {
            class: 'signal-editor-content min-h-32 max-h-72 overflow-y-auto px-3 py-2.5 text-[15px] leading-relaxed outline-none',
        },
    },
    onUpdate: ({ editor: instance }) => {
        body.value = instance.isEmpty ? '' : instance.getHTML();
        selectionTick.value += 1;
    },
    onSelectionUpdate: () => {
        selectionTick.value += 1;
    },
});

watch(body, (value) => {
    if (!editor.value) {
        return;
    }

    const next = value || '';
    const current = editor.value.isEmpty ? '' : editor.value.getHTML();

    if (next === current) {
        return;
    }

    editor.value.commands.setContent(next, { emitUpdate: false });
});

watch(
    () => placeholder,
    () => {
        const instance = editor.value;

        if (!instance?.view) {
            return;
        }

        instance.view.dispatch(instance.state.tr);
    },
);

const isActive = (name: string) => {
    selectionTick.value;

    return Boolean(editor.value?.isActive(name));
};

const toggleMark = (name: 'toggleBold' | 'toggleItalic' | 'toggleUnderline' | 'toggleStrike' | 'toggleBulletList' | 'toggleOrderedList') => {
    editor.value?.chain().focus()[name]().run();
};

const toggleLink = () => {
    if (!editor.value) {
        return;
    }

    if (editor.value.isActive('link')) {
        editor.value.chain().focus().unsetLink().run();
        linkOpen.value = false;
        return;
    }

    linkUrl.value = editor.value.getAttributes('link').href ?? '';
    linkOpen.value = true;
};

const applyLink = () => {
    if (!editor.value) {
        return;
    }

    const raw = linkUrl.value.trim();

    if (raw === '') {
        editor.value.chain().focus().unsetLink().run();
        linkOpen.value = false;
        return;
    }

    const href = /^https?:\/\//i.test(raw) || raw.startsWith('mailto:') ? raw : `https://${raw}`;

    editor.value.chain().focus().setLink({ href }).run();
    linkOpen.value = false;
};

const focus = () => {
    editor.value?.commands.focus();
};

onBeforeUnmount(() => {
    editor.value?.destroy();
});

defineExpose({ focus });
</script>

<template>
    <div class="border-input bg-background/40 rounded-xl border">
        <div class="flex flex-wrap items-center gap-0.5 border-b px-1.5 py-1" @mousedown.prevent>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="rounded-md"
                :class="isActive('bold') && 'bg-accent text-foreground'"
                aria-label="Bold"
                :aria-pressed="isActive('bold')"
                @click="toggleMark('toggleBold')"
            >
                <Bold class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="rounded-md"
                :class="isActive('italic') && 'bg-accent text-foreground'"
                aria-label="Italic"
                :aria-pressed="isActive('italic')"
                @click="toggleMark('toggleItalic')"
            >
                <Italic class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="rounded-md"
                :class="isActive('underline') && 'bg-accent text-foreground'"
                aria-label="Underline"
                :aria-pressed="isActive('underline')"
                @click="toggleMark('toggleUnderline')"
            >
                <Underline class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="rounded-md"
                :class="isActive('strike') && 'bg-accent text-foreground'"
                aria-label="Strikethrough"
                :aria-pressed="isActive('strike')"
                @click="toggleMark('toggleStrike')"
            >
                <Strikethrough class="size-3.5" />
            </Button>
            <span class="bg-border mx-1 h-4 w-px" />
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="rounded-md"
                :class="isActive('bulletList') && 'bg-accent text-foreground'"
                aria-label="Bullet list"
                :aria-pressed="isActive('bulletList')"
                @click="toggleMark('toggleBulletList')"
            >
                <List class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="rounded-md"
                :class="isActive('orderedList') && 'bg-accent text-foreground'"
                aria-label="Numbered list"
                :aria-pressed="isActive('orderedList')"
                @click="toggleMark('toggleOrderedList')"
            >
                <ListOrdered class="size-3.5" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="rounded-md"
                :class="isActive('link') && 'bg-accent text-foreground'"
                aria-label="Link"
                :aria-pressed="isActive('link')"
                @click="toggleLink"
            >
                <LinkIcon class="size-3.5" />
            </Button>
        </div>

        <div v-if="linkOpen" class="flex items-center gap-2 border-b px-2 py-2">
            <Input
                v-model="linkUrl"
                type="text"
                inputmode="url"
                placeholder="https://"
                class="h-8 rounded-lg"
                @keydown.enter.prevent="applyLink"
            />
            <Button type="button" size="sm" class="rounded-full" @click="applyLink">
                Apply
            </Button>
        </div>

        <EditorContent :editor="editor" class="signal-editor" />
    </div>
</template>
