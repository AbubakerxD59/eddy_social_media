import type { SignalType } from '@/types/social';

export type SignalTypeMeta = {
    value: SignalType;
    label: string;
    description: string;
    placeholder: string;
    submit: string;
    badgeClass: string;
    selectedClass: string;
    accentClass: string;
};

export const SIGNAL_TYPES: SignalTypeMeta[] = [
    {
        value: 'drop',
        label: 'Drop',
        description: 'Share an idea, win, or update.',
        placeholder: 'Share an idea, win, or update.',
        submit: 'Drop',
        badgeClass: 'bg-drop text-white',
        selectedClass: 'border-drop/50 bg-drop/10',
        accentClass: 'text-drop',
    },
    {
        value: 'need',
        label: 'Need',
        description: 'Tell the universe what you need.',
        placeholder: 'Describe what you need…',
        submit: 'Post need',
        badgeClass: 'bg-need text-neutral-950',
        selectedClass: 'border-need/50 bg-need/10',
        accentClass: 'text-need',
    },
    {
        value: 'opportunity',
        label: 'Opportunity',
        description: 'Post an opportunity or gig.',
        placeholder: 'Describe the opportunity…',
        submit: 'Post opportunity',
        badgeClass: 'bg-opportunity text-neutral-950',
        selectedClass: 'border-opportunity/50 bg-opportunity/10',
        accentClass: 'text-opportunity',
    },
    {
        value: 'poll',
        label: 'Poll',
        description: 'Ask and get opinions.',
        placeholder: 'Ask a question…',
        submit: 'Post poll',
        badgeClass: 'bg-poll text-white',
        selectedClass: 'border-poll/50 bg-poll/10',
        accentClass: 'text-poll',
    },
];

export const signalTypeMeta = (type: SignalType): SignalTypeMeta =>
    SIGNAL_TYPES.find((item) => item.value === type) ?? SIGNAL_TYPES[0];
