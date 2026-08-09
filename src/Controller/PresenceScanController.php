<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PresenceScanController extends AbstractController
{
    #[Route('/membres/scan/{token}', name: 'presence_scan', methods: ['GET', 'POST'])]
    public function __invoke(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        ?TokenStorageInterface $tokenStorage = null
    ): Response {
        $membre = $entityManager->getRepository(Membre::class)->findOneBy(['qrCodeToken' => $token]);

        if (!$membre) {
            return new Response($this->renderErrorHtml(
                'Code QR non reconnu',
                'Le code QR scanné ne correspond à aucun membre enregistré.'
            ), Response::HTTP_NOT_FOUND, [
                'Content-Type' => 'text/html; charset=utf-8'
            ]);
        }

        if ($request->isMethod('POST')) {
            $activityName = trim($request->request->get('activityName', ''));
            if ($activityName === '') {
                return new Response($this->renderFormHtml(
                    $membre,
                    'Veuillez saisir ou choisir le nom de l\'activité.'
                ), Response::HTTP_BAD_REQUEST, [
                    'Content-Type' => 'text/html; charset=utf-8'
                ]);
            }

            // Create and persist presence
            $presence = new Presence();
            $presence->setMembre($membre);
            $presence->setActivityName($activityName);
            $presence->setScannedAt(new \DateTimeImmutable());

            // If a coordinator is logged in, register scannedBy
            if ($tokenStorage !== null) {
                $tokenObj = $tokenStorage->getToken();
                $user = $tokenObj?->getUser();
                if ($user instanceof Membre) {
                    $presence->setScannedBy($user);
                }
            }

            $entityManager->persist($presence);
            $entityManager->flush();

            return new Response($this->renderSuccessHtml(
                $membre,
                $activityName
            ), Response::HTTP_OK, [
                'Content-Type' => 'text/html; charset=utf-8'
            ]);
        }

        return new Response($this->renderFormHtml($membre), Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]);
    }

    private function renderFormHtml(Membre $membre, ?string $error = null): string
    {
        $nomComplet = htmlspecialchars($membre->getPrenom() . ' ' . $membre->getNom());
        $fiangonanaNom = htmlspecialchars($membre->getFiangonana() ? $membre->getFiangonana()->getNom() : 'Paroisse');
        $errorBlock = '';
        if ($error) {
            $errorBlock = sprintf('<div class="alert-error">%s</div>', htmlspecialchars($error));
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Pointer Présence - {$nomComplet}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 15px;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 440px;
            padding: 25px;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .parish {
            font-size: 11px;
            color: #4b5563;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .title {
            font-size: 20px;
            color: #1e3b8b;
            margin: 5px 0 0 0;
            font-weight: 700;
        }
        .member-info {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .member-label {
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            font-weight: 600;
        }
        .member-name {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            margin-top: 2px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-sizing: border-box;
            background-color: #ffffff;
            color: #111827;
        }
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn {
            display: block;
            width: 100%;
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #1d4ed8;
        }
        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 15px;
        }
        .suggestions {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .suggestion-chip {
            background-color: #edf2f7;
            color: #4a5568;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            cursor: pointer;
            border: 1px solid #cbd5e0;
            user-select: none;
        }
        .suggestion-chip:hover {
            background-color: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="parish">{$fiangonanaNom}</div>
            <div class="title">Validation de Présence</div>
        </div>

        {$errorBlock}

        <div class="member-info">
            <div class="member-label">Membre Scanné</div>
            <div class="member-name">{$nomComplet}</div>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="activityName">Nom de l'activité / Événement</label>
                <input type="text" id="activityName" name="activityName" class="form-control" placeholder="Ex: Formation des Jeunes 2026" required autofocus autocomplete="off" />
                <div class="suggestions">
                    <span class="suggestion-chip" onclick="selectActivity('Formation des Jeunes 2026')">Formation Jeunes</span>
                    <span class="suggestion-chip" onclick="selectActivity('Réunion d\'Association')">Réunion Assoc</span>
                    <span class="suggestion-chip" onclick="selectActivity('Culte du Sabbat')">Culte</span>
                    <span class="suggestion-chip" onclick="selectActivity('Séance d\'Étude')">Étude</span>
                </div>
            </div>

            <button type="submit" class="btn">Confirmer la Présence</button>
        </form>
    </div>

    <script>
        function selectActivity(name) {
            document.getElementById('activityName').value = name;
            document.getElementById('activityName').focus();
        }
    </script>
</body>
</html>
HTML;
    }

    private function renderSuccessHtml(Membre $membre, string $activityName): string
    {
        $nomComplet = htmlspecialchars($membre->getPrenom() . ' ' . $membre->getNom());
        $activity = htmlspecialchars($activityName);
        $date = (new \DateTimeImmutable())->format('d/m/Y à H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Présence Enregistrée avec Succès</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ecfdf5;
            color: #065f46;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 15px;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(6, 95, 70, 0.08);
            width: 100%;
            max-width: 440px;
            padding: 30px 25px;
            box-sizing: border-box;
            text-align: center;
            border-top: 5px solid #10b981;
        }
        .icon {
            font-size: 50px;
            color: #10b981;
            margin-bottom: 15px;
        }
        .title {
            font-size: 22px;
            font-weight: bold;
            color: #047857;
            margin: 0 0 10px 0;
        }
        .message {
            font-size: 14px;
            color: #374151;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .details-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            text-align: left;
            margin-bottom: 25px;
        }
        .detail-row {
            margin-bottom: 10px;
            font-size: 13px;
        }
        .detail-row:last-child {
            margin-bottom: 0;
        }
        .detail-label {
            font-weight: bold;
            color: #4b5563;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-val {
            font-size: 14px;
            color: #111827;
            margin-top: 2px;
            font-weight: 500;
        }
        .btn-back {
            display: inline-block;
            background-color: #10b981;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        .btn-back:hover {
            background-color: #059669;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✓</div>
        <div class="title">Présence Validée !</div>
        <div class="message">La participation du membre a été correctement enregistrée dans le système de suivi.</div>

        <div class="details-box">
            <div class="detail-row">
                <div class="detail-label">Membre</div>
                <div class="detail-val">{$nomComplet}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Activité</div>
                <div class="detail-val">{$activity}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Enregistré le</div>
                <div class="detail-val">{$date}</div>
            </div>
        </div>

        <button onclick="window.history.back();" class="btn-back" style="border:none; cursor:pointer;">Retour</button>
    </div>
</body>
</html>
HTML;
    }

    private function renderErrorHtml(string $title, string $message): string
    {
        $titleEsc = htmlspecialchars($title);
        $msgEsc = htmlspecialchars($message);

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titleEsc}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #fef2f2;
            color: #991b1b;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 15px;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(153, 27, 27, 0.08);
            width: 100%;
            max-width: 440px;
            padding: 30px 25px;
            box-sizing: border-box;
            text-align: center;
            border-top: 5px solid #ef4444;
        }
        .icon {
            font-size: 50px;
            color: #ef4444;
            margin-bottom: 15px;
        }
        .title {
            font-size: 22px;
            font-weight: bold;
            color: #991b1b;
            margin: 0 0 10px 0;
        }
        .message {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.5;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✗</div>
        <div class="title">{$titleEsc}</div>
        <div class="message">{$msgEsc}</div>
    </div>
</body>
</html>
HTML;
    }
}
