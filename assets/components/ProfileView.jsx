import React, { useState, useEffect } from 'react';

export function ProfileView({ member, onRefresh }) {
  const [showCardModal, setShowCardModal] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [showPasswordModal, setShowPasswordModal] = useState(false);
  const [cardData, setCardData] = useState(null);
  const [loadingCard, setLoadingCard] = useState(false);

  // Profile Form State
  const [formData, setFormData] = useState({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    adresse: '',
    dateNaissance: '',
  });
  const [photoFile, setPhotoFile] = useState(null);
  const [photoPreview, setPhotoPreview] = useState(null);
  const [isDragging, setIsDragging] = useState(false);
  const [saving, setSaving] = useState(false);

  // Password Change Form State
  const [passwordForm, setPasswordForm] = useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
  });
  const [changingPassword, setChangingPassword] = useState(false);
  const [passwordMessage, setPasswordMessage] = useState(null);

  const [message, setMessage] = useState(null);

  useEffect(() => {
    if (member) {
      setFormData({
        nom: member.nom || '',
        prenom: member.prenom || '',
        email: member.email || '',
        telephone: member.telephone || '',
        adresse: member.adresse || '',
        dateNaissance: member.dateNaissance ? member.dateNaissance.substring(0, 10) : '',
      });
      setPhotoPreview(member.photoUrl || null);

      if (member.id) {
        setLoadingCard(true);
        fetch(`/api/membres/${member.id}/carte?format=json`)
          .then((r) => (r.ok ? r.json() : null))
          .then((data) => setCardData(data))
          .catch((err) => console.error('Error fetching member card data:', err))
          .finally(() => setLoadingCard(false));
      }
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

  const handleFileSelect = (file) => {
    if (file && file.type.startsWith('image/')) {
      setPhotoFile(file);
      setPhotoPreview(URL.createObjectURL(file));
    }
  };

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    handleFileSelect(file);
  };

  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = (e) => {
    e.preventDefault();
    setIsDragging(false);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      handleFileSelect(e.dataTransfer.files[0]);
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
    data.append('dateNaissance', formData.dateNaissance);
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

  const handleChangePasswordSubmit = (e) => {
    e.preventDefault();
    setPasswordMessage(null);

    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      setPasswordMessage({ type: 'error', text: 'Les deux mots de passe ne correspondent pas.' });
      return;
    }

    setChangingPassword(true);

    fetch('/api/me/change-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        currentPassword: passwordForm.currentPassword,
        newPassword: passwordForm.newPassword,
      }),
    })
      .then(async (res) => {
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
          throw new Error(data.message || 'Erreur lors de la modification du mot de passe.');
        }
        return data;
      })
      .then((data) => {
        setPasswordMessage({ type: 'success', text: data.message });
        setPasswordForm({ currentPassword: '', newPassword: '', confirmPassword: '' });
      })
      .catch((err) => {
        setPasswordMessage({ type: 'error', text: err.message });
      })
      .finally(() => setChangingPassword(false));
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
              <div className="flex items-center space-x-2">
                <h1 className="text-2xl font-extrabold text-slate-800">
                  {member.prenom} {member.nom}
                </h1>
                {member.age !== null && member.age !== undefined && (
                  <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                    {member.age} ans
                  </span>
                )}
              </div>
              <p className="text-xs font-semibold text-slate-500">
                Membre #{member.id} • Inscrit {member.createdAt ? new Date(member.createdAt).toLocaleDateString('fr-FR') : 'N/A'}
              </p>
            </div>
          </div>

          <div className="flex items-center space-x-2 w-full sm:w-auto flex-wrap">
            <button
              onClick={() => setIsEditing(!isEditing)}
              className="flex-1 sm:flex-initial px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center justify-center"
            >
              <i className={`fa-solid ${isEditing ? 'fa-xmark' : 'fa-pen-to-square'} mr-2`}></i>
              {isEditing ? 'Annuler' : 'Éditer mon profil'}
            </button>
            <button
              onClick={() => {
                setShowPasswordModal(true);
                setPasswordMessage(null);
              }}
              className="flex-1 sm:flex-initial px-4 py-2.5 rounded-2xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold text-xs transition flex items-center justify-center"
            >
              <i className="fa-solid fa-key mr-2 text-xs"></i>
              Mot de passe
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

          {/* Photo upload / Drag & Drop Dropzone */}
          <div
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
            className={`p-6 rounded-2xl border-2 border-dashed transition-all duration-200 flex flex-col sm:flex-row items-center justify-center space-y-3 sm:space-y-0 sm:space-x-6 text-center sm:text-left ${
              isDragging ? 'border-indigo-500 bg-indigo-50/60 scale-[1.01]' : 'border-slate-300 bg-slate-50/80 hover:bg-slate-50'
            }`}
          >
            <div className="w-20 h-28 rounded-2xl border-2 border-slate-200 overflow-hidden shrink-0 bg-white shadow-sm flex items-center justify-center relative">
              {photoPreview ? (
                <img src={photoPreview} className="w-full h-full object-cover" />
              ) : (
                <div className="text-slate-300 flex flex-col items-center">
                  <i className="fa-solid fa-camera text-2xl mb-1"></i>
                  <span className="text-[10px] font-bold">Photo</span>
                </div>
              )}
            </div>

            <div className="flex-1 space-y-1">
              <label className="text-xs font-extrabold text-slate-800 block">
                Glissez-déposez votre photo de profil ici
              </label>
              <p className="text-[11px] text-slate-500">
                Formats acceptés: PNG, JPG, JPEG, WEBP.
              </p>

              <div className="pt-2 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                <label className="cursor-pointer px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition inline-flex items-center">
                  <i className="fa-solid fa-cloud-arrow-up mr-2"></i> Parcourir un fichier
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleFileChange}
                    className="hidden"
                  />
                </label>
                {photoFile && (
                  <span className="text-[11px] text-emerald-600 font-bold flex items-center">
                    <i className="fa-solid fa-check mr-1"></i> {photoFile.name}
                  </span>
                )}
              </div>
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
              <label className="text-xs font-bold text-slate-700">Date de Naissance</label>
              <input
                type="date"
                value={formData.dateNaissance}
                onChange={(e) => setFormData({ ...formData, dateNaissance: e.target.value })}
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
              <div className="flex justify-between py-2 border-b border-slate-100">
                <span className="text-slate-400 font-medium">Date de Naissance / Âge</span>
                <span className="font-bold text-slate-700">
                  {member.dateNaissance ? new Date(member.dateNaissance).toLocaleDateString('fr-FR') : 'Non renseignée'}
                  {member.age !== null && member.age !== undefined ? ` (${member.age} ans)` : ''}
                </span>
              </div>
            </div>
          </div>

          {/* QR Code Quick View Card */}
          <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col items-center justify-center text-center space-y-4">
            <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
                <i className="fa-solid fa-qrcode text-emerald-500 mr-2"></i> Carte & QR Code Présence
            </h3>

              {cardData && cardData.qrCodeBase64 ? (
                <div className="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col items-center space-y-2">
                  <img
                    src={`data:image/png;base64,${cardData.qrCodeBase64}`}
                    alt="QR Code"
                    className="w-36 h-36 object-contain"
                  />
                  <p className="text-[10px] font-mono text-slate-400">Token: {cardData.qrCodeToken}</p>
                </div>
              ) : member.qrCodeToken ? (
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
                className="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center"
            >
                <i className="fa-solid fa-print mr-1.5"></i> Ouvrir la carte officielle format impression &rarr;
            </a>
          </div>
        </div>
      )}

      {/* Modal: Change Password */}
      {showPasswordModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl relative">
            <button
              onClick={() => setShowPasswordModal(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full"
            >
              <i className="fa-solid fa-xmark text-lg"></i>
            </button>

            <h3 className="text-lg font-extrabold text-slate-800 flex items-center">
              <i className="fa-solid fa-key text-amber-500 mr-2"></i> Modifier mon Mot de Passe
            </h3>

            {passwordMessage && (
              <div
                className={`p-3.5 rounded-2xl text-xs font-bold border flex items-center space-x-2 ${
                  passwordMessage.type === 'success'
                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                    : 'bg-rose-50 text-rose-800 border-rose-200'
                }`}
              >
                <i className={`fa-solid ${passwordMessage.type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'}`}></i>
                <span>{passwordMessage.text}</span>
              </div>
            )}

            <form onSubmit={handleChangePasswordSubmit} className="space-y-4 pt-1">
              <div className="space-y-1">
                <label className="text-xs font-bold text-slate-700 block">Mot de passe actuel</label>
                <input
                  type="password"
                  placeholder="••••••••"
                  value={passwordForm.currentPassword}
                  onChange={(e) => setPasswordForm({ ...passwordForm, currentPassword: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-bold text-slate-700 block">Nouveau mot de passe *</label>
                <input
                  type="password"
                  required
                  placeholder="••••••••"
                  value={passwordForm.newPassword}
                  onChange={(e) => setPasswordForm({ ...passwordForm, newPassword: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-bold text-slate-700 block">Confirmer le nouveau mot de passe *</label>
                <input
                  type="password"
                  required
                  placeholder="••••••••"
                  value={passwordForm.confirmPassword}
                  onChange={(e) => setPasswordForm({ ...passwordForm, confirmPassword: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500"
                />
              </div>

              <div className="flex justify-end space-x-3 pt-2">
                <button
                  type="button"
                  onClick={() => setShowPasswordModal(false)}
                  className="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs"
                >
                  Fermer
                </button>
                <button
                  type="submit"
                  disabled={changingPassword}
                  className="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-md transition disabled:opacity-50"
                >
                  {changingPassword ? 'Modification...' : 'Changer le mot de passe'}
                </button>
              </div>
            </form>
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

            {/* Stylized Badge Preview */}
            <div className="bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200 flex flex-col relative">
              <div className="bg-gradient-to-r from-blue-700 to-indigo-800 text-white p-4 flex justify-between items-center">
                <div>
                  <h2 className="text-[10px] font-semibold uppercase tracking-widest text-blue-200">Carte de Membre Officielle</h2>
                  <h1 className="text-sm font-bold truncate max-w-[280px]">
                    {cardData?.fiangonanaNom || (member.fiangonana ? member.fiangonana.nom : 'Fiangonana')}
                  </h1>
                </div>
                <span className="bg-blue-500/30 text-white text-[9px] uppercase font-bold tracking-wider px-2 py-1 rounded border border-blue-400/30">
                  Membre
                </span>
              </div>

              <div className="p-5 flex flex-row items-start space-x-4">
                <div className="flex-1 space-y-2 text-left">
                  <div>
                    <p className="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">Nom & Prénom</p>
                    <p className="text-base font-bold text-slate-800 leading-snug">{member.nom} {member.prenom}</p>
                  </div>

                  <div className="space-y-1 text-xs">
                    <div>
                      <p className="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">Zone / Groupe</p>
                      <p className="text-slate-700 font-medium text-[11px]">{cardData?.groupeNom || member.zoneGeographique?.nom || 'Non spécifié'}</p>
                    </div>
                    <div>
                      <p className="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">Associations</p>
                      <p className="text-slate-700 font-medium text-[11px]">{cardData?.associationsStr || 'Aucune'}</p>
                    </div>
                  </div>
                </div>

                <div className="flex flex-col items-center justify-center bg-slate-50 p-2 rounded-xl border border-slate-100 shadow-inner">
                  {cardData?.qrCodeBase64 ? (
                    <img className="w-24 h-24 mix-blend-multiply" src={`data:image/png;base64,${cardData.qrCodeBase64}`} alt="QR Code" />
                  ) : (
                    <img className="w-24 h-24 mix-blend-multiply" src={`/api/membres/${member.id}/qr-code`} alt="QR Code" />
                  )}
                  <span className="text-[7px] uppercase tracking-widest text-slate-400 mt-1 font-bold">Scan Présence</span>
                </div>
              </div>

              <div className="bg-slate-50 px-5 py-2.5 border-t border-slate-100 flex justify-between items-center text-[10px] text-slate-500">
                <span>ID Membre: #{member.id}</span>
                <span className="font-medium">Validité Permanente</span>
              </div>
            </div>

            <div className="flex justify-end space-x-3 pt-2">
              <button
                type="button"
                onClick={() => setShowCardModal(false)}
                className="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs"
              >
                Fermer
              </button>
              <a
                href={`/api/membres/${member.id}/carte`}
                target="_blank"
                rel="noopener noreferrer"
                className="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md transition flex items-center"
              >
                <i className="fa-solid fa-print mr-2"></i> Imprimer la carte
              </a>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
