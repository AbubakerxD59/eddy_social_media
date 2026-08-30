export type TextPart = {
    type: 'text' | 'link';
    value: string;
};

const urlPattern = /(https?:\/\/[^\s<]+)/gi;

const trailingPunctuation = /[)\]}.,;:!?]+$/;

export function hrefForUrl(url: string): string {
    return /^https?:\/\//i.test(url) ? url : `https://${url}`;
}

export function linkifyParts(text: string): TextPart[] {
    return text.split(urlPattern).filter(Boolean).flatMap((part): TextPart[] => {
        if (!/^https?:\/\//i.test(part)) {
            return [{ type: 'text', value: part }];
        }

        const punctuation = part.match(trailingPunctuation)?.[0] ?? '';
        const url = punctuation ? part.slice(0, -punctuation.length) : part;

        if (!url) {
            return [{ type: 'text', value: part }];
        }

        return punctuation
            ? [
                { type: 'link', value: url },
                { type: 'text', value: punctuation },
            ]
            : [{ type: 'link', value: url }];
    });
}
