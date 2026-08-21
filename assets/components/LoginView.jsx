import React, { useState } from 'react';

export function LoginView({ onLoginSuccess }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    fetch('/api/login_check', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        username: email,
        password: password,
      }),
    })
      .then(async (res) => {
        if (!res.ok) {
          const data = await res.json().catch(() => ({}));
          throw new Error(data.message || 'Adresse email ou mot de passe incorrect.');
        }
        return res.json();
      })
      .then((userData) => {
        onLoginSuccess(userData);
      })
      .catch((err) => {
        setError(err.message);
      })
      .finally(() => {
        setLoading(false);
      });
  };

  return (
    <div className="max-w-md w-full mx-auto my-12 bg-white rounded-3xl border border-slate-200 shadow-xl p-8 space-y-6">
      <div className="text-center space-y-2">
        <div className="w-16 h-16 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-extrabold text-2xl mx-auto shadow-lg shadow-indigo-200">
          <i className="fa-solid fa-users"></i>
        </div>
        <h2 className="text-2xl font-extrabold text-slate-800">Espace Membre React</h2>
        <p className="text-xs text-slate-500">Connectez-vous pour accéder à votre profil, carte QR code et assiduité</p>
      </div>

      {error && (
        <div className="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-semibold flex items-center space-x-2">
          <i className="fa-solid fa-circle-exclamation text-rose-500"></i>
          <span>{error}</span>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="space-y-1">
          <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">Adresse Email</label>
          <div className="relative">
            <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
              <i className="fa-solid fa-envelope text-xs"></i>
            </span>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              placeholder="votre.email@exemple.com"
              className="w-full pl-10 pr-4 py-3 rounded-2xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500 bg-slate-50 focus:bg-white transition"
            />
          </div>
        </div>

        <div className="space-y-1">
          <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">Mot de Passe</label>
          <div className="relative">
            <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
              <i className="fa-solid fa-lock text-xs"></i>
            </span>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              placeholder="••••••••"
              className="w-full pl-10 pr-4 py-3 rounded-2xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500 bg-slate-50 focus:bg-white transition"
            />
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full py-3.5 px-4 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-extrabold text-xs shadow-md shadow-indigo-200 transition flex items-center justify-center space-x-2"
        >
          {loading ? (
            <span>Connexion en cours...</span>
          ) : (
            <>
              <span>Se Connecter</span>
              <i className="fa-solid fa-arrow-right text-xs"></i>
            </>
          )}
        </button>
      </form>
    </div>
  );
}
