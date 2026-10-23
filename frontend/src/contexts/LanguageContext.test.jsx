import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { LanguageProvider } from './LanguageContext';
import { useLanguage } from './useLanguage';

const mocks = vi.hoisted(() => ({
  changeLanguage: vi.fn(),
  language: { current: 'vi' },
  settings: { locale: 'en' },
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    i18n: {
      get language() {
        return mocks.language.current;
      },
      changeLanguage: mocks.changeLanguage,
    },
  }),
}));

vi.mock('./useWebsiteSettings', () => ({
  useWebsiteSettings: () => ({ settings: mocks.settings, isLoading: false }),
}));

vi.mock('../i18n', () => ({
  isAdminRoute: () => false,
}));

function Harness() {
  const { changeLanguage, currentLanguage } = useLanguage();

  return (
    <button type="button" onClick={() => changeLanguage('vi')}>
      {currentLanguage}
    </button>
  );
}

describe('LanguageProvider', () => {
  beforeEach(() => {
    localStorage.clear();
    mocks.changeLanguage.mockClear();
  });

  it('applies the backend locale when the visitor has not chosen a language', async () => {
    render(
      <LanguageProvider>
        <Harness />
      </LanguageProvider>,
    );

    await waitFor(() => expect(mocks.changeLanguage).toHaveBeenCalledWith('en'));
  });

  it('keeps the visitor choice after a full page load such as an email verification link', async () => {
    localStorage.setItem('i18nextLng', 'vi');
    localStorage.setItem('i18nextLngChosen', '1');

    render(
      <LanguageProvider>
        <Harness />
      </LanguageProvider>,
    );

    await waitFor(() => expect(screen.getByRole('button')).toHaveTextContent('vi'));
    expect(mocks.changeLanguage).not.toHaveBeenCalled();
  });

  it('remembers a newly selected language', async () => {
    const user = userEvent.setup();

    render(
      <LanguageProvider>
        <Harness />
      </LanguageProvider>,
    );

    await waitFor(() => expect(mocks.changeLanguage).toHaveBeenCalledWith('en'));
    mocks.changeLanguage.mockClear();

    await user.click(screen.getByRole('button'));

    expect(mocks.changeLanguage).toHaveBeenCalledWith('vi');
    expect(localStorage.getItem('i18nextLng')).toBe('vi');
    expect(localStorage.getItem('i18nextLngChosen')).toBe('1');
  });
});
