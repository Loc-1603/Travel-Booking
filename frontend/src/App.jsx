// Main App component with routing configuration
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './contexts/AuthContext';
import { WebsiteSettingsProvider } from './contexts/WebsiteSettingsContext';
import { LanguageProvider } from './contexts/LanguageContext';
import Layout from './components/Layout';
import Home from './pages/Home';
import HotelList from './pages/HotelList';
import MapSearch from './pages/MapSearch';
import HotelDetail from './pages/HotelDetail';
import Booking from './pages/Booking';
import Checkout from './pages/Checkout';
import VnpayReturn from './pages/VnpayReturn';
import Profile from './pages/Profile';
import MyBookings from './pages/MyBookings';
import Wishlist from './pages/Wishlist';
import Support from './pages/Support';
import SupportTicketNew from './pages/SupportTicketNew';
import SupportTicketDetail from './pages/SupportTicketDetail';
import Login from './pages/Login';
import Register from './pages/Register';
import VerifyEmail from './pages/VerifyEmail';
import Tours from './pages/Tours';
import TourProvinceDetail from './pages/TourProvinceDetail';
import GuideDetail from './pages/GuideDetail';
import TourDetail from './pages/TourDetail';
import TourBookingWizard from './pages/TourBookingWizard';
import TourCheckout from './pages/TourCheckout';
import MyTourBookings from './pages/MyTourBookings';
import TourBookingReview from './pages/TourBookingReview';
import SavedTours from './pages/SavedTours';

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
