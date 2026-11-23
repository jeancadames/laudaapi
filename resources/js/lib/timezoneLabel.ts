/** «America/Santo_Domingo» → «hora de Santo Domingo», para no mostrar códigos técnicos. */
export function timezoneLabel(timezone: string | null | undefined): string {
    const city = String(timezone ?? '')
        .split('/')
        .pop()
        ?.replace(/_/g, ' ')
        .trim();
    return city ? `hora de ${city}` : 'hora local';
}
