import React, { useState, useEffect } from 'react';

export function EventsView({ memberId, member }) {
  const [events, setEvents] = useState([]);
  const [presences, setPresences] = useState([]);
  const [stats, setStats] = useState(null);
  const [typesEvenement, setTypesEvenement] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all'); // all, attended, missed

  // Event Creation Modal State
  const [showCreateModal, setShowCardModal] = useState(false);
  const [newEvent, setNewEvent] = useState({
    nom: '',
    description: '',
    lieu: '',
    associationId: '',
    groupeId: '',
    typeEvenementId: '',
    dateDebut: '',
  });
  const [creating, setSaving] = useState(false);

  // QR Code Scanner Modal State
  const [scanModalEvent, setScanModalEvent] = useState(null);
  const [qrTokenInput, setQrTokenInput] = useState('');
  const [scanning, setScanning] = useState(false);
  const [scanFeedback, setScanFeedback] = useState(null);

  const fetchEventsData = () => {
    if (!memberId) return;

    setLoading(true);
    Promise.all([
      fetch(`/api/evenements`).then((r) => (r.ok ? r.json() : [])),
      fetch(`/api/presences?membre=${memberId}`).then((r) => (r.ok ? r.json() : [])),
      fetch(`/api/membres/${memberId}/participation-stats`).then((r) => (r.ok ? r.json() : null)),
      fetch(`/api/type_evenements`).then((r) => (r.ok ? r.json() : [])),
    ])
      .then(([eventsData, presencesData, statsData, typesData]) => {
        let eventList = [];
        if (Array.isArray(eventsData)) {
          eventList = eventsData;
        } else if (eventsData && Array.isArray(eventsData['hydra:member'])) {
          eventList = eventsData['hydra:member'];
        } else if (eventsData && Array.isArray(eventsData.member)) {
          eventList = eventsData.member;
        }

        let presenceList = [];
        if (Array.isArray(presencesData)) {
          presenceList = presencesData;
        } else if (presencesData && Array.isArray(presencesData['hydra:member'])) {
          presenceList = presencesData['hydra:member'];
        } else if (presencesData && Array.isArray(presencesData.member)) {
          presenceList = presencesData.member;
        }

        let typeList = [];
        if (Array.isArray(typesData)) {
          typeList = typesData;
        } else if (typesData && Array.isArray(typesData['hydra:member'])) {
          typeList = typesData['hydra:member'];
        }

        setEvents(eventList);
        setPresences(presenceList);
        setStats(statsData);
        setTypesEvenement(typeList);
      })
      .catch((err) => console.error('Error fetching event presence data:', err))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchEventsData();
  }, [memberId]);

  const handleCreateEvent = (e) => {
    e.preventDefault();
    setSaving(true);

    fetch('/api/member-events/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newEvent),
    })
      .then((res) => {
        if (!res.ok) throw new Error('Erreur lors de la création de l\'événement.');
        return res.json();
      })
      .then(() => {
        setShowCardModal(false);
        setNewEvent({ nom: '', description: '', lieu: '', associationId: '', groupeId: '', typeEvenementId: '', dateDebut: '' });
        fetchEventsData();
      })
      .catch((err) => alert(err.message))
      .finally(() => setSaving(false));
  };

  const handleScanQr = (e) => {
    e.preventDefault();
    if (!qrTokenInput.trim() || !scanModalEvent) return;

    setScanning(true);
    setScanFeedback(null);

    fetch(`/api/member-events/${scanModalEvent.id}/scan`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ qrCodeToken: qrTokenInput.trim() }),
    })
      .then((res) => res.json())
      .then((data) => {
        setScanFeedback(data);
        setQrTokenInput('');
        fetchEventsData();
      })
      .catch((err) => setScanFeedback({ message: 'Erreur réseau lors du scan.' }))
      .finally(() => setScanning(false));
  };

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

  const filteredEvents = (Array.isArray(events) ? events : []).filter((event) => {
    const present = isPresentForEvent(event);
    if (filter === 'attended') return present;
    if (filter === 'missed') return !present;
    return true;
  });

  return (
    <div className="space-y-6">
      {/* Participation Overview Bar & Action Buttons */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
        <div>
          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Assiduité Annuelle</span>
          <h2 className="text-3xl font-extrabold text-emerald-600">
            {stats ? `${stats.tauxParticipation}%` : '100%'}
          </h2>
          <p className="text-xs text-slate-500 mt-1">Taux de présence globale calculé sur le nombre de séances</p>
        </div>

        <div className="col-span-2 flex flex-col justify-between space-y-4">
          <div className="flex items-center justify-between">
            <div className="text-xs font-bold text-slate-700 space-x-3">
              <span>Présences : {stats ? stats.presencesScannees : presences.length}</span>
              <span>• Événements : {stats ? stats.totalEvenements : events.length}</span>
            </div>

            <button
              onClick={() => setShowCardModal(true)}
              className="px-4 py-2 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-100 transition flex items-center"
            >
              <i className="fa-solid fa-plus mr-2"></i> Créer un Événement
            </button>
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
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
          <i className="fa-solid fa-calendar-check text-indigo-500 mr-2"></i> Historique des Événements & Pointages
        </h3>

        <div className="flex bg-slate-200/60 p-1 rounded-2xl text-xs font-bold space-x-1 self-start sm:self-auto">
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
                present ? 'bg-white border-slate-200 hover:border-emerald-300' : 'bg-slate-50 border-slate-200 opacity-90'
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

              <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                <div className="text-[11px] text-slate-400 space-x-2">
                  <span><i className="fa-solid fa-location-dot mr-1 text-indigo-500"></i> {event.lieu || 'Lieu N/A'}</span>
                  <span><i className="fa-solid fa-clock mr-1 text-indigo-500"></i> {event.dateDebut ? new Date(event.dateDebut).toLocaleDateString('fr-FR') : 'N/A'}</span>
                </div>

                {/* Scan Button on Event Card */}
                <button
                  onClick={() => {
                    setScanModalEvent(event);
                    setScanFeedback(null);
                    setQrTokenInput('');
                  }}
                  className="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs border border-indigo-200 transition flex items-center shrink-0"
                >
                  <i className="fa-solid fa-qrcode mr-1.5"></i> Scanner Présences
                </button>
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

      {/* Modal: Create Event */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl relative">
            <button
              onClick={() => setShowCardModal(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full"
            >
              <i className="fa-solid fa-xmark text-lg"></i>
            </button>

            <h3 className="text-lg font-extrabold text-slate-800 flex items-center">
              <i className="fa-solid fa-calendar-plus text-indigo-600 mr-2"></i> Créer un Nouvel Événement
            </h3>

            <form onSubmit={handleCreateEvent} className="space-y-4 pt-2">
              <div className="space-y-1">
                <label className="text-xs font-bold text-slate-700 block">Nom de l'Événement *</label>
                <input
                  type="text"
                  required
                  placeholder="ex: Formation des Jeunes, Chorale, Culte..."
                  value={newEvent.nom}
                  onChange={(e) => setNewEvent({ ...newEvent, nom: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-slate-700 block">Rattacher à une Association</label>
                  <select
                    value={newEvent.associationId}
                    onChange={(e) => setNewEvent({ ...newEvent, associationId: e.target.value })}
                    className="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500 bg-white"
                  >
                    <option value="">-- Aucune (Global / Paroisse) --</option>
                    {member && member.associations && member.associations.map((assoc) => (
                      <option key={assoc.id} value={assoc.id}>{assoc.nom}</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-slate-700 block">Type d'Événement</label>
                  <select
                    value={newEvent.typeEvenementId}
                    onChange={(e) => setNewEvent({ ...newEvent, typeEvenementId: e.target.value })}
                    className="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500 bg-white"
                  >
                    <option value="">-- Sélectionner un type --</option>
                    {typesEvenement.map((type) => (
                      <option key={type.id} value={type.id}>{type.nom}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-slate-700 block">Lieu</label>
                  <input
                    type="text"
                    placeholder="ex: Grande Salle, Temple..."
                    value={newEvent.lieu}
                    onChange={(e) => setNewEvent({ ...newEvent, lieu: e.target.value })}
                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-slate-700 block">Date & Heure de Début</label>
                  <input
                    type="datetime-local"
                    value={newEvent.dateDebut}
                    onChange={(e) => setNewEvent({ ...newEvent, dateDebut: e.target.value })}
                    className="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-bold text-slate-700 block">Description / Objectif</label>
                <textarea
                  rows="2"
                  placeholder="Détails de l'événement..."
                  value={newEvent.description}
                  onChange={(e) => setNewEvent({ ...newEvent, description: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
                ></textarea>
              </div>

              <div className="flex justify-end space-x-3 pt-2">
                <button
                  type="button"
                  onClick={() => setShowCardModal(false)}
                  className="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  disabled={creating}
                  className="px-6 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md transition disabled:opacity-50"
                >
                  {creating ? 'Création...' : 'Créer l\'Événement'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: QR Code Scanner for Event */}
      {scanModalEvent && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl relative">
            <button
              onClick={() => setScanModalEvent(null)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full"
            >
              <i className="fa-solid fa-xmark text-lg"></i>
            </button>

            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-100">
                Pointage de Présence
              </span>
              <h3 className="text-lg font-extrabold text-slate-800 mt-1">
                Scan QR Code : {scanModalEvent.nom}
              </h3>
            </div>

            {scanFeedback && (
              <div
                className={`p-4 rounded-2xl text-xs font-bold border flex items-center space-x-3 ${
                  scanFeedback.alreadyPresent
                    ? 'bg-amber-50 text-amber-800 border-amber-200'
                    : scanFeedback.membre
                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                    : 'bg-rose-50 text-rose-800 border-rose-200'
                }`}
              >
                <i
                  className={`fa-solid text-base ${
                    scanFeedback.alreadyPresent
                      ? 'fa-triangle-exclamation text-amber-600'
                      : scanFeedback.membre
                      ? 'fa-circle-check text-emerald-600'
                      : 'fa-circle-xmark text-rose-600'
                  }`}
                ></i>
                <div>
                  <p>{scanFeedback.message}</p>
                </div>
              </div>
            )}

            <form onSubmit={handleScanQr} className="space-y-4 pt-1">
              <div className="space-y-1">
                <label className="text-xs font-bold text-slate-700 block">
                  Scannez ou Saisissez le Token QR Code du Membre
                </label>
                <div className="relative">
                  <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                    <i className="fa-solid fa-qrcode text-sm"></i>
                  </span>
                  <input
                    type="text"
                    required
                    autoFocus
                    placeholder="Coller le token QR code (ex: 1893281a...)"
                    value={qrTokenInput}
                    onChange={(e) => setQrTokenInput(e.target.value)}
                    className="w-full pl-10 pr-4 py-3 rounded-2xl border border-slate-200 text-xs font-mono focus:outline-none focus:border-indigo-500 bg-slate-50 focus:bg-white"
                  />
                </div>
              </div>

              <div className="flex justify-end space-x-3">
                <button
                  type="button"
                  onClick={() => setScanModalEvent(null)}
                  className="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs"
                >
                  Fermer
                </button>
                <button
                  type="submit"
                  disabled={scanning}
                  className="px-6 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-100 transition disabled:opacity-50 flex items-center"
                >
                  <i className="fa-solid fa-barcode mr-2"></i>
                  {scanning ? 'Validation...' : 'Valider la Présence'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
