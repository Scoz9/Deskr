import { useTranslation } from 'react-i18next';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/i18n/use-locale';
import { localeDisplayName } from '@/lib/locale-display-name';

export default function LanguageSelect() {
    const { t } = useTranslation();
    const { locale, locales, setLocale } = useLocale();

    return (
        <Select value={locale} onValueChange={(value) => void setLocale(value)}>
            <SelectTrigger
                size="sm"
                aria-label={t('language')}
                data-test="language-select"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {locales.map((code) => (
                    <SelectItem key={code} value={code}>
                        {localeDisplayName(code)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
