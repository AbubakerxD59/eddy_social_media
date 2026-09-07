export function htmlToPlainText(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const withBreaks = value.replace(/<br\s*\/?>/gi, '\n').replace(/<\/(p|li)>/gi, '\n');
    const withoutTags = withBreaks.replace(/<[^>]+>/g, '');
    const decoded = withoutTags
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&quot;/gi, '"')
        .replace(/&#39;/gi, "'");

    return decoded.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
}

export function isRichHtml(value: string | null | undefined): boolean {
    return Boolean(value && /<(p|br|strong|b|em|i|u|s|ul|ol|li|a)\b/i.test(value));
}
