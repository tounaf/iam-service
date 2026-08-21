import React, { useState, useEffect } from 'react';

export function EventsView({ memberId }) {
  const [events, setEvents] = useState([]);
  const [presences, setPresences] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all'); // all, attended, missed

  useEffect(() => {
    if (!memberId) return;

    setLoading(true);
    Promise.all([
      fetch(`/api/evenements`).then((r) => (r.ok ? r.json() : { 'hydra:member': [] })),
      fetch(`/api/presences?membre=${memberId}`).then((r) => (r.ok ? r.json() : { 'hydra:member': [] })),
      fetch(`/api/membres/${memberId}/participation-stats`).then((r) => (r.ok ? r.json() : null)),
    ])
      .then(([eventsData, presencesData, statsData]) => {
        const eventList = eventsData['hydra:member'] || eventsData || [];
        const presenceList = presencesData['hydra:member'] || presencesData || [];
        setEvents(eventList);
        setPresences(presenceList);
        setStats(statsData);
      })
      .catch((err) => console.error('Error fetching event presence data:', err))
      .finally(() => setLoading(false));
  }, [memberId]);

  if (loading) {
    return (
      <div className="p-8 text-center text-slate-400">
        <i className="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
        <p className="text-xs font-semibold">Chargement des événements et assiduité...</p>
      </div>
    );
  }

  // Helper check if member was present for an event
  const isPresentForEvent = (event) => {
    return presences.some((p) => p.activityName && p.activityName.toLowerCase() === event.nom.toLowerCase());
  };

  const filteredEvents = events.filter((event) => {
    const present = isPresentForEvent(event);
    if (filter === 'attended') return present;
    if (filter === 'missed') return !present;
    return true;
  });

  return (
    <div className="space-y-6">
      {/* Participation Overview Bar */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
        <div>
          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Assiduité Annuelle</span>
          <h2 className="text-3xl font-extrabold text-emerald-600">
            {stats ? `${stats.tauxParticipation}%` : '100%'}
          </h2>
          <p className="text-xs text-slate-500 mt-1">Taux de présence globale calculé sur le nombre de séances</p>
        </div>

        <div className="col-span-2 space-y-2">
          <div className="flex justify-between text-xs font-bold text-slate-700">
            <span>Présences scannées : {stats ? stats.presencesScannees : presences.length}</span>
            <span>Événements au programme : {stats ? stats.totalEvenements : events.length}</span>
          </div>
          <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
            <div
              className="bg-emerald-500 h-3 rounded-full transition-all duration-500"
              style={{ width: `${stats ? stats.tauxParticipation : 100}%` }}
            ></div>
          </div>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
          <i className="fa-solid fa-calendar-check text-indigo-500 mr-2"></i> Historique des Événements & Pointages
        </h3>

        <div className="flex bg-slate-200/60 p-1 rounded-2xl text-xs font-bold space-x-1">
          <button
            onClick={() => setFilter('all')}
            className={`px-4 py-1.5 rounded-xl transition ${filter === 'all' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
          >
            Tous ({events.length})
          </button>
          <button
            onClick={() => setFilter('attended')}
            className={`px-4 py-1.5 rounded-xl transition ${filter === 'attended' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
          >
            Présents ({events.filter((e) => isPresentForEvent(e)).length})
          </button>
          <button
            onClick={() => setFilter('missed')}
            className={`px-4 py-1.5 rounded-xl transition ${filter === 'missed' ? 'bg-white text-rose-600 shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
          >
            Absents ({events.filter((e) => !isPresentForEvent(e)).length})
          </button>
        </div>
      </div>

      {/* Events List Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {filteredEvents.map((event, idx) => {
          const present = isPresentForEvent(event);
          return (
            <div
              key={idx}
              className={`p-5 rounded-3xl border transition shadow-sm flex flex-col justify-between space-y-4 ${
                present ? 'bg-white border-slate-200 hover:border-emerald-300' : 'bg-slate-50 border-slate-200 opacity-80'
              }`}
            >
              <div className="flex items-start justify-between">
                <div>
                  <div className="flex items-center space-x-2">
                    <span
                      className={`px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase ${
                        present ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'
                      }`}
                    >
                      {present ? 'Présence validée' : 'Non pointé'}
                    </span>
                    {event.typeEvenement && (
                      <span className="text-[10px] font-bold text-slate-400 uppercase">
                        • {event.typeEvenement.nom}
                      </span>
                    )}
                  </div>
                  <h4 className="text-base font-extrabold text-slate-800 mt-2">{event.nom}</h4>
                  <p className="text-xs text-slate-500 mt-1 line-clamp-2">{event.description || 'Aucune description'}</p>
                </div>

                <div
                  className={`w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-sm shrink-0 ${
                    present ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-400'
                  }`}
                >
                  <i className={`fa-solid ${present ? 'fa-check' : 'fa-xmark'}`}></i>
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                <span>
                  <i className="fa-solid fa-location-dot mr-1 text-indigo-500"></i> {event.lieu || 'Lieu N/A'}
                </span>
                <span>
                  <i className="fa-solid fa-clock mr-1 text-indigo-500"></i>
                  {event.dateDebut ? new Date(event.dateDebut).toLocaleDateString('fr-FR') : 'Date N/A'}
                </span>
              </div>
            </div>
          );
        })}

        {filteredEvents.length === 0 && (
          <div className="col-span-full bg-white p-8 rounded-3xl border border-slate-200 text-center text-slate-400 text-xs">
            Aucun événement ne correspond à ce filtre.
          </div>
        )}
      </div>
    </div>
  );
}
