import React, { useState, useEffect } from 'react';

export function FinancesView({ memberId, member }) {
  const [financesData, setFinancesData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());

  // Cotisation Form State
  const [cotisationForm, setCotisationForm] = useState({
    mois: new Date().getMonth() + 1,
    tranche: 1,
    montant: '',
    contextType: 'fiangonana',
    contextId: '',
  });
  const [addingCotisation, setAddingCotisation] = useState(false);

  // Don Form State
  const [donForm, setDonForm] = useState({
    montant: '',
    libelle: '',
    contextType: 'fiangonana',
    contextId: '',
  });
  const [addingDon, setAddingDon] = useState(false);

  const [message, setMessage] = useState(null);

  const fetchFinances = () => {
    if (!memberId) return;
    setLoading(true);

    fetch(`/api/membres/${memberId}/finances?year=${selectedYear}`)
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        setFinancesData(data);
      })
      .catch((err) => console.error('Error fetching finances:', err))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchFinances();
  }, [memberId, selectedYear]);

  const handleAddCotisation = (e) => {
    e.preventDefault();
    if (!cotisationForm.montant || parseFloat(cotisationForm.montant) <= 0) return;

    setAddingCotisation(true);
    setMessage(null);

    fetch(`/api/membres/${memberId}/cotisations/add`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...cotisationForm,
        annee: selectedYear,
        montant: parseFloat(cotisationForm.montant),
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.message) {
          setMessage({ type: 'success', text: data.message });
        }
        setCotisationForm({
          mois: new Date().getMonth() + 1,
          tranche: 1,
          montant: '',
          contextType: 'fiangonana',
          contextId: '',
        });
        fetchFinances();
      })
      .catch((err) => setMessage({ type: 'error', text: err.message }))
      .finally(() => setAddingCotisation(false));
  };

  const handleAddDon = (e) => {
    e.preventDefault();
    if (!donForm.montant || parseFloat(donForm.montant) <= 0) return;

    setAddingDon(true);
    setMessage(null);

    fetch(`/api/membres/${memberId}/dons/add`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...donForm,
        montant: parseFloat(donForm.montant),
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.message) {
          setMessage({ type: 'success', text: data.message });
        }
        setDonForm({
          montant: '',
          libelle: '',
          contextType: 'fiangonana',
          contextId: '',
        });
        fetchFinances();
      })
      .catch((err) => setMessage({ type: 'error', text: err.message }))
      .finally(() => setAddingDon(false));
  };

  if (loading) {
    return (
      <div className="p-8 text-center text-slate-400">
        <i className="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
        <p className="text-xs font-semibold">Chargement des cotisations et dons...</p>
      </div>
    );
  }

  const moisNoms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

  return (
    <div className="space-y-6">
      {/* Alert Message */}
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

      {/* KPI Overview Cards */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
        <div>
          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Suivi Cotisations Année {selectedYear}</span>
          <h2 className="text-3xl font-extrabold text-indigo-600">
            {financesData ? `${financesData.monthsPaidCount}/12 mois` : '0/12 mois'}
          </h2>
          <p className="text-xs text-slate-500 mt-1">Total payé: {financesData ? financesData.totalCotisationsYear.toLocaleString('fr-FR') : 0} Ar</p>
        </div>

        <div>
          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Dons Libres</span>
          <h2 className="text-3xl font-extrabold text-purple-600">
            {financesData ? `${financesData.totalDons.toLocaleString('fr-FR')} Ar` : '0 Ar'}
          </h2>
          <p className="text-xs text-slate-500 mt-1">Générosité enregistrée</p>
        </div>

        <div className="flex items-center justify-end space-x-2">
          <label className="text-xs font-bold text-slate-600">Année :</label>
          <select
            value={selectedYear}
            onChange={(e) => setSelectedYear(parseInt(e.target.value))}
            className="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold bg-slate-50 focus:outline-none"
          >
            {[2024, 2025, 2026, 2027].map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Monthly Matrix Table (12 months x 4 tranches) */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
          <i className="fa-solid fa-calendar-days text-indigo-500 mr-2"></i> Grille des Cotisations Mensuelles (4 Tranches par Mois)
        </h3>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 font-semibold uppercase text-slate-400 border-b border-slate-200">
              <tr>
                <th className="py-2.5 px-3">Mois</th>
                <th className="py-2.5 px-3 text-center">Tranche 1 (1/4)</th>
                <th className="py-2.5 px-3 text-center">Tranche 2 (2/4)</th>
                <th className="py-2.5 px-3 text-center">Tranche 3 (3/4)</th>
                <th className="py-2.5 px-3 text-center">Tranche 4 (4/4)</th>
                <th className="py-2.5 px-3 text-right">Total Mois</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-mono">
              {financesData && financesData.monthsMatrix.map((mData) => (
                <tr key={mData.mois} className="hover:bg-slate-50 transition">
                  <td className="py-3 px-3 font-sans font-bold text-slate-800">{moisNoms[mData.mois]}</td>
                  {[1, 2, 3, 4].map((tIdx) => {
                    const item = mData.tranches[tIdx];
                    return (
                      <td key={tIdx} className="py-3 px-3 text-center">
                        {item ? (
                          <span className="px-2 py-1 rounded bg-emerald-100 text-emerald-800 font-bold border border-emerald-200 block" title={item.paidAt}>
                            {item.montant.toLocaleString('fr-FR')} Ar
                          </span>
                        ) : (
                          <span className="text-slate-300 italic">-</span>
                        )}
                      </td>
                    );
                  })}
                  <td className="py-3 px-3 text-right font-bold text-indigo-700">
                    {mData.totalPaid.toLocaleString('fr-FR')} Ar
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Saisie Manuelle Cotisation Form */}
        <form onSubmit={handleAddCotisation} className="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-4 pt-4">
          <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider">Enregistrer une Cotisation (Saisie Manuelle Utilisateur Ayant Droit)</h4>

          <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Mois</label>
              <select
                value={cotisationForm.mois}
                onChange={(e) => setCotisationForm({ ...cotisationForm, mois: parseInt(e.target.value) })}
                className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none"
              >
                {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((m) => (
                  <option key={m} value={m}>{moisNoms[m]}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Tranche (1 à 4)</label>
              <select
                value={cotisationForm.tranche}
                onChange={(e) => setCotisationForm({ ...cotisationForm, tranche: parseInt(e.target.value) })}
                className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none"
              >
                <option value={1}>1ère tranche (1/4)</option>
                <option value={2}>2ème tranche (2/4)</option>
                <option value={3}>3ème tranche (3/4)</option>
                <option value={4}>4ème tranche (4/4)</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Montant Payé (Ar)</label>
              <input
                type="number"
                step="100"
                required
                placeholder="Ex: 5000"
                value={cotisationForm.montant}
                onChange={(e) => setCotisationForm({ ...cotisationForm, montant: e.target.value })}
                className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none"
              />
            </div>

            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Entité</label>
              <select
                value={cotisationForm.contextType}
                onChange={(e) => setCotisationForm({ ...cotisationForm, contextType: e.target.value })}
                className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none"
              >
                <option value="fiangonana">Paroisse</option>
                <option value="association">Association</option>
                <option value="groupe">Zone / Groupe</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end pt-1">
            <button
              type="submit"
              disabled={addingCotisation}
              className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition disabled:opacity-50"
            >
              {addingCotisation ? 'Enregistrement...' : 'Valider le Paiement'}
            </button>
          </div>
        </form>
      </div>

      {/* Dons Libres Section */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h3 className="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center">
          <i className="fa-solid fa-heart text-purple-500 mr-2"></i> Historique & Enregistrement des Dons Libres
        </h3>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-600">
            <thead className="bg-slate-50 font-semibold uppercase text-slate-400 border-b border-slate-200">
              <tr>
                <th className="py-2.5 px-3">Date</th>
                <th className="py-2.5 px-3">Libellé / Motif</th>
                <th className="py-2.5 px-3">Entité</th>
                <th className="py-2.5 px-3 text-right">Montant</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {financesData && financesData.dons.map((don) => (
                <tr key={don.id} className="hover:bg-slate-50 transition">
                  <td className="py-2.5 px-3 font-mono text-slate-400">{don.paidAt}</td>
                  <td className="py-2.5 px-3 font-bold text-slate-800">{don.libelle}</td>
                  <td className="py-2.5 px-3 text-slate-500">{don.entityType}</td>
                  <td className="py-2.5 px-3 text-right font-mono font-bold text-purple-700">
                    {don.montant.toLocaleString('fr-FR')} Ar
                  </td>
                </tr>
              ))}

              {(!financesData || financesData.dons.length === 0) && (
                <tr>
                  <td colSpan="4" className="py-4 text-center text-slate-400">Aucun don libre enregistré.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Saisie Manuelle Don Form */}
        <form onSubmit={handleAddDon} className="bg-purple-50/50 p-5 rounded-2xl border border-purple-100 space-y-4">
          <h4 className="text-xs font-bold text-purple-900 uppercase tracking-wider">Saisir un Don Libre</h4>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Montant (Ar)</label>
              <input
                type="number"
                step="500"
                required
                placeholder="Ex: 20000"
                value={donForm.montant}
                onChange={(e) => setDonForm({ ...donForm, montant: e.target.value })}
                className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none"
              />
            </div>

            <div className="sm:col-span-2">
              <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">Motif / Libellé</label>
              <input
                type="text"
                required
                placeholder="Ex: Offrande libre, Soutien projet..."
                value={donForm.libelle}
                onChange={(e) => setDonForm({ ...donForm, libelle: e.target.value })}
                className="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none"
              />
            </div>
          </div>

          <div className="flex justify-end pt-1">
            <button
              type="submit"
              disabled={addingDon}
              className="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-md transition disabled:opacity-50"
            >
              {addingDon ? 'Enregistrement...' : 'Enregistrer le Don'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
