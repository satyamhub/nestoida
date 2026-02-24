import Navbar from '../components/Navbar.jsx';
import Footer from '../components/Footer.jsx';

const MainLayout = ({ children }) => (
  <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
    <Navbar />
    <main className="mx-auto max-w-7xl px-4 py-8">{children}</main>
    <Footer />
  </div>
);

export default MainLayout;
