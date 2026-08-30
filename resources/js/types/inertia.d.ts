import { PageProps as InertiaPageProps } from '@inertiajs/core'

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps {
        flash?: {
            success?: string | null
            warning?: string | null
            error?: string | null
            info?: string | null
        }
    }
}
