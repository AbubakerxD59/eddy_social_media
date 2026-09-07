const CAPTION_URL = /\b(?:https?:\/\/|www\.)[^\s<>"'`]+/i;

export function firstCaptionUrl(text: string): string | null {
    const match = text.match(CAPTION_URL);

    if (!match) {
        return null;
    }

    let url = match[0].replace(/[),.!?;:]+$/g, '');

    if (!/^https?:\/\//i.test(url)) {
        url = `https://${url}`;
    }

    return url;
}

export function urlsMatch(left: string, right: string): boolean {
    return left.replace(/\/+$/, '') === right.replace(/\/+$/, '');
}
