export type IdentityTransfer = {
    stage: string;
    service: string;
    token: string;
    deadline: number;
};
export const identityStorageKey = 'lauda.business.identity.pending.v2';
let incoming: { transfer: IdentityTransfer | null; invalid: boolean } | null =
    null;

/** Run before Inertia initializes so its history never captures the credential fragment. */
export function captureBusinessIdentityHandoff(): void {
    if (
        typeof window === 'undefined' ||
        location.pathname !== '/business/identity' ||
        !location.hash
    )
        return;
    const hash = new URLSearchParams(location.hash.slice(1));
    history.replaceState(
        history.state,
        '',
        location.pathname + location.search,
    );
    const token = hash.get('token'),
        stage = hash.get('stage') ?? '',
        service = hash.get('service') ?? '';
    const valid =
        token &&
        token.length <= 16384 &&
        ['request', 'proof', 'activation', 'ready', 'launch'].includes(stage) &&
        ['ecf', 'pos', 'bys', 'cumplimiento'].includes(service);
    const transfer = valid
        ? {
              stage,
              service,
              token,
              deadline: Math.floor(Date.now() / 1000) + 600,
          }
        : null;
    incoming = { transfer, invalid: !valid };
    try {
        if (transfer)
            sessionStorage.setItem(
                identityStorageKey,
                JSON.stringify(transfer),
            );
        else sessionStorage.removeItem(identityStorageKey);
    } catch {
        /* In-memory handoff still works when tab storage is unavailable. */
    }
}

export function takeBusinessIdentityHandoff() {
    const result = incoming;
    incoming = null;
    return result;
}
