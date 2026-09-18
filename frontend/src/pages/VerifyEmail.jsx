import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Mail, Loader2, CheckCircle2 } from 'lucide-react';
import { api } from '../lib/api';
import ErrorMessage from '../components/ErrorMessage';
import { useTranslation } from 'react-i18next';

export default function VerifyEmail() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const email = searchParams.get('email') || '';
  const verified = searchParams.get('verified') === '1';
  const errorCode = searchParams.get('error') || '';
  const [resending, setResending] = useState(false);
  const [resendStatus, setResendStatus] = useState('');
  const [error, setError] = useState('');

  const linkError = errorCode === 'email_taken'
    ? t('auth.verifyEmail.errors.emailTaken')
    : errorCode === 'invalid'
      ? t('auth.verifyEmail.errors.invalidLink')
      : '';

  const handleResend = async () => {
    setResending(true);
    setError('');
    setResendStatus('');
    try {
      await api.post('/email/verify-resend', { email });
      setResendStatus(t('auth.verifyEmail.resendSent'));
    } catch (err) {
      setError(err.response?.data?.message || err.message || t('auth.verifyEmail.errors.resendFailed'));
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="py-16 sm:py-20 lg:py-24">
      <div className="max-w-md mx-auto">
        <div className="rounded-2xl border border-[#e8e4dd] bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] p-8 sm:p-10 text-center">
          {verified ? (
            <>
              <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-50">
                <CheckCircle2 className="w-7 h-7 text-green-600" />
              </div>
              <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a] mb-2">{t('auth.verifyEmail.verifiedTitle')}</h1>
              <p className="text-[#5c5852] mb-6 leading-relaxed">{t('auth.verifyEmail.verifiedMessage')}</p>
              <Link
                to="/login"
                className="inline-flex w-full h-12 items-center justify-center rounded-xl bg-[#1a1a1a] text-white font-semibold hover:bg-[#2d2a28] focus:ring-2 focus:ring-[#b8860b]/30 transition-colors"
              >
                {t('auth.verifyEmail.goToLogin')}
              </Link>
            </>
          ) : (
            <>
              <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[#b8860b]/10">
                {resending ? (
                  <Loader2 className="w-7 h-7 text-[#b8860b] animate-spin" />
                ) : (
                  <Mail className="w-7 h-7 text-[#b8860b]" />
                )}
              </div>
              <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a] mb-2">{t('auth.verifyEmail.title')}</h1>
              <p className="text-[#5c5852] mb-6 leading-relaxed">
                {email ? t('auth.verifyEmail.checkInbox', { email }) : t('auth.verifyEmail.openInbox')}
              </p>

              {resendStatus && <p className="text-sm text-green-600 mb-4">{resendStatus}</p>}
              {(error || linkError) && <ErrorMessage message={error || linkError} />}

              <button
                type="button"
                onClick={handleResend}
                disabled={resending}
                className="inline-flex w-full h-12 items-center justify-center gap-2 rounded-xl bg-[#1a1a1a] text-white font-semibold hover:bg-[#2d2a28] focus:ring-2 focus:ring-[#b8860b]/30 disabled:opacity-50 transition-colors"
              >
                {resending ? (
                  <>
                    <Loader2 className="w-5 h-5 animate-spin" />
                    {t('auth.verifyEmail.resending')}
                  </>
                ) : (
                  t('auth.verifyEmail.resend')
                )}
              </button>

              <p className="mt-6 text-[#5c5852] text-sm">
                {t('auth.verifyEmail.noEmail')} {' '}
                <Link to="/register" className="text-[#b8860b] font-semibold hover:text-[#996f09]">
                  {t('auth.register.title')}
                </Link>
              </p>
              <p className="mt-4 text-[#5c5852] text-sm">
                <Link to="/" className="text-[#b8860b] font-semibold hover:text-[#996f09]">
                  {t('auth.verifyEmail.goHome')}
                </Link>
              </p>
            </>
          )}
        </div>
      </div>
    </div>
  );
}