export function formatRelativeTime(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const elapsed = Date.now() - date.getTime();

    if (Number.isNaN(date.getTime()) || elapsed < 45_000) {
        return 'now';
    }

    const minutes = Math.floor(elapsed / 60_000);

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours}h`;
    }

    const days = Math.floor(hours / 24);

    if (days < 7) {
        return `${days}d`;
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(date);
}
