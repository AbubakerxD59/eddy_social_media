export type UserType = 'business' | 'talent' | 'explorer';

export type User = {
    id: number;
    name: string;
    full_name?: string | null;
    last_name?: string | null;
    business_name?: string | null;
    username: string;
    type?: UserType;
    compose_types?: Array<'drop' | 'need' | 'opportunity' | 'poll'>;
    email: string;
    headline?: string | null;
    bio?: string | null;
    website?: string | null;
    avatar?: string | null;
    cover?: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
