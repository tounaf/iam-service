import React from 'react';

export function AffiliationsView({ member }) {
  if (!member) return null;

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

      {/* Associations Details List */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
          <i className="fa-solid fa-layer-group text-purple-500 mr-2"></i> Mes Associations d'Étape & Responsabilités
        </h3>

        {member.associations && member.associations.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {member.associations.map((assoc, idx) => (
              <div key={idx} className="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center space-x-4">
                <div className="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 font-bold flex items-center justify-center text-sm">
                  <i className="fa-solid fa-users"></i>
                </div>
                <div>
                  <h4 className="font-bold text-slate-800 text-sm">{assoc.nom}</h4>
                  <p className="text-xs text-slate-500">{assoc.description || 'Association active'}</p>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-xs text-slate-400 italic">Vous n'êtes actuellement membre d'aucune association spécifique.</p>
        )}
      </div>
    </div>
  );
}
