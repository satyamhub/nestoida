import { useState } from 'react';
import api from '../api/client';
import { amenitiesList, propertyTypes, sectors, genderPreferences } from '../data/constants';

const PostPropertyPage = () => {
  const [form, setForm] = useState({
    title: '',
    type: 'PG',
    sector: sectors[0],
    price: '',
    gender: 'Any',
    amenities: [],
    description: '',
    contactNumber: '',
    images: []
  });
  const [status, setStatus] = useState('');

  const toggleAmenity = (amenity) => {
    setForm((prev) => ({
      ...prev,
      amenities: prev.amenities.includes(amenity)
        ? prev.amenities.filter((a) => a !== amenity)
        : [...prev.amenities, amenity]
    }));
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    if (!form.title || !form.price || !form.description || !form.contactNumber) {
      setStatus('Please fill all required fields.');
      return;
    }

    const payload = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      if (key === 'images') {
        Array.from(value).forEach((file) => payload.append('images', file));
      } else if (key === 'amenities') {
        value.forEach((item) => payload.append('amenities', item));
      } else {
        payload.append(key, value);
      }
    });

    try {
      await api.post('/properties', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
      setStatus('Property posted successfully!');
    } catch {
      setStatus('Failed to post property. Please login as owner and try again.');
    }
  };

  return (
    <form onSubmit={onSubmit} className="mx-auto max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
      <h1 className="text-2xl font-bold">Post a Property</h1>
      <input className="w-full rounded border p-2 dark:bg-slate-800" placeholder="Title" onChange={(e) => setForm((s) => ({ ...s, title: e.target.value }))} />
      <div className="grid gap-3 md:grid-cols-2">
        <select className="rounded border p-2 dark:bg-slate-800" onChange={(e) => setForm((s) => ({ ...s, type: e.target.value }))}>{propertyTypes.map((t) => <option key={t}>{t}</option>)}</select>
        <select className="rounded border p-2 dark:bg-slate-800" onChange={(e) => setForm((s) => ({ ...s, sector: e.target.value }))}>{sectors.map((s) => <option key={s}>{s}</option>)}</select>
        <input type="number" className="rounded border p-2 dark:bg-slate-800" placeholder="Price" onChange={(e) => setForm((s) => ({ ...s, price: e.target.value }))} />
        <select className="rounded border p-2 dark:bg-slate-800" onChange={(e) => setForm((s) => ({ ...s, gender: e.target.value }))}>{genderPreferences.map((g) => <option key={g}>{g}</option>)}</select>
      </div>
      <div className="grid grid-cols-2 gap-2">{amenitiesList.map((item) => <label key={item}><input type="checkbox" onChange={() => toggleAmenity(item)} /> {item}</label>)}</div>
      <textarea className="w-full rounded border p-2 dark:bg-slate-800" rows="4" placeholder="Description" onChange={(e) => setForm((s) => ({ ...s, description: e.target.value }))} />
      <input className="w-full rounded border p-2 dark:bg-slate-800" placeholder="Contact Number" onChange={(e) => setForm((s) => ({ ...s, contactNumber: e.target.value }))} />
      <input type="file" multiple className="w-full rounded border p-2 dark:bg-slate-800" onChange={(e) => setForm((s) => ({ ...s, images: e.target.files }))} />
      <button className="rounded bg-brand-600 px-4 py-2 font-medium text-white">Submit</button>
      {status && <p className="text-sm text-slate-500">{status}</p>}
    </form>
  );
};

export default PostPropertyPage;
