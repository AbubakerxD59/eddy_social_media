export function formatUsdFromCents(cents: number | null | undefined): string | null {
    if (cents === null || cents === undefined) {
        return null;
    }

    const amount = cents / 100;

    return `$${amount.toLocaleString('en-US', {
        minimumFractionDigits: cents % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    })}`;
}

export function formatUsdAmount(value: string | number | null | undefined): string | null {
    if (value === null || value === undefined) {
        return null;
    }

    const text = String(value).trim();

    if (text === '') {
        return null;
    }

    let normalized = text
        .replace(/\b(USD|EUR|GBP|AED|PKR|INR|CAD|AUD)\b\.?/gi, '')
        .replace(/€|£|¥|₹|₨/g, '$')
        .replace(/Rs\.?/g, '$')
        .replace(/\$\s+/g, '$')
        .replace(/\$+/g, '$')
        .replace(/\s{2,}/g, ' ')
        .trim();

    if (normalized === '' || normalized === '$') {
        return null;
    }

    if (!normalized.includes('$') && /\d/.test(normalized)) {
        normalized = `$${normalized}`;
    }

    return normalized;
}

export function usdInputValue(value: string | number | null | undefined): string {
    return String(value ?? '').replace(/^\$\s*/u, '');
}

export function formatUsdHourly(cents: number | null | undefined): string | null {
    const amount = formatUsdFromCents(cents);

    return amount ? `${amount} / hour` : null;
}
