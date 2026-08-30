export function csrfHeaders(): HeadersInit {
    const token = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');

    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token
            ? { 'X-XSRF-TOKEN': decodeURIComponent(token) }
            : {}),
    };
}
