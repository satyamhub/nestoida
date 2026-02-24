import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import api from '../api/client';

const PropertyDetailPage = () => {
  const { id } = useParams();
  const [property, setProperty] = useState(null);

  useEffect(() => {
    api.get(`/properties/${id}`).then((res) => setProperty(res.data)).catch(() => setProperty(null));
  }, [id]);

  if (!property) return <p>Loading property details...</p>;

  return (
    <div className="space-y-6">
      <div className="grid gap-3 md:grid-cols-3">
        {(property.images?.length ? property.images : ['https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=1200']).map((img, i) => (
          <img key={`${img}-${i}`} src={img} alt={property.title} className="h-52 w-full rounded-xl object-cover" />
        ))}
      </div>
      <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
        <h1 className="text-2xl font-bold">{property.title}</h1>
        <p className="mt-2 text-brand-600 font-semibold">₹{property.price}/month</p>
        <p className="text-slate-500">{property.sector} • {property.type}</p>
        <p className="mt-4">{property.description}</p>
        <div className="mt-4 flex flex-wrap gap-2">
          {property.amenities?.map((item) => <span key={item} className="rounded-full bg-slate-100 px-3 py-1 text-sm dark:bg-slate-800">{item}</span>)}
        </div>
        <div className="mt-6 rounded-lg bg-brand-50 p-4 dark:bg-slate-800">
          <h2 className="font-semibold">Contact Owner</h2>
          <p>{property.contactNumber}</p>
        </div>
      </div>
    </div>
  );
};

export default PropertyDetailPage;
