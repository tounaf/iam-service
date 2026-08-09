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
use Twig\Environment;

class PresenceScanController extends AbstractController
{
    public function __construct(
        private Environment $twig
    ) {}

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
        $nomComplet = $membre->getPrenom() . ' ' . $membre->getNom();
        $fiangonanaNom = $membre->getFiangonana() ? $membre->getFiangonana()->getNom() : 'Paroisse';

        return $this->twig->render('presence/scan_form.html.twig', [
            'nomComplet' => $nomComplet,
            'fiangonanaNom' => $fiangonanaNom,
            'error' => $error,
        ]);
    }

    private function renderSuccessHtml(Membre $membre, string $activityName): string
    {
        $nomComplet = $membre->getPrenom() . ' ' . $membre->getNom();
        $date = (new \DateTimeImmutable())->format('d/m/Y à H:i');

        return $this->twig->render('presence/scan_success.html.twig', [
            'nomComplet' => $nomComplet,
            'activity' => $activityName,
            'date' => $date,
        ]);
    }

    private function renderErrorHtml(string $title, string $message): string
    {
        return $this->twig->render('presence/scan_error.html.twig', [
            'title' => $title,
            'message' => $message,
        ]);
    }
}
