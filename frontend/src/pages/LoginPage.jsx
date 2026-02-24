import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../api/client';
import { useAuth } from '../context/AuthContext.jsx';

const LoginPage = () => {
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState('');
  const navigate = useNavigate();
  const { login } = useAuth();

  const onSubmit = async (e) => {
    e.preventDefault();
    try {
      const { data } = await api.post('/auth/login', form);
      login(data);
      navigate('/');
    } catch {
      setError('Invalid email or password');
    }
  };

  return (
    <form onSubmit={onSubmit} className="mx-auto max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
      <h1 className="text-2xl font-bold">Login</h1>
      <input className="w-full rounded border p-2 dark:bg-slate-800" placeholder="Email" onChange={(e) => setForm((s) => ({ ...s, email: e.target.value }))} />
      <input type="password" className="w-full rounded border p-2 dark:bg-slate-800" placeholder="Password" onChange={(e) => setForm((s) => ({ ...s, password: e.target.value }))} />
      <button className="w-full rounded bg-brand-600 py-2 text-white">Login</button>
      {error && <p className="text-sm text-red-500">{error}</p>}
      <p className="text-sm">Don't have an account? <Link to="/signup" className="text-brand-600">Signup</Link></p>
    </form>
  );
};

export default LoginPage;
