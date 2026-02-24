import { amenitiesList, genderPreferences, propertyTypes, sectors } from '../data/constants';

const FilterSidebar = ({ filters, setFilters }) => {
  const onChange = (key, value) => setFilters((prev) => ({ ...prev, [key]: value }));

  const toggleAmenity = (item) => {
    const current = filters.amenities;
    onChange(
      'amenities',
      current.includes(item) ? current.filter((x) => x !== item) : [...current, item]
    );
  };

  return (
    <aside className="space-y-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
      <select className="w-full rounded border p-2 dark:bg-slate-800" onChange={(e) => onChange('type', e.target.value)}>
        <option value="">All Types</option>
        {propertyTypes.map((type) => <option key={type}>{type}</option>)}
      </select>
      <input type="number" placeholder="Min Price" className="w-full rounded border p-2 dark:bg-slate-800" onChange={(e) => onChange('minPrice', e.target.value)} />
      <input type="number" placeholder="Max Price" className="w-full rounded border p-2 dark:bg-slate-800" onChange={(e) => onChange('maxPrice', e.target.value)} />
      <select className="w-full rounded border p-2 dark:bg-slate-800" onChange={(e) => onChange('gender', e.target.value)}>
        <option value="">Gender Preference</option>
        {genderPreferences.map((gender) => <option key={gender}>{gender}</option>)}
      </select>
      <select className="w-full rounded border p-2 dark:bg-slate-800" onChange={(e) => onChange('sector', e.target.value)}>
        <option value="">All Sectors</option>
        {sectors.map((sector) => <option key={sector}>{sector}</option>)}
      </select>
      <div>
        <p className="mb-2 text-sm font-semibold">Amenities</p>
        <div className="grid grid-cols-2 gap-2 text-sm">
          {amenitiesList.map((item) => (
            <label key={item} className="flex items-center gap-2">
              <input type="checkbox" onChange={() => toggleAmenity(item)} checked={filters.amenities.includes(item)} />
              {item}
            </label>
          ))}
        </div>
      </div>
    </aside>
  );
};

export default FilterSidebar;
