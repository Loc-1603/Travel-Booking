import { useState, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ChevronDown, User, Headphones, LogOut, CalendarCheck, Home } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/useAuth';
import { useWebsiteSettings } from '../contexts/useWebsiteSettings';
import UserAvatar from './UserAvatar';
import { LanguageSwitcher } from './ui/LanguageSwitcher';

export default function Header() {
  const { t } = useTranslation();
  const { user, logout } = useAuth();
  const { settings } = useWebsiteSettings();
  const navigate = useNavigate();
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const dropdownRef = useRef(null);

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setDropdownOpen(false);
      }
    };
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, []);

  const handleLogout = async () => {
    setDropdownOpen(false);
    await logout();
    navigate('/');
  };

  return (
    <header className="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-[#e8e4dd] shadow-sm">
      <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <Link
          to="/"
          aria-label={settings.site_name ? `${settings.site_name} — home` : 'Home'}
          className="flex items-center group transition-opacity hover:opacity-90"
        >
          {settings.site_logo ? (
            <img src={settings.site_logo} alt="" className="h-9 w-auto max-h-10 object-contain" />
          ) : (
            <div className="w-9 h-9 rounded-lg bg-[#1a1a1a] flex items-center justify-center" aria-hidden>
              <Home className="w-5 h-5 text-[#f9edd1]" />
            </div>
          )}
        </Link>

        <nav className="flex items-center gap-1" aria-label="Main navigation">
          <Link
            to="/hotels"
            className="inline-flex items-center px-4 py-2.5 rounded-lg text-[#5c5852] hover:text-[#1a1a1a] hover:bg-[#f5f2ed] font-medium text-sm transition-colors"
          >
            {t('header.hotels')}
          </Link>
          <Link
            to="/hotels/map"
            className="inline-flex items-center px-4 py-2.5 rounded-lg text-[#5c5852] hover:text-[#1a1a1a] hover:bg-[#f5f2ed] font-medium text-sm transition-colors hidden md:block"
          >
            {t('header.mapSearch')}
          </Link>
          <Link
            to="/tours"
            className="inline-flex items-center px-4 py-2.5 rounded-lg text-[#5c5852] hover:text-[#1a1a1a] hover:bg-[#f5f2ed] font-medium text-sm transition-colors"
          >
            {t('header.tours')}
          </Link>
          {user ? (
            <>
              <Link
                to="/wishlist"
                className="inline-flex items-center px-4 py-2.5 rounded-lg text-[#5c5852] hover:text-[#1a1a1a] hover:bg-[#f5f2ed] font-medium text-sm transition-colors hidden md:block"
              >
                {t('header.wishlist')}
              </Link>
              <LanguageSwitcher />
              <div className="relative" ref={dropdownRef}>
                <button
                  type="button"
                  onClick={() => setDropdownOpen((o) => !o)}
                  className="flex items-center gap-1.5 px-2 py-2 rounded-lg text-[#5c5852] hover:text-[#1a1a1a] hover:bg-[#f5f2ed] font-medium text-sm transition-colors"
                  aria-expanded={dropdownOpen}
                  aria-haspopup="true"
                  aria-label={`${t('header.welcome')} ${user.name}`}
                >
                  <UserAvatar user={user} size={36} />
                  <ChevronDown className={`w-4 h-4 text-[#7a756d] shrink-0 transition-transform ${dropdownOpen ? 'rotate-180' : ''}`} />
                </button>
                {dropdownOpen && (
                  <div className="absolute right-0 mt-1.5 w-52 py-1.5 bg-white rounded-xl shadow-lg border border-[#e8e4dd]">
                    <Link
                      to="/bookings"
                      onClick={() => setDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-[#45423d] hover:bg-[#faf8f5] transition-colors"
                    >
                      <CalendarCheck className="w-4 h-4 text-[#b8860b]" />
                      {t('header.myBookings')}
                    </Link>
                    <Link
                      to="/tour-bookings"
                      onClick={() => setDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-[#45423d] hover:bg-[#faf8f5] transition-colors"
                    >
                      <CalendarCheck className="w-4 h-4 text-[#b8860b]" />
                      {t('header.myTourBookings')}
                    </Link>
                    <Link
                      to="/saved-tours"
                      onClick={() => setDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-[#45423d] hover:bg-[#faf8f5] transition-colors"
                    >
                      <CalendarCheck className="w-4 h-4 text-[#b8860b]" />
                      {t('header.savedTours')}
                    </Link>
                    <Link
                      to="/support"
                      onClick={() => setDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-[#45423d] hover:bg-[#faf8f5] transition-colors"
                    >
                      <Headphones className="w-4 h-4 text-[#b8860b]" />
                      {t('header.support')}
                    </Link>
                    <Link
                      to="/profile"
                      onClick={() => setDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-[#45423d] hover:bg-[#faf8f5] transition-colors"
                    >
                      <User className="w-4 h-4 text-[#b8860b]" />
                      {t('header.profile')}
                    </Link>
                    <hr className="my-1.5 border-[#e8e4dd]" />
                    <button
                      type="button"
                      onClick={handleLogout}
                      className="flex items-center gap-2.5 w-full px-4 py-2.5 text-sm text-[#dc2626] hover:bg-red-50 transition-colors"
                    >
                      <LogOut className="w-4 h-4" />
                      {t('header.logout')}
                    </button>
                  </div>
                )}
              </div>
            </>
          ) : (
            <>
              <Link
                to="/support"
                className="inline-flex items-center px-4 py-2.5 rounded-lg text-[#5c5852] hover:text-[#1a1a1a] hover:bg-[#f5f2ed] font-medium text-sm transition-colors hidden md:block"
              >
                {t('header.support')}
              </Link>
              <LanguageSwitcher />
              <Link
                to="/login"
                className="inline-flex items-center px-4 py-2.5 rounded-lg text-[#5c5852] hover:text-[#1a1a1a] hover:bg-[#f5f2ed] font-medium text-sm transition-colors hidden md:block"
              >
                {t('header.login')}
              </Link>
              <Link
                to="/register"
                className="px-5 py-2.5 rounded-lg bg-[#1a1a1a] text-white font-semibold text-sm hover:bg-[#2d2a28] transition-colors"
              >
                {t('header.register')}
              </Link>
            </>
          )}
        </nav>
      </div>
    </header>
  );
}