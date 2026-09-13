import { apiClient } from '../../api/client';

export async function registerPushSubscription(subscription: PushSubscriptionJSON): Promise<void> {
  await apiClient.post('/me/push-subscriptions', subscription);
}
