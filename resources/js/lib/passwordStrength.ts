export type PasswordCheck = {
    id: string;
    label: string;
    passed: boolean;
};

export function passwordChecks(password: string): PasswordCheck[] {
    return [
        {
            id: 'length',
            label: 'At least 12 characters',
            passed: password.length >= 12,
        },
        {
            id: 'lower',
            label: 'One lowercase letter',
            passed: /[a-z]/.test(password),
        },
        {
            id: 'upper',
            label: 'One uppercase letter',
            passed: /[A-Z]/.test(password),
        },
        {
            id: 'number',
            label: 'One number',
            passed: /\d/.test(password),
        },
        {
            id: 'symbol',
            label: 'One symbol',
            passed: /[^A-Za-z0-9]/.test(password),
        },
    ];
}
