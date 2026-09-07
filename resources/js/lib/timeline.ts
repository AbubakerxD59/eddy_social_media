export const TIMELINE_UNITS = ['hours', 'days', 'weeks', 'months'] as const;

export type TimelineUnit = (typeof TIMELINE_UNITS)[number];

const UNIT_LABELS: Record<TimelineUnit, { one: string; many: string; option: string }> = {
    hours: { one: 'hour', many: 'hours', option: 'Hours' },
    days: { one: 'day', many: 'days', option: 'Days' },
    weeks: { one: 'week', many: 'weeks', option: 'Weeks' },
    months: { one: 'month', many: 'months', option: 'Months' },
};

export const TIMELINE_UNIT_OPTIONS = TIMELINE_UNITS.map((value) => ({
    value,
    label: UNIT_LABELS[value].option,
}));

export function isTimelineUnit(value: string): value is TimelineUnit {
    return (TIMELINE_UNITS as readonly string[]).includes(value);
}

export function formatTimelineDuration(amount: string | number | null | undefined, unit: string | null | undefined): string {
    const count = typeof amount === 'number' ? amount : Number.parseInt(String(amount ?? '').trim(), 10);

    if (!Number.isFinite(count) || count < 1) {
        return '';
    }

    const candidate = unit ?? '';
    const resolved: TimelineUnit = isTimelineUnit(candidate) ? candidate : 'days';
    const labels = UNIT_LABELS[resolved];

    return `${count} ${count === 1 ? labels.one : labels.many}`;
}
