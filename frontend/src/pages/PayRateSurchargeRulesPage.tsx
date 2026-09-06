import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { PayRateSurchargeRuleFormModal } from '../features/lines/PayRateSurchargeRuleFormModal';
import {
  createPayRateSurchargeRule,
  fetchPayRateSurchargeRules,
  updatePayRateSurchargeRule,
} from '../features/lines/api';
import type { PayRateSurchargeRule, PayRateSurchargeRuleFormValues } from '../types/lines';

export function PayRateSurchargeRulesPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState<PayRateSurchargeRule | null>(null);
  const [isCreating, setIsCreating] = useState(false);

  const { data: rules, isLoading } = useQuery({
    queryKey: ['pay-rate-surcharge-rules'],
    queryFn: fetchPayRateSurchargeRules,
  });

  const createMutation = useMutation({
    mutationFn: (values: PayRateSurchargeRuleFormValues) => createPayRateSurchargeRule(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['pay-rate-surcharge-rules'] }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: number; values: PayRateSurchargeRuleFormValues }) =>
      updatePayRateSurchargeRule(id, values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['pay-rate-surcharge-rules'] }),
  });

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('pay_rate_rules.title')}</h1>
        <Button onClick={() => setIsCreating(true)}>{t('pay_rate_rules.new')}</Button>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : rules && rules.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('pay_rate_rules.name')}</th>
                <th className="px-6 py-3">{t('pay_rate_rules.days_of_week')}</th>
                <th className="px-6 py-3">{t('pay_rate_rules.start_time')}</th>
                <th className="px-6 py-3">{t('pay_rate_rules.end_time')}</th>
                <th className="px-6 py-3">{t('pay_rate_rules.surcharge_percentage')}</th>
                <th className="px-6 py-3">{t('common.active')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {rules.map((rule) => (
                <tr key={rule.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">{rule.name}</td>
                  <td className="px-6 py-3 text-slate-600">
                    {rule.days_of_week.map((d) => t(`pay_rate_rules.day.${d}`)).join(', ')}
                  </td>
                  <td className="px-6 py-3 tabular-nums text-slate-600">{rule.start_time.slice(0, 5)}</td>
                  <td className="px-6 py-3 tabular-nums text-slate-600">{rule.end_time.slice(0, 5)}</td>
                  <td className="px-6 py-3 tabular-nums text-slate-600">+{rule.surcharge_percentage}%</td>
                  <td className="px-6 py-3">
                    <Badge tone={rule.is_active ? 'success' : 'neutral'}>
                      {rule.is_active ? t('common.active') : t('common.inactive')}
                    </Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    <button
                      type="button"
                      className="text-sm font-medium text-slate-600 hover:text-slate-900"
                      onClick={() => setEditing(rule)}
                    >
                      {t('common.edit')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="p-6 text-sm text-slate-500">{t('pay_rate_rules.empty')}</p>
        )}
      </Card>

      {isCreating && (
        <PayRateSurchargeRuleFormModal
          rule={null}
          onClose={() => setIsCreating(false)}
          onSubmit={async (values) => {
            await createMutation.mutateAsync(values);
          }}
        />
      )}

      {editing && (
        <PayRateSurchargeRuleFormModal
          rule={editing}
          onClose={() => setEditing(null)}
          onSubmit={async (values) => {
            await updateMutation.mutateAsync({ id: editing.id, values });
          }}
        />
      )}
    </AppLayout>
  );
}
