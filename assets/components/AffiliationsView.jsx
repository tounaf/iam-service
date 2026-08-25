import React, { useState, useEffect } from 'react';

export function AffiliationsView({ member, onRefresh }) {
  const [showMemberModal, setShowMemberModal] = useState(false);
  const [selectedAssoc, setSelectedAssoc] = useState(null);
  const [assocMembers, setAssocMembers] = useState([]);
  const [loadingMembers, setLoadingMembers] = useState(false);

  // Form State for Adding / Editing Member
  const [editingMember, setEditingMember] = useState(null);
  const [formData, setFormData] = useState({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    adresse: '',
  });
  const [photoFile, setPhotoFile] = useState(null);
  const [photoPreview, setPhotoPreview] = useState(null);
  const [saving, setSaving] = useState(false);
  const [feedback, setFeedback] = useState(null);

  if (!member) return null;

  const handleOpenAssocModal = (assoc) => {
    setSelectedAssoc(assoc);
    setShowMemberModal(true);
    setFeedback(null);
    setEditingMember(null);
    resetForm();
    fetchAssocMembers(assoc.id);
  };

  const fetchAssocMembers = (assocId) => {
    setLoadingMembers(true);
    fetch(`/api/membres?associations=${assocId}`)
      .then((r) => r.json())
      .then((data) => {
        let list = [];
        if (Array.isArray(data)) list = data;
        else if (data && Array.isArray(data['hydra:member'])) list = data['hydra:member'];
        else if (data && Array.isArray(data.member)) list = data.member;
        setAssocMembers(list);
      })
      .catch((err) => console.error('Error fetching association members:', err))
      .finally(() => setLoadingMembers(false));
  };

  const resetForm = () => {
    setFormData({ nom: '', prenom: '', email: '', telephone: '', adresse: '' });
    setPhotoFile(null);
    setPhotoPreview(null);
  };

  const handleEditClick = (m) => {
    setEditingMember(m);
    setFormData({
      nom: m.nom || '',
      prenom: m.prenom || '',
      email: m.email || '',
      telephone: m.telephone || '',
      adresse: m.adresse || '',
    });
    setPhotoPreview(m.photoUrl || null);
  };

  const handleSaveMember = (e) => {
    e.preventDefault();
    setSaving(true);
    setFeedback(null);

    const bodyData = new FormData();
    if (editingMember) {
      bodyData.append('memberId', editingMember.id);
    }
    bodyData.append('nom', formData.nom);
    bodyData.append('prenom', formData.prenom);
    bodyData.append('email', formData.email);
    bodyData.append('telephone', formData.telephone);
    bodyData.append('adresse', formData.adresse);
    if (selectedAssoc) {
      bodyData.append('associationId', selectedAssoc.id);
    }
    if (photoFile) {
      bodyData.append('photo', photoFile);
    }

    fetch('/api/association-membres/save', {
      method: 'POST',
      body: bodyData,
    })
      .then((res) => {
        if (!res.ok) throw new Error('Erreur lors de l\'enregistrement du membre.');
        return res.json();
      })
      .then((data) => {
        setFeedback({ type: 'success', text: data.message });
        setEditingMember(null);
        resetForm();
        fetchAssocMembers(selectedAssoc.id);
        if (onRefresh) onRefresh();
      })
      .catch((err) => {
        setFeedback({ type: 'error', text: err.message });
      })
      .finally(() => setSaving(false));
  };

  return (
    <div className="space-y-6">
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
        <h2 className="text-xl font-extrabold text-slate-800 mb-1">Affiliations & Rôles CRBAC</h2>
        <p className="text-xs text-slate-500">Aperçu de votre rattachement paroissial, zone géographique et associations d'église.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Paroisse */}
        <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-3">
          <div className="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xl">
            <i className="fa-solid fa-church"></i>
          </div>
          <div>
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Paroisse (Fiangonana)</span>
            <h3 className="text-lg font-extrabold text-slate-800">
              {member.fiangonana ? member.fiangonana.nom : 'Non rattaché'}
            </h3>
          </div>
          {member.fiangonana && (
            <p className="text-xs text-slate-500">
              Code: <span className="font-mono text-slate-700">{member.fiangonana.code || 'N/A'}</span>
            </p>
          )}
        </div>

        {/* Zone Geographique / Groupe */}
        <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-3">
          <div className="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xl">
            <i className="fa-solid fa-map-location-dot"></i>
          </div>
          <div>
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Zone / Section Locale</span>
            <h3 className="text-lg font-extrabold text-slate-800">
              {member.zoneGeographique ? member.zoneGeographique.nom : 'Non rattaché'}
            </h3>
          </div>
          {member.zoneGeographique && (
            <p className="text-xs text-slate-500">Zone administrative locale</p>
          )}
        </div>

        {/* Total Associations */}
        <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-3">
          <div className="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-xl">
            <i className="fa-solid fa-users-rectangle"></i>
          </div>
          <div>
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Associations Intégrées</span>
            <h3 className="text-lg font-extrabold text-slate-800">
              {member.associations ? member.associations.length : 0} Association(s)
            </h3>
          </div>
          <p className="text-xs text-slate-500">Groupements de jeunes / chorales / ministères</p>
        </div>
      </div>

      {/* Associations Details List & Management Action */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
          <i className="fa-solid fa-layer-group text-purple-500 mr-2"></i> Mes Associations d'Étape & Responsabilités
        </h3>

        {member.associations && member.associations.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {member.associations.map((assoc, idx) => (
              <div key={idx} className="p-5 rounded-3xl bg-slate-50 border border-slate-200 flex items-center justify-between space-x-4">
                <div className="flex items-center space-x-4">
                  <div className="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 font-bold flex items-center justify-center text-base shrink-0">
                    <i className="fa-solid fa-users"></i>
                  </div>
                  <div>
                    <h4 className="font-extrabold text-slate-800 text-sm">{assoc.nom}</h4>
                    <p className="text-xs text-slate-500">{assoc.description || 'Association active'}</p>
                  </div>
                </div>

                <button
                  onClick={() => handleOpenAssocModal(assoc)}
                  className="px-3.5 py-2 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md shadow-purple-100 transition flex items-center shrink-0"
                >
                  <i className="fa-solid fa-user-plus mr-1.5"></i> Gérer Membres
                </button>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-xs text-slate-400 italic">Vous n'êtes actuellement membre d'aucune association spécifique.</p>
        )}
      </div>

      {/* Modal: Manage & Add Members in Association */}
      {showMemberModal && selectedAssoc && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-2xl w-full p-6 space-y-5 shadow-2xl relative max-h-[90vh] flex flex-col">
            <button
              onClick={() => setShowMemberModal(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full"
            >
              <i className="fa-solid fa-xmark text-lg"></i>
            </button>

            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-purple-600 bg-purple-50 px-2.5 py-0.5 rounded-full border border-purple-100">
                Gestion des Membres d'Association
              </span>
              <h3 className="text-lg font-extrabold text-slate-800 mt-1">
                Association : {selectedAssoc.nom}
              </h3>
            </div>

            {feedback && (
              <div
                className={`p-3.5 rounded-2xl text-xs font-bold border flex items-center justify-between ${
                  feedback.type === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'
                }`}
              >
                <span>{feedback.text}</span>
                <button onClick={() => setFeedback(null)} className="text-slate-400 hover:text-slate-600">
                  <i className="fa-solid fa-xmark"></i>
                </button>
              </div>
            )}

            {/* Form: Add or Edit Member */}
            <form onSubmit={handleSaveMember} className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3 shrink-0">
              <div className="flex items-center justify-between border-b border-slate-200 pb-2">
                <span className="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
                  <i className={`fa-solid ${editingMember ? 'fa-pen' : 'fa-plus'} text-purple-600 mr-2`}></i>
                  {editingMember ? `Modifier : ${editingMember.prenom} ${editingMember.nom}` : 'Ajouter un Nouveau Membre'}
                </span>
                {editingMember && (
                  <button
                    type="button"
                    onClick={() => {
                      setEditingMember(null);
                      resetForm();
                    }}
                    className="text-[11px] text-slate-400 hover:text-slate-600 underline font-semibold"
                  >
                    Annuler modification
                  </button>
                )}
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input
                  type="text"
                  required
                  placeholder="Nom *"
                  value={formData.nom}
                  onChange={(e) => setFormData({ ...formData, nom: e.target.value })}
                  className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-purple-500 bg-white"
                />
                <input
                  type="text"
                  required
                  placeholder="Prénom *"
                  value={formData.prenom}
                  onChange={(e) => setFormData({ ...formData, prenom: e.target.value })}
                  className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-purple-500 bg-white"
                />
                <input
                  type="email"
                  required
                  placeholder="Email *"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-purple-500 bg-white"
                />
                <input
                  type="text"
                  placeholder="Téléphone"
                  value={formData.telephone}
                  onChange={(e) => setFormData({ ...formData, telephone: e.target.value })}
                  className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-purple-500 bg-white"
                />
              </div>

              <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-1">
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => setPhotoFile(e.target.files[0] || null)}
                  className="text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-purple-100 file:text-purple-700"
                />

                <button
                  type="submit"
                  disabled={saving}
                  className="w-full sm:w-auto px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-md transition disabled:opacity-50"
                >
                  {saving ? 'Enregistrement...' : editingMember ? 'Mettre à jour' : 'Ajouter au groupe'}
                </button>
              </div>
            </form>

            {/* List of Association Members */}
            <div className="flex-1 overflow-y-auto pr-1 space-y-2">
              <h4 className="text-xs font-extrabold text-slate-700 uppercase tracking-wider">
                Membres de {selectedAssoc.nom} ({assocMembers.length})
              </h4>

              {loadingMembers ? (
                <div className="p-6 text-center text-slate-400">
                  <i className="fa-solid fa-spinner fa-spin text-xl mb-1"></i>
                  <p className="text-xs">Chargement de la liste...</p>
                </div>
              ) : assocMembers.length > 0 ? (
                assocMembers.map((m, idx) => (
                  <div key={idx} className="p-3 rounded-2xl bg-white border border-slate-200 flex items-center justify-between space-x-3">
                    <div className="flex items-center space-x-3">
                      <div className="w-8 h-8 rounded-full bg-purple-100 text-purple-700 font-bold flex items-center justify-center text-xs overflow-hidden shrink-0">
                        {m.photoUrl ? (
                          <img src={m.photoUrl} className="w-full h-full object-cover" />
                        ) : (
                          m.prenom?.charAt(0)
                        )}
                      </div>
                      <div>
                        <p className="font-bold text-slate-800 text-xs">{m.prenom} {m.nom}</p>
                        <p className="text-[10px] text-slate-400">{m.email} • {m.telephone || 'Sans tél'}</p>
                      </div>
                    </div>

                    <button
                      onClick={() => handleEditClick(m)}
                      className="px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition flex items-center shrink-0"
                    >
                      <i className="fa-solid fa-pen-to-square mr-1"></i> Éditer
                    </button>
                  </div>
                ))
              ) : (
                <div className="p-6 text-center text-slate-400 text-xs">
                  Aucun membre rattaché à cette association.
                </div>
              )}
            </div>

            <div className="flex justify-end pt-2 border-t border-slate-100">
              <button
                onClick={() => setShowMemberModal(false)}
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
