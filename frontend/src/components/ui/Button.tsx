import type { ButtonHTMLAttributes } from 'react';

type Variant = 'primary' | 'secondary' | 'danger';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
}

const variantClasses: Record<Variant, string> = {
  primary:
    'bg-x-gold text-x-brown hover:bg-x-gold-dark shadow-md shadow-x-gold/30 disabled:opacity-50 disabled:shadow-none',
  secondary:
    'bg-white text-slate-700 border border-x-border hover:bg-x-warmbg',
  danger: 'bg-rose-600 text-white hover:bg-rose-500 disabled:bg-rose-300',
};

export function Button({ variant = 'primary', className = '', ...props }: ButtonProps) {
  return (
    <button
      className={`rounded-xl px-4 py-2.5 text-sm font-bold transition-colors disabled:cursor-not-allowed ${variantClasses[variant]} ${className}`}
      {...props}
    />
  );
}
