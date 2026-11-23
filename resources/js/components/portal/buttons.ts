/**
 * Botones del portal del cliente. Rojo solo para la acción principal de
 * autoservicio; negro para abrir una aplicación; blanco para lo secundario.
 */
const base =
    'inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

export const portalButton = {
    primary: `${base} bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600`,
    dark: `${base} bg-slate-950 text-white hover:bg-slate-800 focus-visible:outline-slate-950 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200`,
    secondary: `${base} border border-slate-200 bg-white text-slate-800 hover:bg-slate-50 focus-visible:outline-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-900`,
    link: 'inline-flex min-h-10 items-center gap-1 text-sm font-medium text-slate-600 underline-offset-4 hover:text-slate-950 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-400 disabled:cursor-not-allowed disabled:opacity-50 dark:text-slate-300 dark:hover:text-white',
} as const;
