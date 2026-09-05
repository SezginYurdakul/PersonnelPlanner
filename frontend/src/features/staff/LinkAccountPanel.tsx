import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Badge';
import { linkEmployeeUser, searchUnlinkedUsers, unlinkEmployeeUser } from './api';
import type { Employee } from '../../types/staff';

export function LinkAccountPanel({ employee }: { employee: Employee }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [isPicking, setIsPicking] = useState(false);

  const { data: candidates } = useQuery({
    queryKey: ['unlinked-users', search],
    queryFn: () => searchUnlinkedUsers(search),
    enabled: isPicking,
  });

  const linkMutation = useMutation({
    mutationFn: (userId: number) => linkEmployeeUser(employee.id, userId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] });
      setIsPicking(false);
    },
  });

  const unlinkMutation = useMutation({
    mutationFn: () => unlinkEmployeeUser(employee.id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] }),
  });

  return (
    <div>
      <h2 className="mb-3 text-sm font-semibold uppercase text-slate-500">
        {t('staff.linked_account')}
      </h2>

      {employee.has_account ? (
        <div className="flex items-center justify-between">
          <Badge tone="success">{t('staff.has_account')}</Badge>
          <Button variant="secondary" onClick={() => unlinkMutation.mutate()}>
            {t('common.unlink')}
          </Button>
        </div>
      ) : isPicking ? (
        <div>
          <input
            autoFocus
            placeholder={t('staff.search_users')}
            className="mb-2 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <ul className="max-h-40 overflow-y-auto rounded border border-slate-200">
            {candidates?.map((user) => (
              <li key={user.id}>
                <button
                  type="button"
                  className="w-full px-3 py-2 text-left text-sm hover:bg-slate-50"
                  onClick={() => linkMutation.mutate(user.id)}
                >
                  {user.name} &middot; {user.email}
                </button>
              </li>
            ))}
          </ul>
          <Button variant="secondary" className="mt-2" onClick={() => setIsPicking(false)}>
            {t('common.cancel')}
          </Button>
        </div>
      ) : (
        <div className="flex items-center justify-between">
          <Badge tone="warning">{t('staff.not_linked_yet')}</Badge>
          <Button variant="secondary" onClick={() => setIsPicking(true)}>
            {t('staff.link_account')}
          </Button>
        </div>
      )}
    </div>
  );
}
