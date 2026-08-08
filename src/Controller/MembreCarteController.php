<?php

namespace App\Controller;

use App\Entity\Membre;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MembreCarteController extends AbstractController
{
    #[Route('/api/membres/{id}/carte', name: 'api_membre_carte', methods: ['GET'])]
    public function __invoke(?Membre $membre): Response
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        $fiangonanaNom = htmlspecialchars($membre->getFiangonana() ? $membre->getFiangonana()->getNom() : 'Paroisse');
        $nomComplet = htmlspecialchars($membre->getPrenom() . ' ' . $membre->getNom());
        $email = htmlspecialchars($membre->getEmail() ?: 'N/A');
        $telephone = htmlspecialchars($membre->getTelephone() ?: 'N/A');
        $groupeNom = htmlspecialchars($membre->getZoneGeographique() ? $membre->getZoneGeographique()->getNom() : 'Aucun');

        $associationsList = '';
        foreach ($membre->getAssociations() as $assoc) {
            $associationsList .= sprintf('<span class="association-badge">%s</span>', htmlspecialchars($assoc->getNom()));
        }
        if (empty($associationsList)) {
            $associationsList = '<span class="association-badge" style="background-color: #f3f4f6; color: #9ca3af;">Aucune association</span>';
        }

        // Generate the URL pointing to the QR code endpoint
        $qrCodeUrl = sprintf('/api/membres/%d/qr-code', $membre->getId());

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Carte de Membre - {$nomComplet}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: #ffffff;
            width: 480px;
            height: 290px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .card-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #ffffff;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header-logo {
            font-size: 10px;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 6px;
            border-radius: 3px;
        }
        .card-body {
            display: flex;
            flex: 1;
            padding: 15px;
            background: #ffffff;
        }
        .card-left {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 140px;
            border-right: 1px solid #edf2f7;
            padding-right: 15px;
        }
        .qr-code {
            width: 110px;
            height: 110px;
            object-fit: contain;
            border: 1px solid #e2e8f0;
            padding: 4px;
            background: #ffffff;
            border-radius: 6px;
        }
        .qr-help {
            font-size: 9px;
            color: #718096;
            margin-top: 6px;
            text-align: center;
            font-weight: 500;
        }
        .card-right {
            flex: 1;
            padding-left: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .member-title {
            font-size: 11px;
            color: #a0aec0;
            text-transform: uppercase;
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .member-name {
            font-size: 20px;
            color: #2d3748;
            margin: 2px 0 10px 0;
            font-weight: bold;
            line-height: 1.2;
        }
        .detail-item {
            font-size: 12px;
            color: #4a5568;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
        }
        .detail-label {
            font-weight: bold;
            color: #2b6cb0;
            width: 85px;
            flex-shrink: 0;
        }
        .detail-value {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .associations-section {
            margin-top: 8px;
        }
        .association-badge {
            display: inline-block;
            background-color: #ebf8ff;
            color: #2b6cb0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            margin-right: 4px;
            margin-top: 4px;
            font-weight: 600;
            border: 1px solid #bee3f8;
        }
        .card-footer {
            background-color: #f7fafc;
            border-top: 1px solid #edf2f7;
            padding: 8px 15px;
            font-size: 10px;
            color: #718096;
            text-align: center;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <span>{$fiangonanaNom}</span>
            <span class="card-header-logo">CARTE MEMBRE</span>
        </div>
        <div class="card-body">
            <div class="card-left">
                <img src="{$qrCodeUrl}" class="qr-code" alt="QR Code" />
                <div class="qr-help">Scannez pour présence</div>
            </div>
            <div class="card-right">
                <div>
                    <div class="member-title">Membre</div>
                    <div class="member-name">{$nomComplet}</div>

                    <div class="detail-item">
                        <span class="detail-label">Téléphone :</span>
                        <span class="detail-value">{$telephone}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">E-mail :</span>
                        <span class="detail-value">{$email}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Groupe (Zone) :</span>
                        <span class="detail-value">{$groupeNom}</span>
                    </div>
                </div>
                <div class="associations-section">
                    <div class="member-title">Associations</div>
                    <div style="max-height: 52px; overflow-y: auto;">
                        {$associationsList}
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            Système d'Organisation Paroissiale - Généré automatiquement
        </div>
    </div>
</body>
</html>
HTML;

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]);
    }
}
