import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../api/client';
import PropertyCard from '../components/PropertyCard.jsx';

const HomePage = () => {
  const [search, setSearch] = useState({ sector: '', type: '', maxPrice: '' });
  const [featured, setFeatured] = useState([]);
  const navigate = useNavigate();

  useEffect(() => {
    api.get('/properties/featured').then((res) => setFeatured(res.data)).catch(() => setFeatured([]));
  }, []);

  const onSearch = (e) => {
    e.preventDefault();
    const query = new URLSearchParams(search).toString();
    navigate(`/explore?${query}`);
  };

  return (
    <div className="space-y-12">
      <section className="rounded-2xl bg-gradient-to-r from-brand-600 to-indigo-600 p-8 text-white">
        <h1 className="text-3xl font-bold">Find your next home in Noida</h1>
        <p className="mt-2 text-white/90">Hostels, PGs, and flats across top Noida sectors.</p>
        <form onSubmit={onSearch} className="mt-6 grid gap-3 md:grid-cols-4">
          <input placeholder="Sector" className="rounded-lg p-3 text-slate-800" onChange={(e) => setSearch((s) => ({ ...s, sector: e.target.value }))} />
          <select className="rounded-lg p-3 text-slate-800" onChange={(e) => setSearch((s) => ({ ...s, type: e.target.value }))}>
            <option value="">Type</option><option>Hostel</option><option>PG</option><option>Flat</option>
          </select>
          <input type="number" placeholder="Max Price" className="rounded-lg p-3 text-slate-800" onChange={(e) => setSearch((s) => ({ ...s, maxPrice: e.target.value }))} />
          <button className="rounded-lg bg-slate-900 px-4 py-3 font-medium">Search</button>
        </form>
      </section>

      <section>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-2xl font-semibold">Featured Listings</h2>
          <Link to="/explore" className="text-brand-600">View all</Link>
        </div>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {featured.map((item) => <PropertyCard key={item._id} property={item} />)}
        </div>
      </section>
    </div>
  );
};

export default HomePage;
