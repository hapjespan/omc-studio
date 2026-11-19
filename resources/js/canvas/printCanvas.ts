import '../../css/print.css';
import { t } from '../i18n';

export const printWarning = (beyond: number): string => (beyond > 0 ? t('print.warning', { count: beyond }) : '');

export function printCanvas(): void {
    window.print();
}
