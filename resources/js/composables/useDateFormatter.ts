import { usePage } from '@inertiajs/vue3'

export function useDateFormatter() {
    const page = usePage()

    const settings = page.props.appSettings as {
        currency?: string
        dateFormat?: string
        locale?: string
        timezone?: string
    }

    function formatDate(
        value: string | Date | null | undefined,
        options?: Intl.DateTimeFormatOptions
    ): string {
        if (!value) return '—'

        const date = value instanceof Date ? value : new Date(value)
        if (Number.isNaN(date.getTime())) return '—'

        const locale = settings?.locale || 'es-DO'
        const timezone = settings?.timezone || 'America/Santo_Domingo'

        return new Intl.DateTimeFormat(locale, {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            ...options,
        }).format(date)
    }

    function formatDateTime(
        value: string | Date | null | undefined,
        options?: Intl.DateTimeFormatOptions
    ): string {
        if (!value) return '—'

        const date = value instanceof Date ? value : new Date(value)
        if (Number.isNaN(date.getTime())) return '—'

        const locale = settings?.locale || 'es-DO'
        const timezone = settings?.timezone || 'America/Santo_Domingo'

        return new Intl.DateTimeFormat(locale, {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            ...options,
        }).format(date)
    }

    return {
        formatDate,
        formatDateTime,
    }
}