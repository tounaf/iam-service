import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { ProfileView } from './components/ProfileView';
import { AffiliationsView } from './components/AffiliationsView';
import { EventsView } from './components/EventsView';
import { FinancesView } from './components/FinancesView';
import { LoginView } from './components/LoginView';

function App() {
  const [activeTab, setActiveTab] = useState('profile');
  const [currentUser, setCurrentUser] = useState(null);
  const [member, setMember] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchAuthenticatedMember = () => {
    fetch('/api/me')
      .then((r) => (r.ok ? r.json() : null))
      .then((userData) => {
        if (userData && userData.id) {
          setCurrentUser(userData);
        } else {
          setCurrentUser(null);
        }
      })
      .catch((err) => console.error('Error checking authentication state:', err))
      .finally(() => setLoading(false));
  };

  // Check if session is already authenticated on mount
  useEffect(() => {
    fetchAuthenticatedMember();
  }, []);

  // Fetch full details of authenticated member
  useEffect(() => {
    if (!currentUser || !currentUser.id) {
      setMember(null);
      return;
    }

    fetch(`/api/membres/${currentUser.id}`)
      .then((r) => r.json())
      .then((data) => setMember(data))
      .catch((err) => console.error('Error fetching member detail:', err));
  }, [currentUser]);

  const handleLogout = () => {
    fetch('/logout')
      .finally(() => {
        setCurrentUser(null);
        setMember(null);
      });
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="text-center space-y-3">
          <div className="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
          <p className="text-xs font-bold text-slate-500 uppercase tracking-wider">Chargement de l'Espace Membre React...</p>
        </div>
      </div>
    );
  }

  // Render Login view if user is not authenticated
  if (!currentUser) {
    return (
      <div className="min-h-screen bg-slate-50 flex flex-col justify-between p-4">
        <header className="max-w-6xl w-full mx-auto py-4 flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-extrabold text-lg shadow-md">
              <i className="fa-solid fa-church"></i>
            </div>
            <span className="font-extrabold text-slate-800 text-base">Portail Membres Paroisse</span>
          </div>
          <a href="/login" className="text-xs font-bold text-indigo-600 hover:underline">Accès Backoffice Admin &rarr;</a>
        </header>

        <LoginView onLoginSuccess={(userData) => setCurrentUser(userData)} />

        <footer className="text-center text-xs text-slate-400 py-4">
          Espace Membre React • Authentification Sécurisée
        </footer>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      {/* Top Navbar */}
      <header className="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-extrabold text-lg shadow-md shadow-indigo-200">
              <i className="fa-solid fa-users text-sm"></i>
            </div>
            <div>
              <span className="font-extrabold text-slate-800 text-base leading-tight block">PORTAIL MEMBRES</span>
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Session Connectée</span>
            </div>
          </div>

          {/* Connected User Profile Pill & Logout */}
          <div className="flex items-center space-x-3">
            <div className="flex items-center space-x-2 bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-2xl">
              <div className="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-[10px]">
                {currentUser.prenom ? currentUser.prenom.charAt(0) : 'M'}
              </div>
              <span className="text-xs font-bold text-slate-800">
                {currentUser.prenom} {currentUser.nom}
              </span>
            </div>

            <button
              onClick={handleLogout}
              className="px-3 py-1.5 rounded-2xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs border border-rose-200 transition flex items-center space-x-1"
            >
              <i className="fa-solid fa-right-from-bracket text-xs"></i>
              <span className="hidden sm:inline">Déconnexion</span>
            </button>
          </div>
        </div>

        {/* Navigation Tabs Bar */}
        <div className="max-w-6xl mx-auto px-4 sm:px-6 flex space-x-2 border-t border-slate-100 overflow-x-auto">
          <button
            onClick={() => setActiveTab('profile')}
            className={`py-3 px-4 text-xs font-bold flex items-center border-b-2 whitespace-nowrap transition ${
              activeTab === 'profile'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <i className="fa-solid fa-user-circle mr-2"></i> Mon Profil & Carte
          </button>

          <button
            onClick={() => setActiveTab('affiliations')}
            className={`py-3 px-4 text-xs font-bold flex items-center border-b-2 whitespace-nowrap transition ${
              activeTab === 'affiliations'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <i className="fa-solid fa-church mr-2"></i> Paroisse & Associations
          </button>

          <button
            onClick={() => setActiveTab('events')}
            className={`py-3 px-4 text-xs font-bold flex items-center border-b-2 whitespace-nowrap transition ${
              activeTab === 'events'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <i className="fa-solid fa-calendar-check mr-2"></i> Mes Événements & Assiduité
          </button>

          <button
            onClick={() => setActiveTab('finances')}
            className={`py-3 px-4 text-xs font-bold flex items-center border-b-2 whitespace-nowrap transition ${
              activeTab === 'finances'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <i className="fa-solid fa-hand-holding-dollar mr-2"></i> Cotisations & Dons
          </button>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-6 space-y-6">
        {activeTab === 'profile' && <ProfileView member={member} onRefresh={fetchAuthenticatedMember} />}
        {activeTab === 'affiliations' && <AffiliationsView member={member} />}
        {activeTab === 'events' && <EventsView memberId={currentUser.id} member={member} />}
        {activeTab === 'finances' && <FinancesView memberId={currentUser.id} member={member} />}
      </main>

      {/* Footer */}
      <footer className="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400">
        Portail Membres React • Connecté au serveur Symfony API Platform & CRBAC
      </footer>
    </div>
  );
}

const container = document.getElementById('react-member-app');
if (container) {
  const root = createRoot(container);
  root.render(<App />);
}
