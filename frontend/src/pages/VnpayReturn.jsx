import { useEffect } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Loader2, AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * VNPay return landing page (VNPAY_RETURN_URL).
 * Display-only: confirmation is done server-side via IPN.
 * Success/failure is determined by polling the booking status on /checkout/:uuid.
 */
export default function VnpayReturn() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const uuid = searchParams.get('uuid');
  const type = searchParams.get('type');
  const responseCode = searchParams.get('vnp_ResponseCode');
  const isTour = type === 'tour';
  const base = isTour ? `/tour-checkout/${uuid}` : `/checkout/${uuid}`;

  useEffect(() => {
    if (!uuid) return;
    if (responseCode === '00') {
      navigate(`${base}?success=1`, { replace: true });
    } else if (responseCode && responseCode !== '00') {
      navigate(`${base}?payment=failed`, { replace: true });
    }
  }, [uuid, responseCode, navigate, base]);

  if (!uuid) {
    return (
      <div className="py-12 max-w-lg mx-auto text-center">
        <AlertTriangle className="w-10 h-10 mx-auto mb-4 text-amber-600" />
        <p className="text-[#5c5852] mb-4">{t('checkout.errors.missingReference')}</p>
        <Link to={isTour ? '/tours' : '/hotels'} className="text-[#b8860b] underline hover:text-[#996f09]">
          {t('checkout.errors.searchHotels')}
        </Link>
      </div>
    );
  }

  return (
    <div className="py-12 flex items-center justify-center">
      <Loader2 className="w-8 h-8 animate-spin text-[#b8860b]" />
    </div>
  );
}
