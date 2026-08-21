import React, { useState, useEffect } from 'react';

export function ProfileView({ member, onRefresh }) {
  const [showCardModal, setShowCardModal] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
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
  const [message, setMessage] = useState(null);

  useEffect(() => {
    if (member) {
      setFormData({
        nom: member.nom || '',
        prenom: member.prenom || '',
        email: member.email || '',
        telephone: member.telephone || '',
        adresse: member.adresse || '',
      });
      setPhotoPreview(member.photoUrl || null);
    }
  }, [member]);

  if (!member) {
    return (
      <div className="p-8 text-center text-slate-400">
        <i className="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
        <p>Chargement du profil...</p>
      </div>
    );
  }

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setPhotoFile(file);
      setPhotoPreview(URL.createObjectURL(file));
    }
  };

  const handleSaveProfile = (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);

    const data = new FormData();
    data.append('nom', formData.nom);
    data.append('prenom', formData.prenom);
    data.append('email', formData.email);
    data.append('telephone', formData.telephone);
    data.append('adresse', formData.adresse);
    if (photoFile) {
      data.append('photo', photoFile);
    }

    fetch(`/api/membres/${member.id}/update-profile`, {
      method: 'POST',
      body: data,
    })
      .then((res) => {
        if (!res.ok) throw new Error('Erreur lors de la mise à jour du profil.');
        return res.json();
      })
      .then((updatedData) => {
        setMessage({ type: 'success', text: 'Vos informations ont été mises à jour avec succès !' });
        setIsEditing(false);
        if (onRefresh) onRefresh();
      })
      .catch((err) => {
        setMessage({ type: 'error', text: err.message });
      })
      .finally(() => setSaving(false));
  };

  return (
    <div className="space-y-6">
      {/* Flash Feedback Message */}
      {message && (
        <div
          className={`p-4 rounded-2xl text-xs font-bold border flex items-center justify-between ${
            message.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
              : 'bg-rose-50 text-rose-800 border-rose-200'
          }`}
        >
          <div className="flex items-center space-x-2">
            <i className={`fa-solid ${message.type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'}`}></i>
            <span>{message.text}</span>
          </div>
          <button onClick={() => setMessage(null)} className="text-slate-400 hover:text-slate-600">
            <i className="fa-solid fa-xmark"></i>
          </button>
        </div>
      )}

      {/* Top Banner Card */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="h-32 bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-700"></div>
        <div className="px-6 pb-6 pt-0 relative flex flex-col sm:flex-row items-center sm:items-end justify-between gap-4 -mt-16 sm:-mt-12">
          <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-5 text-center sm:text-left">
            <div className="w-28 h-28 rounded-2xl border-4 border-white shadow-md overflow-hidden bg-white relative group">
              {photoPreview ? (
                <img src={photoPreview} alt={member.prenom} className="w-full h-full object-cover" />
              ) : (
                <div className="w-full h-full bg-indigo-100 text-indigo-700 font-extrabold text-3xl flex items-center justify-center">
                  {member.prenom ? member.prenom.charAt(0) : 'M'}
                </div>
              )}
            </div>
            <div className="sm:mb-1">
              <h1 className="text-2xl font-extrabold text-slate-800">
                {member.prenom} {member.nom}
              </h1>
              <p className="text-xs font-semibold text-slate-500">
                Membre #{member.id} • Registered {member.createdAt ? new Date(member.createdAt).toLocaleDateString('fr-FR') : 'N/A'}
              </p>
            </div>
          </div>

          <div className="flex items-center space-x-3 w-full sm:w-auto">
            <button
              onClick={() => setIsEditing(!isEditing)}
              className="flex-1 sm:flex-initial px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center justify-center"
            >
              <i className={`fa-solid ${isEditing ? 'fa-xmark' : 'fa-pen-to-square'} mr-2`}></i>
              {isEditing ? 'Annuler' : 'Éditer mon profil'}
            </button>
            <button
              onClick={() => setShowCardModal(true)}
              className="flex-1 sm:flex-initial px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-100 transition flex items-center justify-center"
            >
              <i className="fa-solid fa-id-card mr-2 text-sm"></i>
              Ma Carte QR Code
            </button>
          </div>
        </div>
      </div>

      {/* Edit Form or Info View */}
      {isEditing ? (
        <form onSubmit={handleSaveProfile} className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-6">
          <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
            <i className="fa-solid fa-user-pen text-indigo-500 mr-2"></i> Modifier mes Informations de Base
          </h3>

          {/* Photo upload input */}
          <div className="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-4">
            <div className="w-16 h-16 rounded-xl border border-slate-300 overflow-hidden shrink-0 bg-white">
              {photoPreview ? (
                <img src={photoPreview} className="w-full h-full object-cover" />
              ) : (
                <div className="w-full h-full bg-slate-200 flex items-center justify-center text-slate-400">
                  <i className="fa-solid fa-camera text-xl"></i>
                </div>
              )}
            </div>
            <div className="flex-1 text-center sm:text-left">
              <label className="text-xs font-bold text-slate-700 block mb-1">Changer la Photo de Profil</label>
              <input
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                className="text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="text-xs font-bold text-slate-700">Nom</label>
              <input
                type="text"
                required
                value={formData.nom}
                onChange={(e) => setFormData({ ...formData, nom: e.target.value })}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-bold text-slate-700">Prénom</label>
              <input
                type="text"
                required
                value={formData.prenom}
                onChange={(e) => setFormData({ ...formData, prenom: e.target.value })}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-bold text-slate-700">Adresse Email</label>
              <input
                type="email"
                required
                value={formData.email}
                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-bold text-slate-700">Téléphone</label>
              <input
                type="text"
                value={formData.telephone}
                onChange={(e) => setFormData({ ...formData, telephone: e.target.value })}
                placeholder="0340000000"
                className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div className="sm:col-span-2 space-y-1">
              <label className="text-xs font-bold text-slate-700">Adresse Résidentielle</label>
              <input
                type="text"
                value={formData.adresse}
                onChange={(e) => setFormData({ ...formData, adresse: e.target.value })}
                placeholder="Adresse complète..."
                className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <div className="flex justify-end space-x-3 pt-2">
            <button
              type="button"
              onClick={() => setIsEditing(false)}
              className="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs"
            >
              Annuler
            </button>
            <button
              type="submit"
              disabled={saving}
              className="px-6 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md transition disabled:opacity-50"
            >
              {saving ? 'Enregistrement...' : 'Enregistrer les modifications'}
            </button>
          </div>
        </form>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Personal Details */}
          <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
            <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
              <i className="fa-solid fa-user text-indigo-500 mr-2"></i> Informations Personnelles
            </h3>

            <div className="space-y-3 text-xs">
              <div className="flex justify-between py-2 border-b border-slate-100">
                <span className="text-slate-400 font-medium">Prénom & Nom</span>
                <span className="font-bold text-slate-700">{member.prenom} {member.nom}</span>
              </div>
              <div className="flex justify-between py-2 border-b border-slate-100">
                <span className="text-slate-400 font-medium">Email</span>
                <span className="font-bold text-slate-700">{member.email || 'Non renseigné'}</span>
              </div>
              <div className="flex justify-between py-2 border-b border-slate-100">
                <span className="text-slate-400 font-medium">Téléphone</span>
                <span className="font-bold text-slate-700">{member.telephone || 'Non renseigné'}</span>
              </div>
              <div className="flex justify-between py-2 border-b border-slate-100">
                <span className="text-slate-400 font-medium">Adresse</span>
                <span className="font-bold text-slate-700">{member.adresse || 'Non renseignée'}</span>
              </div>
            </div>
          </div>

          {/* QR Code Quick View Card */}
          <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col items-center justify-center text-center space-y-4">
            <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
              <i className="fa-solid fa-qrcode text-emerald-500 mr-2"></i> Token QR Code Présence
            </h3>

            {member.qrCodeToken ? (
              <div className="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col items-center space-y-2">
                <img
                  src={`/api/membres/${member.id}/qr-code`}
                  alt="QR Code"
                  className="w-36 h-36 object-contain"
                />
                <p className="text-[10px] font-mono text-slate-400">Token: {member.qrCodeToken}</p>
              </div>
            ) : (
              <p className="text-xs text-slate-400 italic">Aucun QR code généré. Veuillez demander à la direction.</p>
            )}

            <a
              href={`/api/membres/${member.id}/carte`}
              target="_blank"
              rel="noopener noreferrer"
              className="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
            >
              Ouvrir la carte officielle format impression &rarr;
            </a>
          </div>
        </div>
      )}

      {/* Modal for Member Card */}
      {showCardModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl relative">
            <button
              onClick={() => setShowCardModal(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full"
            >
              <i className="fa-solid fa-xmark text-lg"></i>
            </button>

            <h3 className="text-lg font-extrabold text-slate-800 flex items-center">
              <i className="fa-solid fa-id-card text-indigo-600 mr-2"></i> Carte Officielle Membre
            </h3>

            <div className="border border-slate-200 rounded-2xl p-4 bg-slate-50 text-center space-y-3">
              <div className="w-16 h-16 rounded-full mx-auto overflow-hidden bg-indigo-100 border-2 border-indigo-200 flex items-center justify-center">
                {member.photoUrl ? (
                  <img src={member.photoUrl} className="w-full h-full object-cover" />
                ) : (
                  <span className="text-indigo-700 font-extrabold text-xl">{member.prenom?.charAt(0)}</span>
                )}
              </div>
              <div>
                <p className="font-extrabold text-slate-800 text-base">{member.prenom} {member.nom}</p>
                <p className="text-xs text-slate-500">{member.fiangonana ? member.fiangonana.nom : 'Fiangonana'}</p>
              </div>

              {member.qrCodeToken && (
                <div className="bg-white p-3 rounded-xl border border-slate-200 inline-block shadow-sm">
                  <img src={`/api/membres/${member.id}/qr-code`} className="w-40 h-40 object-contain mx-auto" />
                </div>
              )}
            </div>

            <div className="flex justify-end space-x-3">
              <a
                href={`/api/membres/${member.id}/carte`}
                target="_blank"
                className="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md transition"
              >
                Imprimer Carte PDF / HTML
              </a>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
