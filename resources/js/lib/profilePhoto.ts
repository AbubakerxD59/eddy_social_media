import { router } from '@inertiajs/vue3';
import { csrfFormHeaders } from '@/lib/csrf';
import { firstValidationError, notifyError, notifySuccess } from '@/lib/notify';

export type ProfilePhotoKind = 'avatar' | 'cover';

export async function uploadProfilePhoto(kind: ProfilePhotoKind, file: File): Promise<string | null> {
    const payload = new FormData();
    payload.append('kind', kind);
    payload.append('photo', file);

    try {
        const response = await fetch('/settings/profile/photo', {
            method: 'POST',
            headers: csrfFormHeaders(),
            credentials: 'same-origin',
            body: payload,
        });

        const data = (await response.json()) as {
            message?: string;
            url?: string;
            errors?: Record<string, string | string[]>;
        };

        if (!response.ok) {
            notifyError(firstValidationError(data.errors) ?? data.message ?? 'Could not update that photo.');
            return null;
        }

        notifySuccess(data.message ?? (kind === 'cover' ? 'Cover photo updated.' : 'Profile picture updated.'));
        router.reload({ only: ['profile', 'auth'] });

        return data.url ?? null;
    } catch {
        notifyError('Could not update that photo.');
        return null;
    }
}
