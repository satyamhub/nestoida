import { Link } from 'react-router-dom';

const PropertyCard = ({ property }) => (
  <article className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <img
      src={property.images?.[0] || 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800'}
      alt={property.title}
      className="h-44 w-full object-cover"
    />
    <div className="space-y-2 p-4">
      <h3 className="text-lg font-semibold">{property.title}</h3>
      <p className="text-sm text-slate-500">{property.sector} • {property.type}</p>
      <p className="font-semibold text-brand-600">₹{property.price}/month</p>
      <Link to={`/properties/${property._id || 'demo-id'}`} className="inline-block text-sm font-medium text-brand-600">
        View details →
      </Link>
    </div>
  </article>
);

export default PropertyCard;
