import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { enablePushNotifications, isPushSupported } from './pushSubscription';

const DISMISSED_KEY = 'notification-prompt-dismissed';

/**
 * Shown once, after first login (ProjectPlan.md §20.3/§21.2) - not on page load, and not
 * repeated once dismissed/answered. A manual "enable notifications" control belongs in
 * settings for anyone who skips this and wants to opt in later; this component is only
 * the one-time prompt.
 */
export function NotificationPermissionPrompt() {
  const { t } = useTranslation();
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (!isPushSupported()) return;
    if (localStorage.getItem(DISMISSED_KEY)) return;
    if (Notification.permission !== 'default') return;

    setVisible(true);
  }, []);

  function dismiss() {
    localStorage.setItem(DISMISSED_KEY, '1');
    setVisible(false);
  }

  async function enable() {
    try {
      await enablePushNotifications();
    } catch (error) {
      console.error('Failed to enable push notifications', error);
    } finally {
      dismiss();
    }
  }

  if (!visible) return null;

  return (
    <div className="mb-4 rounded-xl border border-x-border bg-x-warmbg p-4 text-sm text-slate-600">
      <p className="mb-3 font-semibold text-x-brown">{t('notifications.permission_prompt')}</p>
      <div className="flex gap-2">
        <Button onClick={() => void enable()}>{t('notifications.enable')}</Button>
        <Button variant="secondary" onClick={dismiss}>
          {t('notifications.not_now')}
        </Button>
      </div>
    </div>
  );
}
