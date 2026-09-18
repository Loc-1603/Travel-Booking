import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Mail, Lock, Loader2 } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import ErrorMessage from '../components/ErrorMessage';
import { useTranslation } from 'react-i18next';

export default function Login() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { login } = useAuth();
  const redirect = searchParams.get('redirect') || '/';
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [requiresVerification, setRequiresVerification] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setRequiresVerification(false);
    setLoading(true);
    try {
      await login(email, password);
      navigate(redirect.startsWith('/') ? redirect : '/');
    } catch (err) {
      setRequiresVerification(!!err.response?.data?.requires_verification);
      setError(err.response?.data?.message || err.message || t('auth.errors.loginFailed'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="py-16 sm:py-20 lg:py-24">
      <div className="max-w-md mx-auto">
        <div className="rounded-2xl border border-[#e8e4dd] bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] p-8 sm:p-10">
          <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a] mb-2">{t('auth.login.title')}</h1>
          <p className="text-[#5c5852] mb-6 leading-relaxed">{t('auth.login.subtitle')}</p>
          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-[#45423d] mb-1.5">{t('auth.fields.email')}</label>
              <div className="relative">
                <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-[#7a756d] pointer-events-none" />
                <input
                  id="email"
                  type="email"
                  autoComplete="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="w-full h-12 pl-11 pr-4 rounded-xl border border-[#e8e4dd] text-[#1a1a1a] placeholder-[#7a756d] focus:ring-2 focus:ring-[#b8860b]/30 focus:border-[#b8860b] bg-white"
                  placeholder={t('auth.fields.emailPlaceholder')}
                />
              </div>
            </div>
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-[#45423d] mb-1.5">{t('auth.fields.password')}</label>
              <div className="relative">
                <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-[#7a756d] pointer-events-none" />
                <input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full h-12 pl-11 pr-4 rounded-xl border border-[#e8e4dd] text-[#1a1a1a] placeholder-[#7a756d] focus:ring-2 focus:ring-[#b8860b]/30 focus:border-[#b8860b] bg-white"
                  placeholder={t('auth.fields.passwordPlaceholder')}
                />
              </div>
            </div>
            {error && <ErrorMessage message={error} />}
            {requiresVerification && (
              <Link
                to={`/verify-email?email=${encodeURIComponent(email)}`}
                className="block text-center text-sm text-[#b8860b] font-semibold hover:text-[#996f09]"
              >
                {t('auth.verifyEmail.resend')}
              </Link>
            )}
            <button
              type="submit"
              disabled={loading}
              className="w-full h-12 px-6 rounded-xl bg-[#1a1a1a] text-white font-semibold hover:bg-[#2d2a28] focus:ring-2 focus:ring-[#b8860b]/30 disabled:opacity-50 flex items-center justify-center gap-2 transition-colors"
            >
              {loading ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  {t('auth.login.signingIn')}
                </>
              ) : (
                t('auth.login.submit')
              )}
            </button>
          </form>
          <p className="mt-6 text-[#5c5852] text-sm text-center">
            {t('auth.login.noAccount')} {' '}
            <Link to="/register" className="text-[#b8860b] font-semibold hover:text-[#996f09]">
              {t('auth.login.signUp')}
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}