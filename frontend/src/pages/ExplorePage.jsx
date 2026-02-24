import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import api from '../api/client';
import FilterSidebar from '../components/FilterSidebar.jsx';
import PropertyCard from '../components/PropertyCard.jsx';

const ExplorePage = () => {
  const [searchParams] = useSearchParams();
  const [filters, setFilters] = useState({
    type: searchParams.get('type') || '',
    minPrice: '',
    maxPrice: searchParams.get('maxPrice') || '',
    gender: '',
    sector: searchParams.get('sector') || '',
    amenities: []
  });
  const [properties, setProperties] = useState([]);

  useEffect(() => {
    const params = Object.fromEntries(
      Object.entries(filters).filter(([, value]) => (Array.isArray(value) ? value.length : value !== ''))
    );
    if (params.amenities) params.amenities = params.amenities.join(',');

    api.get('/properties', { params }).then((res) => setProperties(res.data)).catch(() => setProperties([]));
  }, [filters]);

  return (
    <div className="grid gap-6 lg:grid-cols-[280px_1fr]">
      <FilterSidebar filters={filters} setFilters={setFilters} />
      <section className="grid gap-4 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
        {properties.map((property) => <PropertyCard key={property._id} property={property} />)}
      </section>
    </div>
  );
};

export default ExplorePage;
