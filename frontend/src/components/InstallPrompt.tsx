import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from './ui/Button';

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
}

function isIOS(): boolean {
  return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

/**
 * ProjectPlan.md §10A.6a / §21.2a - a helpful recommendation alongside account creation,
 * never a gate: rendered below the complete-invitation form regardless of whether the
 * employee installs. Android/Chrome captures the browser's own `beforeinstallprompt` event
 * or promise-based fallback; iOS/Safari has no such event, so it gets a static numbered
 * guide instead.
 */
export function InstallPrompt() {
  const { t } = useTranslation();
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);

  useEffect(() => {
    function handler(event: Event) {
      event.preventDefault();
      setDeferredPrompt(event as BeforeInstallPromptEvent);
    }

    window.addEventListener('beforeinstallprompt', handler);
    return () => window.removeEventListener('beforeinstallprompt', handler);
  }, []);

  if (isIOS()) {
    return (
      <div className="mt-6 rounded-xl border border-x-border bg-x-warmbg p-4 text-sm text-slate-600">
        <p className="mb-2 font-semibold text-x-brown">{t('complete_invitation.install_hint')}</p>
        <p>{t('complete_invitation.install_ios')}</p>
      </div>
    );
  }

  if (!deferredPrompt) {
    return null;
  }

  return (
    <div className="mt-6 rounded-xl border border-x-border bg-x-warmbg p-4 text-sm text-slate-600">
      <p className="mb-3 font-semibold text-x-brown">{t('complete_invitation.install_hint')}</p>
      <Button
        type="button"
        onClick={() => {
          void deferredPrompt.prompt();
          setDeferredPrompt(null);
        }}
      >
        {t('complete_invitation.install_android')}
      </Button>
    </div>
  );
}
