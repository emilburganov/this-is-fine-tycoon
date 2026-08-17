const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function saveId() {
    return localStorage.getItem('fine_save') || '';
}

function remember(payload) {
    const uuid = payload?.player?.uuid || payload?.state?.player?.uuid;
    if (uuid) {
        localStorage.setItem('fine_save', uuid);
    }
    return payload;
}

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token(),
            'X-Requested-With': 'XMLHttpRequest',
            'X-Fine-Save': saveId(),
            ...(options.headers ?? {}),
        },
        ...options,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message = data.message || Object.values(data.errors ?? {})[0]?.[0] || 'Сервер не в настроении';
        throw new Error(message);
    }

    return remember(data);
}

export const api = {
    load: () => request('/api/game'),
    sip: (count, combo) =>
        request('/api/game/sip', {
            method: 'POST',
            body: JSON.stringify({ count, combo }),
        }),
    upgrade: (id) =>
        request('/api/game/upgrade', {
            method: 'POST',
            body: JSON.stringify({ id }),
        }),
    action: (name) =>
        request('/api/game/action', {
            method: 'POST',
            body: JSON.stringify({ name }),
        }),
    monetize: (sku) =>
        request('/api/game/monetize', {
            method: 'POST',
            body: JSON.stringify({ sku }),
        }),
    reset: async () => {
        localStorage.removeItem('fine_save');
        return request('/api/game/reset', { method: 'POST' });
    },
};
