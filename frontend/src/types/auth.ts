export type Locale = 'en' | 'tr' | 'nl' | 'es' | 'ro' | 'uk';

export type VisibilityScope = 'own' | 'line' | 'company';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  locale: Locale;
  visibility_scope: VisibilityScope;
  is_active: boolean;
  invited_at: string | null;
  activated_at: string | null;
  invitation_pending: boolean;
  roles: string[];
  employee: {
    id: number;
    first_name: string;
    last_name: string;
  } | null;
}

export interface LoginPayload {
  email: string;
  password: string;
}
