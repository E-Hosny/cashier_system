export const SIZE_TRANSLATIONS = {
    small: 'صغير',
    medium: 'وسط',
    large: 'كبير',
    extra_large: 'كان كبير',
};

export function translateSize(size) {
    if (size == null || size === '') {
        return '—';
    }

    return SIZE_TRANSLATIONS[size] ?? size;
}
