import type { ReactNode } from 'react';
import { Button } from './Button';

interface ConfirmDialogProps {
  title: string;
  message: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  danger?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

export function ConfirmDialog({
  title,
  message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  danger = false,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-sm rounded-2xl border border-x-border bg-white p-6 shadow-xl">
        <h3 className="text-base font-extrabold text-x-brown">{title}</h3>
        <div className="mt-2 text-sm text-slate-600">{message}</div>
        <div className="mt-6 flex justify-end gap-3">
          <Button variant="secondary" type="button" onClick={onCancel}>
            {cancelLabel}
          </Button>
          <Button variant={danger ? 'danger' : 'primary'} type="button" onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
