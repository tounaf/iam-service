import React, { useState, useEffect } from 'react';

export function ProfileView({ member, onRefresh }) {
  const [showCardModal, setShowCardModal] = useState(false);

  if (!member) {
    return (
      <div className="p-8 text-center text-slate-400">
        <i className="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
        <p>Chargement du profil...</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Top Banner Card */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="h-32 bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-700"></div>
        <div className="px-6 pb-6 pt-0 relative flex flex-col sm:flex-row items-center sm:items-end justify-between gap-4 -mt-16 sm:-mt-12">
          <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-5 text-center sm:text-left">
            <div className="w-28 h-28 rounded-2xl border-4 border-white shadow-md overflow-hidden bg-white">
              {member.photoUrl ? (
                <img src={member.photoUrl} alt={member.prenom} className="w-full h-full object-cover" />
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
              onClick={() => setShowCardModal(true)}
              className="flex-1 sm:flex-initial px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-100 transition flex items-center justify-center"
            >
              <i className="fa-solid fa-id-card mr-2 text-sm"></i>
              Ma Carte Membre QR Code
            </button>
          </div>
        </div>
      </div>

      {/* Info Grid */}
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
