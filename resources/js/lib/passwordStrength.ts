export type PasswordCheck = {
    id: string;
    label: string;
    passed: boolean;
};

export function passwordChecks(password: string): PasswordCheck[] {
    return [
        {
            id: 'length',
            label: '12+ characters',
            passed: password.length >= 12,
        },
        {
            id: 'lower',
            label: 'Lowercase',
            passed: /[a-z]/.test(password),
        },
        {
            id: 'upper',
            label: 'Uppercase',
            passed: /[A-Z]/.test(password),
        },
        {
            id: 'number',
            label: 'Number',
            passed: /\d/.test(password),
        },
        {
            id: 'symbol',
            label: 'Symbol',
            passed: /[^A-Za-z0-9]/.test(password),
        },
    ];
}
