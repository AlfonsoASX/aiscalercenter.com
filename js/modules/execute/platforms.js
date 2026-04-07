export const EXECUTE_STORAGE_BUCKET = 'scheduled-post-assets';

const COMMON_VISIBILITY_OPTIONS = [
    { value: 'public', label: 'Publico' },
    { value: 'connections', label: 'Solo conexiones' },
    { value: 'private', label: 'Privado' },
];

const YOUTUBE_CATEGORY_OPTIONS = [
    { value: '1', label: 'Film y animacion' },
    { value: '2', label: 'Autos y vehiculos' },
    { value: '10', label: 'Musica' },
    { value: '15', label: 'Mascotas y animales' },
    { value: '17', label: 'Deportes' },
    { value: '19', label: 'Viajes y eventos' },
    { value: '20', label: 'Videojuegos' },
    { value: '22', label: 'Personas y blogs' },
    { value: '23', label: 'Comedia' },
    { value: '24', label: 'Entretenimiento' },
    { value: '25', label: 'Noticias y politica' },
    { value: '26', label: 'Estilo y moda' },
    { value: '27', label: 'Educacion' },
    { value: '28', label: 'Ciencia y tecnologia' },
];

export const PLATFORM_DEFINITIONS = {
    facebook_page: {
        label: 'Facebook',
        accordionLabel: 'Configuracion de Facebook',
        icon: 'campaign',
        accent: '#1877F2',
        publicationTypes: [
            { value: 'post', label: 'Post' },
            { value: 'reel', label: 'Reel' },
            { value: 'story', label: 'Story' },
        ],
        fields: [
            { key: 'caption', label: 'Texto principal', type: 'textarea', applyTo: ['post', 'reel', 'story'], placeholder: 'Escribe el copy de Facebook' },
            { key: 'first_comment', label: 'Primer comentario', type: 'textarea', applyTo: ['post', 'reel'] },
            { key: 'link_url', label: 'Enlace', type: 'url', applyTo: ['post'] },
            { key: 'call_to_action', label: 'Call to action', type: 'text', applyTo: ['post'] },
            { key: 'location', label: 'Ubicacion', type: 'text', applyTo: ['post', 'reel'] },
        ],
        validate(context) {
            return validateFacebookLike('Facebook', context);
        },
    },
    facebook_profile: {
        label: 'Facebook perfil',
        accordionLabel: 'Configuracion de Facebook perfil',
        icon: 'person',
        accent: '#1877F2',
        publicationTypes: [
            { value: 'post', label: 'Post' },
            { value: 'reel', label: 'Reel' },
            { value: 'story', label: 'Story' },
        ],
        fields: [
            { key: 'caption', label: 'Texto principal', type: 'textarea', applyTo: ['post', 'reel', 'story'] },
            { key: 'first_comment', label: 'Primer comentario', type: 'textarea', applyTo: ['post', 'reel'] },
            { key: 'link_url', label: 'Enlace', type: 'url', applyTo: ['post'] },
            { key: 'location', label: 'Ubicacion', type: 'text', applyTo: ['post', 'reel'] },
        ],
        validate(context) {
            return validateFacebookLike('Facebook perfil', context);
        },
    },
    instagram: {
        label: 'Instagram',
        accordionLabel: 'Configuracion de Instagram',
        icon: 'photo_camera',
        accent: '#E1306C',
        publicationTypes: [
            { value: 'post', label: 'Post' },
            { value: 'reel', label: 'Reel' },
            { value: 'story', label: 'Story' },
        ],
        fields: [
            { key: 'caption', label: 'Caption', type: 'textarea', applyTo: ['post', 'reel', 'story'] },
            { key: 'hashtags', label: 'Hashtags', type: 'text', applyTo: ['post', 'reel'], placeholder: '#ia #marketing #ventas' },
            { key: 'location', label: 'Ubicacion', type: 'text', applyTo: ['post', 'reel'] },
            { key: 'collaborators', label: 'Colaboradores', type: 'text', applyTo: ['post', 'reel'], placeholder: '@marca1, @marca2' },
            { key: 'alt_text', label: 'Texto alternativo', type: 'textarea', applyTo: ['post'] },
        ],
        validate({ publicationType, assets, body, config }) {
            const errors = [];
            const resolvedCaption = pickText(config.caption, body);

            if (publicationType === 'post') {
                if (!hasImageOrVideo(assets)) {
                    errors.push('Instagram Post requiere al menos una imagen o video.');
                }

                if (!resolvedCaption) {
                    errors.push('Instagram Post requiere caption.');
                }
            }

            if (publicationType === 'reel') {
                if (!hasVideo(assets)) {
                    errors.push('Instagram Reel requiere un video.');
                }

                if (!resolvedCaption) {
                    errors.push('Instagram Reel requiere caption.');
                }
            }

            if (publicationType === 'story' && !hasImageOrVideo(assets)) {
                errors.push('Instagram Story requiere una imagen o video.');
            }

            return errors;
        },
    },
    youtube_channel: {
        label: 'YouTube',
        accordionLabel: 'Configuracion de YouTube',
        icon: 'smart_display',
        accent: '#FF0000',
        publicationTypes: [
            { value: 'short', label: 'Short' },
            { value: 'video', label: 'Video' },
        ],
        fields: [
            { key: 'title', label: 'Titulo del video o short', type: 'text', applyTo: ['short', 'video'], maxLength: 100 },
            {
                key: 'audience',
                label: 'Configuracion de audiencia',
                type: 'select',
                applyTo: ['short', 'video'],
                options: [
                    { value: 'not_for_kids', label: 'No, no es un video creado para ninos' },
                    { value: 'for_kids', label: 'Si, es un video creado para ninos' },
                ],
            },
            {
                key: 'privacy',
                label: 'Configuracion de privacidad',
                type: 'select',
                applyTo: ['short', 'video'],
                options: [
                    { value: 'public', label: 'Publico' },
                    { value: 'unlisted', label: 'Oculto' },
                    { value: 'private', label: 'Privado' },
                ],
            },
            {
                key: 'category',
                label: 'Categoria',
                type: 'select',
                applyTo: ['short', 'video'],
                options: YOUTUBE_CATEGORY_OPTIONS,
            },
            { key: 'playlist', label: 'Lista de reproduccion', type: 'text', applyTo: ['short', 'video'] },
            { key: 'tags', label: 'Etiquetas', type: 'text', applyTo: ['short', 'video'], placeholder: 'ia, ventas, automatizacion' },
            {
                key: 'notify_subscribers',
                label: 'Notificar suscriptores',
                type: 'select',
                applyTo: ['short', 'video'],
                options: [
                    { value: 'yes', label: 'Si' },
                    { value: 'no', label: 'No' },
                ],
            },
        ],
        validate({ assets, config }) {
            const errors = [];

            if (!hasVideo(assets)) {
                errors.push('YouTube requiere un video para publicar.');
            }

            if (!hasValue(config.title)) {
                errors.push('YouTube requiere titulo.');
            }

            if (!hasValue(config.audience)) {
                errors.push('YouTube requiere configuracion de audiencia.');
            }

            if (!hasValue(config.privacy)) {
                errors.push('YouTube requiere configuracion de privacidad.');
            }

            return errors;
        },
    },
    linkedin_profile: {
        label: 'LinkedIn perfil',
        accordionLabel: 'Configuracion de LinkedIn perfil',
        icon: 'badge',
        accent: '#0A66C2',
        publicationTypes: [
            { value: 'post', label: 'Post' },
            { value: 'article', label: 'Articulo' },
            { value: 'video', label: 'Video' },
        ],
        fields: [
            { key: 'headline', label: 'Titular', type: 'text', applyTo: ['article', 'video'] },
            { key: 'caption', label: 'Texto principal', type: 'textarea', applyTo: ['post', 'article', 'video'] },
            { key: 'link_url', label: 'Enlace', type: 'url', applyTo: ['post', 'article'] },
            {
                key: 'visibility',
                label: 'Visibilidad',
                type: 'select',
                applyTo: ['post', 'article', 'video'],
                options: COMMON_VISIBILITY_OPTIONS,
            },
            { key: 'first_comment', label: 'Primer comentario', type: 'textarea', applyTo: ['post', 'video'] },
        ],
        validate(context) {
            return validateLinkedInLike('LinkedIn perfil', context);
        },
    },
    linkedin_company: {
        label: 'LinkedIn empresa',
        accordionLabel: 'Configuracion de LinkedIn empresa',
        icon: 'apartment',
        accent: '#0A66C2',
        publicationTypes: [
            { value: 'post', label: 'Post' },
            { value: 'article', label: 'Articulo' },
            { value: 'video', label: 'Video' },
        ],
        fields: [
            { key: 'headline', label: 'Titular', type: 'text', applyTo: ['article', 'video'] },
            { key: 'caption', label: 'Texto principal', type: 'textarea', applyTo: ['post', 'article', 'video'] },
            { key: 'link_url', label: 'Enlace', type: 'url', applyTo: ['post', 'article'] },
            {
                key: 'visibility',
                label: 'Visibilidad',
                type: 'select',
                applyTo: ['post', 'article', 'video'],
                options: COMMON_VISIBILITY_OPTIONS,
            },
            { key: 'first_comment', label: 'Primer comentario', type: 'textarea', applyTo: ['post', 'video'] },
        ],
        validate(context) {
            return validateLinkedInLike('LinkedIn empresa', context);
        },
    },
    google_business_profile: {
        label: 'Google Business Profile',
        accordionLabel: 'Configuracion de Google Business Profile',
        icon: 'storefront',
        accent: '#188038',
        publicationTypes: [
            { value: 'update', label: 'Actualizacion' },
            { value: 'offer', label: 'Oferta' },
            { value: 'event', label: 'Evento' },
        ],
        fields: [
            { key: 'title', label: 'Titulo', type: 'text', applyTo: ['offer', 'event'] },
            { key: 'summary', label: 'Texto principal', type: 'textarea', applyTo: ['update', 'offer', 'event'] },
            {
                key: 'button_type',
                label: 'Boton',
                type: 'select',
                applyTo: ['update', 'offer', 'event'],
                options: [
                    { value: 'none', label: 'Sin boton' },
                    { value: 'book', label: 'Reservar' },
                    { value: 'learn_more', label: 'Mas informacion' },
                    { value: 'order', label: 'Ordenar' },
                    { value: 'shop', label: 'Comprar' },
                    { value: 'sign_up', label: 'Registrarse' },
                ],
            },
            { key: 'button_url', label: 'URL del boton', type: 'url', applyTo: ['update', 'offer', 'event'] },
            { key: 'coupon_code', label: 'Codigo promocional', type: 'text', applyTo: ['offer'] },
            { key: 'redeem_url', label: 'URL de redencion', type: 'url', applyTo: ['offer'] },
            { key: 'terms', label: 'Terminos y condiciones', type: 'textarea', applyTo: ['offer'] },
            { key: 'event_start', label: 'Inicio del evento', type: 'datetime-local', applyTo: ['event'] },
            { key: 'event_end', label: 'Fin del evento', type: 'datetime-local', applyTo: ['event'] },
        ],
        validate({ publicationType, body, config }) {
            const errors = [];
            const text = pickText(config.summary, body);

            if (!text) {
                errors.push('Google Business Profile requiere texto principal.');
            }

            if (publicationType === 'offer' && !hasValue(config.title)) {
                errors.push('Google Business Profile Oferta requiere titulo.');
            }

            if (publicationType === 'event') {
                if (!hasValue(config.title)) {
                    errors.push('Google Business Profile Evento requiere titulo.');
                }

                if (!hasValue(config.event_start)) {
                    errors.push('Google Business Profile Evento requiere fecha de inicio.');
                }
            }

            return errors;
        },
    },
};

export function getPlatformDefinition(providerKey) {
    return PLATFORM_DEFINITIONS[providerKey] ?? null;
}

export function buildInitialTarget(connection) {
    const definition = getPlatformDefinition(connection.provider_key);
    const defaultType = definition?.publicationTypes?.[0]?.value ?? 'post';
    const defaults = buildFieldDefaults(definition?.fields ?? [], defaultType);

    return {
        id: '',
        post_id: '',
        social_connection_id: String(connection.id ?? ''),
        provider_key: String(connection.provider_key ?? ''),
        connection_label: String(connection.display_name ?? connection.connection_label ?? definition?.label ?? ''),
        publication_type: defaultType,
        config: defaults,
        validation_snapshot: [],
    };
}

export function buildInitialProviderTarget(providerKey) {
    const definition = getPlatformDefinition(providerKey);
    const defaultType = definition?.publicationTypes?.[0]?.value ?? 'post';

    return {
        provider_key: providerKey,
        publication_type: defaultType,
        connection_ids: [],
        config: buildFieldDefaults(definition?.fields ?? [], defaultType),
        validation_snapshot: [],
    };
}

export function ensureTarget(target, connection) {
    const definition = getPlatformDefinition(connection.provider_key);
    const publicationType = String(target?.publication_type ?? definition?.publicationTypes?.[0]?.value ?? 'post');

    return {
        ...buildInitialTarget(connection),
        ...target,
        social_connection_id: String(target?.social_connection_id ?? connection.id ?? ''),
        provider_key: String(target?.provider_key ?? connection.provider_key ?? ''),
        connection_label: String(target?.connection_label ?? connection.display_name ?? connection.connection_label ?? definition?.label ?? ''),
        publication_type: publicationType,
        config: {
            ...buildFieldDefaults(definition?.fields ?? [], publicationType),
            ...(isPlainObject(target?.config) ? target.config : {}),
        },
        validation_snapshot: Array.isArray(target?.validation_snapshot) ? target.validation_snapshot : [],
    };
}

export function getVisibleFields(providerKey, publicationType) {
    const definition = getPlatformDefinition(providerKey);

    if (!definition) {
        return [];
    }

    return (definition.fields ?? []).filter((field) => {
        return !Array.isArray(field.applyTo) || field.applyTo.includes(publicationType);
    });
}

export function validateTargetDraft({ target, body, notes, assets }) {
    const definition = getPlatformDefinition(target.provider_key);

    if (!definition) {
        return ['Esta red social aun no tiene configuracion de publicacion.'];
    }

    return definition.validate({
        publicationType: target.publication_type,
        config: isPlainObject(target.config) ? target.config : {},
        body: String(body ?? '').trim(),
        notes: String(notes ?? '').trim(),
        assets: Array.isArray(assets) ? assets : [],
    });
}

export function getProviderGroupLabel(providerKey) {
    return getPlatformDefinition(providerKey)?.accordionLabel ?? providerKey;
}

function buildFieldDefaults(fields, publicationType) {
    return fields.reduce((accumulator, field) => {
        if (Array.isArray(field.applyTo) && !field.applyTo.includes(publicationType)) {
            return accumulator;
        }

        if (field.type === 'select') {
            accumulator[field.key] = field.options?.[0]?.value ?? '';
            return accumulator;
        }

        accumulator[field.key] = '';
        return accumulator;
    }, {});
}

function validateFacebookLike(label, { publicationType, assets, body, config }) {
    const errors = [];
    const resolvedCaption = pickText(config.caption, body);

    if (publicationType === 'post' && !resolvedCaption && !hasImageOrVideo(assets)) {
        errors.push(`${label} Post requiere texto o al menos un archivo.`);
    }

    if (publicationType === 'reel' && !hasVideo(assets)) {
        errors.push(`${label} Reel requiere un video.`);
    }

    if (publicationType === 'story' && !hasImageOrVideo(assets)) {
        errors.push(`${label} Story requiere una imagen o video.`);
    }

    return errors;
}

function validateLinkedInLike(label, { publicationType, assets, body, config }) {
    const errors = [];
    const resolvedCaption = pickText(config.caption, body);

    if (publicationType === 'post' && !resolvedCaption) {
        errors.push(`${label} Post requiere texto principal.`);
    }

    if (publicationType === 'article') {
        if (!hasValue(config.headline)) {
            errors.push(`${label} Articulo requiere titular.`);
        }

        if (!resolvedCaption && !hasValue(config.link_url)) {
            errors.push(`${label} Articulo requiere contenido o un enlace.`);
        }
    }

    if (publicationType === 'video') {
        if (!hasVideo(assets)) {
            errors.push(`${label} Video requiere un archivo de video.`);
        }

        if (!hasValue(config.headline) && !resolvedCaption) {
            errors.push(`${label} Video requiere titular o texto.`);
        }
    }

    return errors;
}

function pickText(primaryValue, fallbackValue) {
    return hasValue(primaryValue) ? String(primaryValue).trim() : (hasValue(fallbackValue) ? String(fallbackValue).trim() : '');
}

function hasValue(value) {
    return String(value ?? '').trim() !== '';
}

function hasVideo(assets) {
    return normalizedAssets(assets).some((asset) => asset.mimeType.startsWith('video/'));
}

function hasImageOrVideo(assets) {
    return normalizedAssets(assets).some((asset) => asset.mimeType.startsWith('image/') || asset.mimeType.startsWith('video/'));
}

function normalizedAssets(assets) {
    return (Array.isArray(assets) ? assets : []).map((asset) => {
        return {
            mimeType: String(asset?.mime_type ?? asset?.mimeType ?? '').toLowerCase(),
        };
    });
}

function isPlainObject(value) {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}
