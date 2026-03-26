import { computed } from 'vue'

export function useCrmAssignedUser() {
    const assignedUserId = computed<string | null>(() => {
        if (typeof window === 'undefined') return null

        const url = new URL(window.location.href)
        return url.searchParams.get('assigned_user_id')
    })

    function withAssignedUser(url: string): string {
        if (!assignedUserId.value) return url

        const separator = url.includes('?') ? '&' : '?'
        return `${url}${separator}assigned_user_id=${assignedUserId.value}`
    }

    return {
        assignedUserId,
        withAssignedUser,
    }
}