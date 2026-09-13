import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Badge';
import { inviteUser } from '../auth/api';
import { linkEmployeeUser, searchUnlinkedUsers, unlinkEmployeeUser } from './api';
import type { Employee } from '../../types/staff';

type Mode = 'idle' | 'link_existing' | 'invite_new';

export function LinkAccountPanel({ employee }: { employee: Employee }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [mode, setMode] = useState<Mode>('idle');
  const [inviteEmail, setInviteEmail] = useState(employee.email ?? '');

  const { data: candidates } = useQuery({
    queryKey: ['unlinked-users', search],
    queryFn: () => searchUnlinkedUsers(search),
    enabled: mode === 'link_existing',
  });

  const linkMutation = useMutation({
    mutationFn: (userId: number) => linkEmployeeUser(employee.id, userId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] });
      setMode('idle');
    },
  });

  const unlinkMutation = useMutation({
    mutationFn: () => unlinkEmployeeUser(employee.id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] }),
  });

  const inviteMutation = useMutation({
    mutationFn: () =>
      inviteUser({
        name: `${employee.first_name} ${employee.last_name}`,
        email: inviteEmail,
        employee_id: employee.id,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] });
      setMode('idle');
    },
  });

  return (
    <div>
      <h2 className="mb-3 text-sm font-semibold uppercase text-slate-500">
        {t('staff.linked_account')}
      </h2>

      {employee.has_account ? (
        <div className="flex items-center justify-between">
          <Badge tone={employee.account_active ? 'success' : 'warning'}>
            {employee.account_active ? t('staff.has_account') : t('staff.awaiting_activation')}
          </Badge>
          <Button variant="secondary" onClick={() => unlinkMutation.mutate()}>
            {t('common.unlink')}
          </Button>
        </div>
      ) : mode === 'link_existing' ? (
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
          <Button variant="secondary" className="mt-2" onClick={() => setMode('idle')}>
            {t('common.cancel')}
          </Button>
        </div>
      ) : mode === 'invite_new' ? (
        <div>
          <label className="mb-1 block text-sm font-semibold text-slate-700">
            {t('staff.invite_email')}
          </label>
          <input
            autoFocus
            type="email"
            className="mb-2 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            value={inviteEmail}
            onChange={(e) => setInviteEmail(e.target.value)}
          />
          {inviteMutation.isError && (
            <p className="mb-2 text-sm text-red-600">{t('staff.invite_error')}</p>
          )}
          <div className="flex gap-2">
            <Button
              disabled={!inviteEmail || inviteMutation.isPending}
              onClick={() => inviteMutation.mutate()}
            >
              {t('staff.send_invite')}
            </Button>
            <Button variant="secondary" onClick={() => setMode('idle')}>
              {t('common.cancel')}
            </Button>
          </div>
        </div>
      ) : (
        <div className="flex items-center justify-between gap-2">
          <Badge tone="warning">{t('staff.not_linked_yet')}</Badge>
          <div className="flex gap-2">
            <Button variant="secondary" onClick={() => setMode('invite_new')}>
              {t('staff.invite_account')}
            </Button>
            <Button variant="secondary" onClick={() => setMode('link_existing')}>
              {t('staff.link_account')}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
