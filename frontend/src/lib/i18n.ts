import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import en from '../locales/en/common.json';
import tr from '../locales/tr/common.json';
import nl from '../locales/nl/common.json';
import es from '../locales/es/common.json';
import ro from '../locales/ro/common.json';
import uk from '../locales/uk/common.json';

export const SUPPORTED_LOCALES = ['en', 'tr', 'nl', 'es', 'ro', 'uk'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

void i18n
  .use(initReactI18next)
  .init({
    resources: {
      en: { common: en },
      tr: { common: tr },
      nl: { common: nl },
      es: { common: es },
      ro: { common: ro },
      uk: { common: uk },
    },
    lng: 'en',
    fallbackLng: 'en',
    defaultNS: 'common',
    interpolation: {
      escapeValue: false,
    },
  });

export default i18n;
