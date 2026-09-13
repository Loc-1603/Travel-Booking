import { useLanguage } from '../../contexts/LanguageContext';

export function LanguageSwitcher() {
  const { currentLanguage, changeLanguage } = useLanguage();

  const languages = [
    { code: 'en', label: 'English', flag: '🇺🇸' },
    { code: 'vi', label: 'Tiếng Việt', flag: '🇻🇳' },
  ];

  return (
    <div className="relative">
      <button
        onClick={() => {
          const dropdown = document.getElementById('language-dropdown');
          if (dropdown) dropdown.classList.toggle('hidden');
        }}
        className="flex items-center gap-2 px-3 py-2 rounded-xl border border-[#e8e4dd] bg-white text-sm font-medium text-[#45423d] hover:bg-[#faf8f5] transition-colors focus:ring-2 focus:ring-[#b8860b]/30 focus:border-[#b8860b]"
        aria-label="Select language"
        aria-expanded="false"
        aria-haspopup="true"
      >
        <span className="text-base">{languages.find(l => l.code === currentLanguage)?.flag || '🌐'}</span>
        <span className="hidden sm:inline">{languages.find(l => l.code === currentLanguage)?.label || currentLanguage}</span>
        <svg className="w-4 h-4 text-[#7a756d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div
        id="language-dropdown"
        className="absolute right-0 top-full mt-2 w-40 rounded-xl border border-[#e8e4dd] bg-white shadow-lg hidden z-50 py-1"
        role="menu"
      >
        {languages.map((lang) => (
          <button
            key={lang.code}
            onClick={() => {
              changeLanguage(lang.code);
              const dropdown = document.getElementById('language-dropdown');
              if (dropdown) dropdown.classList.add('hidden');
            }}
            className={`w-full flex items-center gap-3 px-4 py-2 text-sm transition-colors ${
              currentLanguage === lang.code
                ? 'bg-[#f9edd1] text-[#996f09] font-medium'
                : 'text-[#45423d] hover:bg-[#faf8f5]'
            }`}
            role="menuitem"
          >
            <span className="text-base">{lang.flag}</span>
            <span>{lang.label}</span>
            {currentLanguage === lang.code && (
              <svg className="w-4 h-4 ml-auto text-[#996f09]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            )}
          </button>
        ))}
      </div>
    </div>
  );
}