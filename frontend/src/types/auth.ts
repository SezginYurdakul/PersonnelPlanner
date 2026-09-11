export type Locale = 'en' | 'tr' | 'nl' | 'es' | 'ro' | 'uk';

export type VisibilityScope = 'own' | 'line' | 'company';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  locale: Locale;
  visibility_scope: VisibilityScope;
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
