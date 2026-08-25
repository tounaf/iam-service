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

  // Attendees Modal State
  const [attendeesModalEvent, setAttendeesModalEvent] = useState(null);
  const [attendees, setAttendees] = useState([]);
  const [loadingAttendees, setLoadingAttendees] = useState(false);

  // Evaluation & Detail Modal State
  const [detailModalEvent, setDetailModalEvent] = useState(null);
  const [noteInput, setNoteInput] = useState('');
  const [compteRenduInput, setCompteRenduInput] = useState('');
  const [mediaFile, setMediaFile] = useState(null);
  const [mediaUrlInput, setMediaUrlInput] = useState('');
  const [updatingDetail, setUpdatingDetail] = useState(false);

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

  const handleViewAttendees = (event) => {
    setAttendeesModalEvent(event);
    setLoadingAttendees(true);

    fetch(`/api/member-events/${event.id}/attendees`)
      .then((res) => res.json())
      .then((data) => {
        setAttendees(data.attendees || []);
      })
      .catch((err) => console.error('Error fetching attendees:', err))
      .finally(() => setLoadingAttendees(false));
  };

  const handleOpenDetailModal = (event) => {
    setDetailModalEvent(event);
    setCompteRenduInput(event.compteRendu || '');
    setNoteInput('');
    setMediaFile(null);
    setMediaUrlInput('');
  };

  const handleAddNote = (text) => {
    const val = text || noteInput;
    if (!val.trim() || !detailModalEvent) return;

    setUpdatingDetail(true);
    fetch(`/api/member-events/${detailModalEvent.id}/add-note`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ note: val.trim() }),
    })
      .then((r) => r.json())
      .then(() => {
        setNoteInput('');
        fetchEventsData();
        // refresh local detailModalEvent notes
        setDetailModalEvent((prev) => ({
          ...prev,
          notes: prev.notes ? [...prev.notes, { contenu: val.trim() }] : [{ contenu: val.trim() }],
        }));
      })
      .finally(() => setUpdatingDetail(false));
  };

  const handleSaveCompteRendu = (e) => {
    e.preventDefault();
    if (!detailModalEvent) return;

    setUpdatingDetail(true);
    fetch(`/api/member-events/${detailModalEvent.id}/compte-rendu`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ compteRendu: compteRenduInput }),
    })
      .then((r) => r.json())
      .then(() => {
        fetchEventsData();
      })
      .finally(() => setUpdatingDetail(false));
  };

  const handleUploadMedia = (e) => {
    e.preventDefault();
    if (!detailModalEvent) return;

    const data = new FormData();
    if (mediaFile) data.append('mediaFile', mediaFile);
    if (mediaUrlInput) data.append('mediaUrl', mediaUrlInput);

    setUpdatingDetail(true);
    fetch(`/api/member-events/${detailModalEvent.id}/upload-media`, {
      method: 'POST',
      body: data,
    })
      .then((r) => r.json())
      .then((resData) => {
        setMediaFile(null);
        setMediaUrlInput('');
        fetchEventsData();
        if (resData.mediaUrls) {
          setDetailModalEvent((prev) => ({
            ...prev,
            medias: resData.mediaUrls.map((u) => ({ url: u })),
          }));
        }
      })
      .finally(() => setUpdatingDetail(false));
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
                  <h4
                    onClick={() => handleOpenDetailModal(event)}
                    className="text-base font-extrabold text-slate-800 mt-2 hover:text-indigo-600 cursor-pointer"
                  >
                    {event.nom}
                  </h4>
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

                <div className="flex items-center space-x-2 shrink-0">
                  {/* View Details / Report / Evaluation Button */}
                  <button
                    onClick={() => handleOpenDetailModal(event)}
                    className="px-3 py-1.5 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold text-xs border border-purple-200 transition flex items-center"
                  >
                    <i className="fa-solid fa-[#fa-file-lines] mr-1.5 fa-file-pen"></i> Rapport & Médias
                  </button>

                  {/* View Attendees List Button */}
                  <button
                    onClick={() => handleViewAttendees(event)}
                    className="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center"
                  >
                    <i className="fa-solid fa-users mr-1.5"></i> Présents
                  </button>

                  {/* Scan Button on Event Card */}
                  <button
                    onClick={() => {
                      setScanModalEvent(event);
                      setScanFeedback(null);
                      setQrTokenInput('');
                    }}
                    className="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs border border-indigo-200 transition flex items-center"
                  >
                    <i className="fa-solid fa-qrcode mr-1.5"></i> Scanner
                  </button>
                </div>
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

      {/* Modal: Event Detail, Report, Evaluation, & Media Upload */}
      {detailModalEvent && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-2xl w-full p-6 space-y-5 shadow-2xl relative max-h-[90vh] flex flex-col">
            <button
              onClick={() => setDetailModalEvent(null)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full"
            >
              <i className="fa-solid fa-xmark text-lg"></i>
            </button>

            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-purple-600 bg-purple-50 px-2.5 py-0.5 rounded-full border border-purple-100">
                Fiche & Rapport d'Événement
              </span>
              <h3 className="text-xl font-extrabold text-slate-800 mt-1">
                {detailModalEvent.nom}
              </h3>
            </div>

            <div className="flex-1 overflow-y-auto pr-1 space-y-5">
              {/* Section 1: Notes & Evaluation */}
              <div className="p-4 rounded-2xl bg-amber-50/60 border border-amber-200 space-y-3">
                <h4 className="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
                  <i className="fa-solid fa-star text-amber-500 mr-2"></i> Évaluations & Notes
                </h4>

                <div className="flex flex-wrap gap-2">
                  {detailModalEvent.notes && detailModalEvent.notes.length > 0 ? (
                    detailModalEvent.notes.map((n, i) => (
                      <span key={i} className="px-3 py-1 bg-white rounded-xl text-xs font-bold text-amber-900 border border-amber-200 shadow-sm">
                        • {typeof n === 'string' ? n : n.contenu}
                      </span>
                    ))
                  ) : (
                    <span className="text-xs text-slate-400 italic">Aucune note attribuée.</span>
                  )}
                </div>

                <div className="flex items-center space-x-2 pt-1">
                  <input
                    type="text"
                    placeholder="Ajouter une appréciation (ex: Très bien, Succès...)"
                    value={noteInput}
                    onChange={(e) => setNoteInput(e.target.value)}
                    className="flex-1 px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-amber-500"
                  />
                  <button
                    type="button"
                    onClick={() => handleAddNote('Très bien')}
                    className="px-2.5 py-2 bg-emerald-100 text-emerald-800 font-bold text-[11px] rounded-xl hover:bg-emerald-200"
                  >
                    + Très bien
                  </button>
                  <button
                    type="button"
                    onClick={() => handleAddNote()}
                    disabled={updatingDetail}
                    className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl transition"
                  >
                    Ajouter
                  </button>
                </div>
              </div>

              {/* Section 2: Compte-Rendu */}
              <form onSubmit={handleSaveCompteRendu} className="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <h4 className="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
                  <i className="fa-solid fa-file-pen text-indigo-500 mr-2"></i> Compte-Rendu & Résumé
                </h4>

                <textarea
                  rows="3"
                  placeholder="Saisissez les faits marquants ou le résumé..."
                  value={compteRenduInput}
                  onChange={(e) => setCompteRenduInput(e.target.value)}
                  className="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-700 bg-white focus:outline-none focus:border-indigo-500"
                ></textarea>

                <div className="flex justify-end">
                  <button
                    type="submit"
                    disabled={updatingDetail}
                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition"
                  >
                    Enregistrer le Compte-Rendu
                  </button>
                </div>
              </form>

              {/* Section 3: Media Upload & Gallery */}
              <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <h4 className="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
                  <i className="fa-solid fa-photo-film text-purple-500 mr-2"></i> Photos & Vidéos Souvenirs
                </h4>

                <form onSubmit={handleUploadMedia} className="flex flex-col sm:flex-row items-center gap-2">
                  <input
                    type="file"
                    accept="image/*,video/*"
                    onChange={(e) => setMediaFile(e.target.files[0] || null)}
                    className="text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-purple-100 file:text-purple-700"
                  />
                  <input
                    type="text"
                    placeholder="Ou URL externe..."
                    value={mediaUrlInput}
                    onChange={(e) => setMediaUrlInput(e.target.value)}
                    className="flex-1 px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-purple-500"
                  />
                  <button
                    type="submit"
                    disabled={updatingDetail}
                    className="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-sm transition whitespace-nowrap"
                  >
                    Téléverser
                  </button>
                </form>

                {/* Media Gallery */}
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-2">
                  {detailModalEvent.medias && detailModalEvent.medias.length > 0 ? (
                    detailModalEvent.medias.map((m, i) => {
                      const url = typeof m === 'string' ? m : m.url;
                      const isVideo = url.endsWith('.mp4') || url.endsWith('.webm') || url.endsWith('.ogg');
                      return (
                        <div key={i} className="aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-900">
                          {isVideo ? (
                            <video src={url} controls className="w-full h-full object-cover"></video>
                          ) : (
                            <img src={url} className="w-full h-full object-cover" />
                          )}
                        </div>
                      );
                    })
                  ) : (
                    <div className="col-span-full text-center text-slate-400 text-xs py-2">
                      Aucun média joint pour cet événement.
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="flex justify-end pt-2 border-t border-slate-100">
              <button
                onClick={() => setDetailModalEvent(null)}
                className="px-5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition"
              >
                Fermer
              </button>
            </div>
          </div>
        </div>
      )}

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

      {/* Modal: View Event Attendees */}
      {attendeesModalEvent && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl relative max-h-[85vh] flex flex-col">
            <button
              onClick={() => setAttendeesModalEvent(null)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full"
            >
              <i className="fa-solid fa-xmark text-lg"></i>
            </button>

            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-100">
                Membres Présents
              </span>
              <h3 className="text-lg font-extrabold text-slate-800 mt-1">
                {attendeesModalEvent.nom} ({attendees.length})
              </h3>
            </div>

            <div className="flex-1 overflow-y-auto pt-2 pr-1 space-y-2">
              {loadingAttendees ? (
                <div className="p-8 text-center text-slate-400">
                  <i className="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
                  <p className="text-xs">Chargement des membres présents...</p>
                </div>
              ) : attendees.length > 0 ? (
                attendees.map((attendee, idx) => (
                  <div key={idx} className="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between space-x-3">
                    <div className="flex items-center space-x-3">
                      <div className="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-xs overflow-hidden shrink-0">
                        {attendee.photoUrl ? (
                          <img src={attendee.photoUrl} className="w-full h-full object-cover" />
                        ) : (
                          attendee.prenom?.charAt(0)
                        )}
                      </div>
                      <div>
                        <p className="font-bold text-slate-800 text-xs">{attendee.prenom} {attendee.nom}</p>
                        <p className="text-[10px] text-slate-400">{attendee.email || 'Pas d\'email'}</p>
                      </div>
                    </div>

                    <span className="text-[10px] font-mono text-slate-400 bg-white px-2 py-1 rounded-lg border border-slate-200 shrink-0">
                      {attendee.scannedAt}
                    </span>
                  </div>
                ))
              ) : (
                <div className="p-8 text-center text-slate-400 text-xs">
                  Aucun membre marqué présent pour cet événement pour l'instant.
                </div>
              )}
            </div>

            <div className="flex justify-end pt-2 border-t border-slate-100">
              <button
                onClick={() => setAttendeesModalEvent(null)}
                className="px-5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition"
              >
                Fermer
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
