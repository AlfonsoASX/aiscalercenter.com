export async function resolvePanelToolModule(moduleKey, {
    getAccessToken,
    supabase,
    getCurrentUser,
    getActiveProject,
    showNotice,
    humanizeError,
}) {
    if (
        moduleKey === 'research_market_signals'
        || moduleKey === 'research_google'
        || moduleKey === 'research_youtube'
        || moduleKey === 'research_mercado_libre'
        || moduleKey === 'research_amazon'
    ) {
        const { createResearchModule } = await import('../research/index.js');

        return createResearchModule({
            getAccessToken,
            ...resolveResearchPreset(moduleKey),
        });
    }

    if (moduleKey === 'social_post_scheduler') {
        const { createExecuteModule } = await import('../execute/index.js');

        return createExecuteModule({
            supabase,
            getCurrentUser,
            getActiveProject,
            showNotice,
            humanizeError,
        });
    }

    return null;
}

function resolveResearchPreset(moduleKey) {
    if (moduleKey === 'research_google') {
        return {
            providerKey: 'google',
            providerLabel: 'Google',
        };
    }

    if (moduleKey === 'research_youtube') {
        return {
            providerKey: 'youtube',
            providerLabel: 'YouTube',
        };
    }

    if (moduleKey === 'research_mercado_libre') {
        return {
            providerKey: 'mercado_libre',
            providerLabel: 'Mercado Libre',
        };
    }

    if (moduleKey === 'research_amazon') {
        return {
            providerKey: 'amazon',
            providerLabel: 'Amazon',
        };
    }

    return {
        providerKey: '',
        providerLabel: '',
    };
}
