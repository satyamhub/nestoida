import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../api/client';
import { useAuth } from '../context/AuthContext.jsx';

const SignupPage = () => {
  const [form, setForm] = useState({ name: '', email: '', password: '', role: 'tenant' });
  const [error, setError] = useState('');
  const navigate = useNavigate();
  const { login } = useAuth();

  const onSubmit = async (e) => {
    e.preventDefault();
    try {
      const { data } = await api.post('/auth/signup', form);
      login(data);
      navigate('/');
    } catch {
      setError('Could not create account');
    }
  };

  return (
    <form onSubmit={onSubmit} className="mx-auto max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
      <h1 className="text-2xl font-bold">Signup</h1>
      <input className="w-full rounded border p-2 dark:bg-slate-800" placeholder="Name" onChange={(e) => setForm((s) => ({ ...s, name: e.target.value }))} />
      <input className="w-full rounded border p-2 dark:bg-slate-800" placeholder="Email" onChange={(e) => setForm((s) => ({ ...s, email: e.target.value }))} />
      <input type="password" className="w-full rounded border p-2 dark:bg-slate-800" placeholder="Password" onChange={(e) => setForm((s) => ({ ...s, password: e.target.value }))} />
      <select className="w-full rounded border p-2 dark:bg-slate-800" onChange={(e) => setForm((s) => ({ ...s, role: e.target.value }))}><option value="tenant">Tenant</option><option value="owner">Owner</option></select>
      <button className="w-full rounded bg-brand-600 py-2 text-white">Create Account</button>
      {error && <p className="text-sm text-red-500">{error}</p>}
      <p className="text-sm">Already have an account? <Link to="/login" className="text-brand-600">Login</Link></p>
    </form>
  );
};

export default SignupPage;
