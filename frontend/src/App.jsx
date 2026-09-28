// Main App component with routing configuration
import { lazy } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './contexts/AuthContext';
import { WebsiteSettingsProvider } from './contexts/WebsiteSettingsContext';
import { LanguageProvider } from './contexts/LanguageContext';
import Layout from './components/Layout';
// Home stays statically imported for fast first paint (LCP on "/"); all other
// pages are code-split via React.lazy (Suspense boundary lives in Layout).
import Home from './pages/Home';

const HotelList = lazy(() => import('./pages/HotelList'));
const MapSearch = lazy(() => import('./pages/MapSearch'));
const HotelDetail = lazy(() => import('./pages/HotelDetail'));
const Booking = lazy(() => import('./pages/Booking'));
const Checkout = lazy(() => import('./pages/Checkout'));
const VnpayReturn = lazy(() => import('./pages/VnpayReturn'));
const Profile = lazy(() => import('./pages/Profile'));
const MyBookings = lazy(() => import('./pages/MyBookings'));
const Wishlist = lazy(() => import('./pages/Wishlist'));
const Support = lazy(() => import('./pages/Support'));
const SupportTicketNew = lazy(() => import('./pages/SupportTicketNew'));
const SupportTicketDetail = lazy(() => import('./pages/SupportTicketDetail'));
const Login = lazy(() => import('./pages/Login'));
const Register = lazy(() => import('./pages/Register'));
const VerifyEmail = lazy(() => import('./pages/VerifyEmail'));
const Tours = lazy(() => import('./pages/Tours'));
const TourProvinceDetail = lazy(() => import('./pages/TourProvinceDetail'));
const GuideDetail = lazy(() => import('./pages/GuideDetail'));
const TourDetail = lazy(() => import('./pages/TourDetail'));
const TourBookingWizard = lazy(() => import('./pages/TourBookingWizard'));
const TourCheckout = lazy(() => import('./pages/TourCheckout'));
const MyTourBookings = lazy(() => import('./pages/MyTourBookings'));
const TourBookingReview = lazy(() => import('./pages/TourBookingReview'));
const SavedTours = lazy(() => import('./pages/SavedTours'));

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 60 * 1000, retry: 1 },
  },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <WebsiteSettingsProvider>
          <LanguageProvider>
            <BrowserRouter>
              <Routes>
                <Route path="/" element={<Layout />}>
              <Route index element={<Home />} />
              <Route path="hotels" element={<HotelList />} />
              <Route path="hotels/map" element={<MapSearch />} />
              <Route path="hotels/:id" element={<HotelDetail />} />
              <Route path="book" element={<Booking />} />
              <Route path="checkout/:uuid" element={<Checkout />} />
              <Route path="vnpay-return" element={<VnpayReturn />} />
              <Route path="profile" element={<Profile />} />
              <Route path="bookings" element={<MyBookings />} />
              <Route path="wishlist" element={<Wishlist />} />
              <Route path="support" element={<Support />} />
              <Route path="support/new" element={<SupportTicketNew />} />
              <Route path="support/:id" element={<SupportTicketDetail />} />
              <Route path="tours" element={<Tours />} />
              <Route path="tours/province/:slug" element={<TourProvinceDetail />} />
              <Route path="guides/:uuid" element={<GuideDetail />} />
              <Route path="tours/:uuid" element={<TourDetail />} />
              <Route path="tours/book/:uuid" element={<TourBookingWizard />} />
              <Route path="tour-checkout/:uuid" element={<TourCheckout />} />
              <Route path="tour-bookings" element={<MyTourBookings />} />
              <Route path="tour-bookings/:uuid/review" element={<TourBookingReview />} />
              <Route path="saved-tours" element={<SavedTours />} />
              <Route path="login" element={<Login />} />
              <Route path="register" element={<Register />} />
              <Route path="verify-email" element={<VerifyEmail />} />
            </Route>
          </Routes>
            </BrowserRouter>
          </LanguageProvider>
        </WebsiteSettingsProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}
