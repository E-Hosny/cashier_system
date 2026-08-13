import { computed, onMounted, onUnmounted, ref } from 'vue';

const DISMISS_KEY = 'pwa-install-dismissed-at';
const DISMISS_DAYS = 14;

const deferredPrompt = ref(null);
const showAndroidInstall = ref(false);
const showIosInstall = ref(false);
const showUpdate = ref(false);
const isInstalled = ref(false);

let initialized = false;
let waitingWorker = null;

function isStandaloneDisplay() {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true ||
        document.referrer.includes('android-app://')
    );
}

function isIosDevice() {
    const ua = window.navigator.userAgent || '';
    const iOS = /iPad|iPhone|iPod/.test(ua);
    const iPadOs = window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1;
    return iOS || iPadOs;
}

function isDismissedRecently() {
    try {
        const raw = localStorage.getItem(DISMISS_KEY);
        if (!raw) {
            return false;
        }
        const dismissedAt = Number(raw);
        if (!Number.isFinite(dismissedAt)) {
            return false;
        }
        return Date.now() - dismissedAt < DISMISS_DAYS * 24 * 60 * 60 * 1000;
    } catch {
        return false;
    }
}

function trackWaitingWorker(worker) {
    if (!worker) {
        return;
    }
    waitingWorker = worker;
    showUpdate.value = true;
}

function onBeforeInstallPrompt(event) {
    event.preventDefault();
    deferredPrompt.value = event;
    if (!isStandaloneDisplay() && !isDismissedRecently()) {
        showAndroidInstall.value = true;
        showIosInstall.value = false;
    }
}

function onAppInstalled() {
    deferredPrompt.value = null;
    showAndroidInstall.value = false;
    showIosInstall.value = false;
    isInstalled.value = true;
    try {
        localStorage.removeItem(DISMISS_KEY);
    } catch {
        // ignore
    }
}

/**
 * Registers the service worker once and wires install/update events.
 * Safe to call multiple times.
 */
export function initPwa() {
    if (typeof window === 'undefined' || initialized) {
        return;
    }
    initialized = true;

    isInstalled.value = isStandaloneDisplay();

    if (!isInstalled.value && !isDismissedRecently() && isIosDevice()) {
        showIosInstall.value = true;
    }

    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onAppInstalled);

    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                if (registration.waiting) {
                    trackWaitingWorker(registration.waiting);
                }

                registration.addEventListener('updatefound', () => {
                    const installing = registration.installing;
                    if (!installing) {
                        return;
                    }
                    installing.addEventListener('statechange', () => {
                        if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                            trackWaitingWorker(registration.waiting || installing);
                        }
                    });
                });
            })
            .catch(() => {
                // Fails on insecure origins (http:// LAN IP without HTTPS).
            });
    });

    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshing) {
            return;
        }
        refreshing = true;
        window.location.reload();
    });
}

export function usePwaInstall() {
    const canInstall = computed(() => showAndroidInstall.value || showIosInstall.value);

    onMounted(() => {
        initPwa();
    });

    // Keep composable API stable even if multiple components mount.
    onUnmounted(() => {});

    const dismiss = () => {
        showAndroidInstall.value = false;
        showIosInstall.value = false;
        try {
            localStorage.setItem(DISMISS_KEY, String(Date.now()));
        } catch {
            // ignore
        }
    };

    const promptInstall = async () => {
        if (!deferredPrompt.value) {
            return false;
        }

        deferredPrompt.value.prompt();
        const choice = await deferredPrompt.value.userChoice;
        deferredPrompt.value = null;
        showAndroidInstall.value = false;

        return choice.outcome === 'accepted';
    };

    const applyUpdate = () => {
        if (!waitingWorker) {
            window.location.reload();
            return;
        }
        waitingWorker.postMessage({ type: 'SKIP_WAITING' });
    };

    return {
        canInstall,
        showAndroidInstall,
        showIosInstall,
        showUpdate,
        isInstalled,
        dismiss,
        promptInstall,
        applyUpdate,
    };
}
