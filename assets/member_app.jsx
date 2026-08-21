import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { ProfileView } from './components/ProfileView';
import { AffiliationsView } from './components/AffiliationsView';
import { EventsView } from './components/EventsView';

function App() {
  const [activeTab, setActiveTab] = useState('profile');
  const [members, setMembers] = useState([]);
  const [selectedMemberId, setSelectedMemberId] = useState(null);
  const [member, setMember] = useState(null);
  const [loading, setLoading] = useState(true);

  // Fetch list of members to allow selecting/viewing profile
  useEffect(() => {
    fetch('/api/membres')
      .then((r) => r.json())
      .then((data) => {
        let list = [];
        if (Array.isArray(data)) {
          list = data;
        } else if (data && Array.isArray(data['hydra:member'])) {
          list = data['hydra:member'];
        } else if (data && Array.isArray(data.member)) {
          list = data.member;
        }
        setMembers(list);
        if (list.length > 0) {
          setSelectedMemberId(list[0].id);
        }
      })
      .catch((err) => console.error('Error fetching members list:', err))
      .finally(() => setLoading(false));
  }, []);

  // Fetch selected member full details
  useEffect(() => {
    if (!selectedMemberId) return;

    fetch(`/api/membres/${selectedMemberId}`)
      .then((r) => r.json())
      .then((data) => setMember(data))
      .catch((err) => console.error('Error fetching member detail:', err));
  }, [selectedMemberId]);

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
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Application React</span>
            </div>
          </div>

          {/* Member Switcher Dropdown */}
          <div className="flex items-center space-x-3">
            <span className="text-xs text-slate-400 font-semibold hidden sm:inline">Connecté en tant que:</span>
            <select
              value={selectedMemberId || ''}
              onChange={(e) => setSelectedMemberId(Number(e.target.value))}
              className="bg-slate-100 border border-slate-200 text-slate-800 text-xs font-bold rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500"
            >
              {(Array.isArray(members) ? members : []).map((m) => (
                <option key={m.id} value={m.id}>
                  {m.prenom} {m.nom} (#{m.id})
                </option>
              ))}
            </select>
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
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-6 space-y-6">
        {activeTab === 'profile' && <ProfileView member={member} />}
        {activeTab === 'affiliations' && <AffiliationsView member={member} />}
        {activeTab === 'events' && <EventsView memberId={selectedMemberId} />}
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
