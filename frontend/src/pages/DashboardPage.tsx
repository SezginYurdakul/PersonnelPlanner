import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { fetchEmployees } from '../features/staff/api';

export function DashboardPage() {
  const { t } = useTranslation();

  const { data: unlinkedEmployees } = useQuery({
    queryKey: ['employees', { has_account: false }],
    queryFn: () => fetchEmployees({ has_account: false }),
  });

  return (
    <AppLayout>
      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <p className="text-sm text-slate-500">{t('staff.no_account')}</p>
          <p className="mt-1 text-2xl font-semibold text-slate-900">
            {unlinkedEmployees?.length ?? 0}
          </p>
          <Link to="/staff?has_account=false" className="mt-2 inline-block text-sm text-slate-600 hover:text-slate-900">
            {t('staff.title')} &rarr;
          </Link>
        </Card>
      </div>
    </AppLayout>
  );
}
