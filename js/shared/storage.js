export const USER_FILES_STORAGE_BUCKET = 'user-files';

export const STORAGE_SCOPES = {
    blog: 'blog',
    courses: 'courses',
    execute: 'execute',
    landingPages: 'landing-pages',
    projects: 'projects',
    tools: 'tools',
};

export function buildUserStoragePath(userId, scope, ...segments) {
    return [
        normalizeStorageSegment(userId),
        normalizeStorageSegment(scope),
        ...segments.map(normalizeStorageSegment).filter(Boolean),
    ].filter(Boolean).join('/');
}

export function getStorageBucketForScopedPath(path, scope, legacyBucket = USER_FILES_STORAGE_BUCKET) {
    const parts = String(path ?? '').split('/').filter(Boolean);
    return parts[1] === scope ? USER_FILES_STORAGE_BUCKET : legacyBucket;
}

export function extractStoragePathFromUrl(url, buckets = [USER_FILES_STORAGE_BUCKET]) {
    if (!url) {
        return null;
    }

    const value = String(url);

    if (!value.includes('://') && !value.startsWith('/')) {
        return {
            bucket: USER_FILES_STORAGE_BUCKET,
            path: value,
        };
    }

    try {
        const parsed = new URL(value, window.location.origin);

        for (const bucket of buckets) {
            const prefixes = [
                `/storage/v1/object/public/${bucket}/`,
                `/storage/v1/object/sign/${bucket}/`,
            ];

            for (const prefix of prefixes) {
                const index = parsed.pathname.indexOf(prefix);

                if (index !== -1) {
                    return {
                        bucket,
                        path: decodeURIComponent(parsed.pathname.slice(index + prefix.length)),
                    };
                }
            }
        }
    } catch (error) {
        return null;
    }

    return null;
}

export function normalizeStorageSegment(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9.\-_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase();
}
